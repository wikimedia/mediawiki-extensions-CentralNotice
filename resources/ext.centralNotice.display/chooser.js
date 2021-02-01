/* eslint-disable no-unused-vars */
/**
 * Logic for selecting a campaign and a banner (or not). Provides
 * cn.internal.chooser.
 */
( function () {

	var cn = mw.centralNotice,

		// Minutes leeway for checking stale choice data. Should be the same
		// as SpecialBannerLoader::CAMPAIGN_STALENESS_LEEWAY.
		// TODO Make this a global config variable.
		CAMPAIGN_STALENESS_LEEWAY = 15;

	/**
	 * For targeted users (users meeting the same logged-in status, country,
	 * and language criteria as this user) calculate the probability that
	 * of receiving each campaign in availableCampaigns, and set the probability
	 * on the allocation property of each campaign. This takes into account
	 * campaign priority and throttling. The equivalent server-side method
	 * is AllocationCalculator::calculateCampaignAllocations().
	 *
	 * @param availableCampaigns
	 */
	function setCampaignAllocations( availableCampaigns ) {

		var i, campaign, campaignPriority,
			campaignsByPriority = [],
			priorities = [],
			priority, campaignsAtThisPriority,
			remainingAllocation = 1,
			j, campaignsAtThisPriorityCount, currentFullAllocation,
			actualAllocation;

		// Optimize for the common scenario of a single campaign
		if ( availableCampaigns.length === 1 ) {
			availableCampaigns[ 0 ].allocation = availableCampaigns[ 0 ].throttle / 100;
			return;
		}

		// Make an index of campaigns by priority level.
		// Note that the actual values of priority levels are integers,
		// and higher integers represent higher priority. These values are
		// defined by class constants in the CentralNotice PHP class.

		for ( i = 0; i < availableCampaigns.length; i++ ) {

			campaign = availableCampaigns[ i ];
			campaignPriority = campaign.preferred;

			// Initialize index the first time we hit this priority
			if ( !campaignsByPriority[ campaignPriority ] ) {
				campaignsByPriority[ campaignPriority ] = [];
			}

			campaignsByPriority[ campaignPriority ].push( campaign );
		}

		// Make an array of priority levels and sort in descending order.
		for ( priority in campaignsByPriority ) {
			priorities.push( priority );
		}
		priorities.sort();
		priorities.reverse();

		// Now go through the priority levels from highest to lowest. If
		// campaigns are not throttled, then campaigns with a higher
		// priority level will eclipse all campaigns with lower priority.
		// Only if some campaigns are throttled will they allow some space
		// for campaigns at the next level down.

		for ( i = 0; i < priorities.length; i++ ) {

			campaignsAtThisPriority = campaignsByPriority[ priorities[ i ] ];

			// If we fully allocated at a previous level, set allocations
			// at this level to zero. (We check with 0.01 instead of 0 in
			// case of issues due to finite precision.)
			if ( remainingAllocation < 0.01 ) {
				for ( j = 0; j < campaignsAtThisPriority.length; j++ ) {
					campaignsAtThisPriority[ j ].allocation = 0;
				}
				continue;
			}

			// If we are here, there is some allocation remaining.

			// All campaigns at a given priority level are alloted the same
			// allocation, unless they are throttled, in which case the
			// throttling value (taken as a percentage of the whole
			// allocation pie) is their maximum possible allocation.

			// To calculate this, we'll loop through the campaigns at this
			// level in order from the most throttled (lowest throttling
			// value) to the least throttled (highest value) and on each
			// loop, we'll re-calculate the remaining total allocation and
			// the proportional (i.e. unthrottled) allocation available to
			// each campaign.

			// First, sort the campaigns by throttling value (ascending)

			campaignsAtThisPriority.sort( function ( a, b ) {
				if ( a.throttle < b.throttle ) {
					return -1;
				}
				if ( a.throttle > b.throttle ) {
					return 1;
				}
				return 0;
			} );

			campaignsAtThisPriorityCount = campaignsAtThisPriority.length;
			for ( j = 0; j < campaignsAtThisPriorityCount; j++ ) {

				campaign = campaignsAtThisPriority[ j ];

				// Calculate the proportional, unthrottled allocation now
				// available to a campaign at this level.
				currentFullAllocation =
					remainingAllocation / ( campaignsAtThisPriorityCount - j );

				// A campaign may get the above amount, or less, if
				// throttling indicates that'd be too much.
				actualAllocation =
					Math.min( currentFullAllocation, campaign.throttle / 100 );

				campaign.allocation = actualAllocation;

				// Update remaining allocation
				remainingAllocation -= actualAllocation;
			}
		}
	}

	/**
	 * Filter banners for this campaign on the user's logged-in status,
	 * device and bucket (some banners that are not for the user's status
	 * or device may remain following previous filters) and return a list
	 * of possible banners to chose from.
	 *
	 * The equivalent server-side method
	 * AllocationCalculator::makePossibleBanners().
	 *
	 * @param campaign
	 * @param bucket
	 * @param anon
	 * @param device
	 */
	function makePossibleBanners( campaign, bucket, anon, device ) {

		var i, campaignName, banner,
			possibleBanners = [];

		campaignName = campaign.name;

		for ( i = 0; i < campaign.banners.length; i++ ) {
			banner = campaign.banners[ i ];

			// Filter for bucket
			if ( bucket !== banner.bucket ) {
				continue;
			}

			// Filter for logged-in status
			if ( anon && !banner.display_anon ) {
				continue;
			}
			if ( !anon && !banner.display_account ) {
				continue;
			}

			// Filter for device
			if ( banner.devices.indexOf( device ) === -1 ) {
				continue;
			}

			possibleBanners.push( banner );
		}

		return possibleBanners;
	}

	/**
	 * Calculate the allocation of banners (from a single campaign) based on
	 * relative weights of banners in possibleBanners. The equivalent
	 * server-side method is
	 * AllocationCalculator::calculateBannerAllocations().
	 *
	 * @param possibleBanners
	 */
	function setBannerAllocations( possibleBanners ) {
		var i, banner,
			totalWeights = 0;

		// Optimize for just one banner available for the user in this
		// campaign, by far our most common scenario.
		if ( possibleBanners.length === 1 ) {
			possibleBanners[ 0 ].allocation = 1;
			return;
		}

		// Find the sum of all banner weights
		for ( i = 0; i < possibleBanners.length; i++ ) {
			totalWeights += possibleBanners[ i ].weight;
		}

		// Set allocation property to the normalized weight
		for ( i = 0; i < possibleBanners.length; i++ ) {
			banner = possibleBanners[ i ];
			banner.allocation = banner.weight / totalWeights;
		}
	}

	/**
	 * Method used for choosing a campaign or banner from an array of
	 * allocated campaigns or banners.
	 *
	 * Given an array of objects with 'allocation' properties, the sum of which
	 * is greater than or equal to 0 and less than or equal to 1, return the
	 * object whose allocation block is indicated by a number greater than or
	 * equal to 0 and less than 1.
	 *
	 * @param {number} random A random number, greater or equal to 0  and less
	 *   than 1, to use in choosing an object.
	 * @param {Array} allocatedArray
	 * @return {?Object} The selected element in the array
	 */
	function chooseObjInAllocatedArray( random, allocatedArray ) {
		var blockStart = 0,
			i, obj, blockEnd;

		// Cycle through objects, calculating which piece of
		// the allocation pie they should get. When random is in the piece,
		// choose the object.

		for ( i = 0; i < allocatedArray.length; i++ ) {
			obj = allocatedArray[ i ];
			blockEnd = blockStart + obj.allocation;

			if ( ( random >= blockStart ) && ( random < blockEnd ) ) {
				return obj;
			}

			blockStart = blockEnd;
		}

		// We get here if there is less than full allocation (including no
		// allocation) and random points to the unallocated chunk.
		return null;
	}

	/**
	 * Chooser object (intended for access from within this RL module)
	 */
	cn.internal.chooser = {

		/**
		 * Filter choiceData on country, region, logged-in status and device.
		 * Only campaigns that target the user's country and have at least
		 * one banner for the user's logged-in status and device pass this filter.
		 *
		 * Campaigns that don't target the user's country or region, or have
		 * no banners for their logged-in status and device will be removed.
		 *
		 * The server-side equivalent of this method is
		 * AllocationCalculator::makeAvailableCampaigns().
		 *
		 * @param choiceData
		 * @param country
		 * @param region
		 * @param anon
		 * @param device
		 * @return {Array}
		 */
		makeAvailableCampaigns: function ( choiceData, country, region, anon, device ) {

			var i, campaign, j, banner, keepCampaign, uniqueRegionCode,
				availableCampaigns = [];

			// This needs to yield the same result as makeUniqueRegionCode in GeoTarget.php
			uniqueRegionCode = country + '_' + region;

			for ( i = 0; i < choiceData.length; i++ ) {

				campaign = choiceData[ i ];
				keepCampaign = false;

				// Filter for country if geotargeted
				if ( campaign.geotargeted && (
					campaign.countries.indexOf( country ) === -1 && // No country wide match
					campaign.regions.indexOf( uniqueRegionCode ) === -1 // And no region match
				) ) {
					continue;
				}

				// Now filter by banner logged-in status and device.
				for ( j = 0; j < campaign.banners.length; j++ ) {
					banner = campaign.banners[ j ];

					// Logged-in status
					if ( anon && !banner.display_anon ) {
						continue;
					}
					if ( !anon && !banner.display_account ) {
						continue;
					}

					// Device
					if ( banner.devices.indexOf( device ) === -1 ) {
						continue;
					}

					// We get here if the campaign targets the user's country,
					// and has at least one banner for the user's logged-in status
					// and device.
					keepCampaign = true;
					break;
				}

				if ( keepCampaign ) {
					availableCampaigns.push( campaign );
				}
			}

			return availableCampaigns;
		},

		updateAvailableCampaigns: function ( previousAvailableCampaigns,
			failedCampaign, fallbackLoopIndex ) {
			// FIXME As well as removing the failed campaign, also update throttling
			// for any other campaigns that were throttled and that may have been up for
			// grabs in the previous fallback loop. (Calculating the correct the
			// probability in that case is what the fallbackLoopIndex parameter is for.)

			var newAvailableCampaigns = previousAvailableCampaigns.slice(),

				// Remove campaign from available campaigns list
				// Find campaign object index by name
				cIndex = newAvailableCampaigns.map( function ( c ) {
					return c.name;
				} ).indexOf( failedCampaign.name );

			// Sanity check: Verify the failed campaign was in the list of available
			// campaigns. (That should always be the case, so this conditional should
			// never be true.)
			if ( cIndex === -1 ) {
				mw.log.warn( 'Failed campaign was not in list of available campaigns' );
			} else {
				newAvailableCampaigns.splice( cIndex, 1 );
			}

			return newAvailableCampaigns;
		},

		/**
		 * Check for campaigns that are have already ended, which might happen due to
		 * incorrect caching of choiceData between us and the user. This check can easily
		 * result in false positives.
		 *
		 * @param choiceData
		 */
		choiceDataSeemsFresh: function ( choiceData ) {

			var i, campaign,
				now = new Date(),
				campaignEndDatePlusLeeway;

			for ( i = 0; i < choiceData.length; i++ ) {
				campaign = choiceData[ i ];
				campaignEndDatePlusLeeway = new Date();

				campaignEndDatePlusLeeway.setTime(
					( campaign.end * 1000 ) +
					( CAMPAIGN_STALENESS_LEEWAY * 60000 )
				);

				if ( campaignEndDatePlusLeeway < now ) {
					return false;
				}
			}

			return true;
		},

		chooseCampaign: function ( availableCampaigns, random ) {

			if ( availableCampaigns.length === 0 ) {
				return null;
			}

			// Calculate the user's probability of getting each campaign. This
			// will set allocation properties on the elements in
			// availableCampaigns.
			setCampaignAllocations( availableCampaigns );

			return chooseObjInAllocatedArray( random, availableCampaigns );
		},

		chooseBanner: function ( campaign, bucket, anon, device, random ) {

			// Make a list of possible banners. Because of our wonky data model,
			// this call must filter on logged-in status and device again.
			var possibleBanners =
				makePossibleBanners( campaign, bucket, anon, device );

			if ( possibleBanners.length === 0 ) {
				return null;
			}

			// Calculate the user's probability of getting each banner. This
			// will set allocation properties on the elements in
			// possibleBanners.
			setBannerAllocations( possibleBanners );

			return chooseObjInAllocatedArray( random, possibleBanners );
		},

		/**
		 * Request a specific banner from among those available for this user
		 *
		 * @param {Object} campaign
		 * @param {number} bucket
		 * @param {boolean} anon
		 * @param {string} device
		 * @param {string} requestedBannerName
		 * @return {Object}
		 */
		requestBanner: function ( campaign, bucket, anon, device, requestedBannerName ) {

			var i, possibleBanner,
				// Make a list of possible banners.
				possibleBanners =
				makePossibleBanners( campaign, bucket, anon, device );

			for ( i = 0; i < possibleBanners.length; i++ ) {

				possibleBanner = possibleBanners[ i ];

				if ( possibleBanner.name === requestedBannerName ) {
					return possibleBanner;
				}
			}

			return null;
		}
	};

}() );
