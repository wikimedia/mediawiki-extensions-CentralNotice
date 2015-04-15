( function ( $, mw ) {

	var bucketValidityFromServer = mw.config.get( 'wgNoticeNumberOfBuckets' )
		+ '.' + mw.config.get( 'wgNoticeNumberOfControllerBuckets' );

	// FIXME Temporary location of this object on the mw hierarchy. See FIXME
	// in bannerController.js.
	mw.cnBannerControllerLib = {

		BUCKET_COOKIE_NAME: 'centralnotice_buckets_by_campaign',

		/**
		 * Minutes leeway for checking stale choice data. Should be the same
		 * as SpecialBannerLoader:CAMPAIGN_STALENESS_LEEWAY.
		 */
		CAMPAIGN_STALENESS_LEEWAY: 15,

		choiceData: null,
		bucketsByCampaign: null,
		possibleBanners: null,

		/**
		 * Set possible campaign and banner choices. Called by
		 * ext.centralNotice.bannerChoices.
		 */
		setChoiceData: function ( choices ) {
			this.choiceData = choices;
		},

		/**
		 * Do all things bucket:
		 *
		 * - Go through choiceData and retrieve or generate buckets for all
		 *   campaigns. If we don't already have a bucket for a campaign, but
		 *   we still have legacy buckets, copy those in. Otherwise choose a
		 *   random bucket. If we did already have a bucket for a campaign,
		 *   check and possibly update its expiry date.
		 *
		 * - Go through all the buckets stored, purging expired buckets.
		 *
		 * - Store the updated bucket data in a cookie.
		 */
		processBuckets: function() {

			var campaign, campaignName, bucket,
				campaignStartDate, retrievedBucketEndDate, bucketEndDate,
				now = new Date(),
				bucketsModified = false,
				val,
				extension = mw.config.get( 'wgCentralNoticePerCampaignBucketExtension' ),
				i;

			this.retrieveBuckets();

			for ( i = 0; i < this.choiceData.length; i++ ) {

				campaign = this.choiceData[i];
				campaignName = campaign.name;
				campaignStartDate = new Date();
				campaignStartDate.setTime( campaign.start * 1000  );

				// Buckets should end the time indicated by extension after
				// the campaign's end
				bucketEndDate = new Date();
				bucketEndDate.setTime( campaign.end * 1000 );
				bucketEndDate.setUTCDate( bucketEndDate.getUTCDate() + extension );

				bucket = this.bucketsByCampaign[campaignName];

				// If we have a valid bucket for this campaign, just check
				// and possibly update its expiry.
				// Note that buckets that are expired but that are found in
				// the cookie (because they didn't have the chance to get
				// purged) are not considered valid. In that case, for
				// consistency, we choose a new random bucket, just as if
				// no bucket had been found.
				if ( bucket && bucketEndDate > now ) {

					retrievedBucketEndDate = new Date();
					retrievedBucketEndDate.setTime( bucket.end * 1000 );

					if ( retrievedBucketEndDate.getTime()
						!== bucketEndDate.getTime() ) {

						bucket.end = bucketEndDate.getTime() / 1000;
						bucketsModified = true;
					}

				} else {

					// First try to get a legacy bucket value. These are only
					// expected to be around for one week after the activation
					// of per-campaign buckets. Doing this eases the transition.
					val = this.retrieveLegacyBucket();

					if ( !val ) {
						// We always use wgNoticeNumberOfControllerBuckets, and
						// not the campaign's number of buckets, to determine
						// how many possible buckets to randomly choose from. If
						// the campaign actually has less buckets than that,
						// the value is mapped down as necessary. This lets
						// campaigns modify the number of buckets they use.
						val = this.getRandomBucket();
					}

					this.bucketsByCampaign[campaignName] = {
						val: val,
						start: campaignStartDate.getTime() / 1000,
						end: bucketEndDate.getTime() / 1000
					};

					bucketsModified = true;
				}
			}

			// Purge any expired buckets
			for ( campaignName in this.bucketsByCampaign ) {

				bucketEndDate = new Date();
				bucketEndDate.setTime( this.bucketsByCampaign[campaignName].end * 1000 );

				if ( bucketEndDate < now ) {
					delete this.bucketsByCampaign[campaignName];
					bucketsModified = true;
				}
			}

			// Store the buckets if there were changes
			if ( bucketsModified ) {
				this.storeBuckets();
			}
		},

		/**
		 * Attempt to get buckets from the bucket cookie, and place them in
		 * bucketsByCampaign. If there is no bucket cookie, set bucketsByCampaign
		 * to an empty object.
		 */
		retrieveBuckets: function() {
			var cookieVal = $.cookie( this.BUCKET_COOKIE_NAME );

			if ( cookieVal ) {
				this.bucketsByCampaign = JSON.parse( cookieVal );
			} else {
				this.bucketsByCampaign = {};
			}
		},

		/**
		 * Store data in bucketsByCampaign in the bucket cookie. The cookie
		 * will be set to expire after the all the buckets it contains
		 * do.
		 */
		storeBuckets: function() {
			var now = new Date(),
				latestDate,
				campaignName, bucketEndDate;

			// Cycle through the buckets to find the latest end date
			latestDate = now;
			for ( campaignName in this.bucketsByCampaign ) {

				bucketEndDate = new Date();
				bucketEndDate.setTime( this.bucketsByCampaign[campaignName].end * 1000 );

				if ( bucketEndDate > latestDate ) {
					latestDate = bucketEndDate;
				}
			}

			latestDate.setDate( latestDate.getDate() + 1 );

			// Store the buckets in the cookie
			$.cookie( this.BUCKET_COOKIE_NAME,
				JSON.stringify( this.bucketsByCampaign ),
				{ expires: latestDate, path: '/' }
			);
		},

		/**
		 * Get a random bucket (integer greater or equal to 0 and less than
		 * wgNoticeNumberOfControllerBuckets).
		 *
		 * @returns int
		 */
		getRandomBucket: function() {
			return Math.floor(
				Math.random() * mw.config.get( 'wgNoticeNumberOfControllerBuckets' )
			);
		},

		/**
		 * Retrieve the user's legacy global bucket from the legacy bucket
		 * cookie. Follow the legacy procedure for determining validity. If a
		 * valid bucket was available, return it, otherwise return null.
		 */
		retrieveLegacyBucket: function() {
			var dataString = $.cookie( 'centralnotice_bucket' ) || '',
				bucket = dataString.split('-')[0],
				validity = dataString.split('-')[1];

			if ( ( bucket === null ) || ( validity !== bucketValidityFromServer ) ) {
				return null;
			}

			return bucket;
		},

		/**
		 * Store the legacy bucket.
		 * Puts the bucket in the legacy global bucket cookie.
		 * If such a cookie already exists, extends its expiry date as
		 * indicated by wgNoticeBucketExpiry.
		 */
		storeLegacyBucket: function( bucket ) {
			$.cookie(
				'centralnotice_bucket',
				bucket + '-' + bucketValidityFromServer,
				{ expires: mw.config.get( 'wgNoticeBucketExpiry' ), path: '/' }
			);
		},

		/**
		 * Filter choiceData on the user's country, logged-in status and device.
		 * Campaigns that don't target the user's country or have no banners for
		 * their logged-in status and device will be removed.
		 *
		 * We also check for campaigns that are have already ended, which might
		 * happen due to incorrect caching of choiceData between us and the user.
		 * If that happens we just toss everything out because one stale campaign
		 * spoils the basket. TODO: Log when this happens.
		 *
		 * We operate on this.choiceData.
		 */
		filterChoiceData: function() {

			var i, campaign, j, banner, keepCampaign,
				filteredChoiceData = [],
				now = new Date(),
				campaignEndDateWLeeway;

			for ( i = 0; i < this.choiceData.length; i++ ) {

				campaign = this.choiceData[i];
				keepCampaign = false;

				// Check choice data freshness
				campaignEndDateWLeeway = new Date();
				campaignEndDateWLeeway.setTime(
					( campaign.end * 1000  ) +
					( this.CAMPAIGN_STALENESS_LEEWAY * 60000 )
				);

				// Quick bow-out if the data is stale
				if ( campaignEndDateWLeeway < now ) {
					this.choiceData = [];
					return;
				}

				// Filter for country if geotargeted
				if ( campaign.geotargeted &&
					( $.inArray(
					mw.centralNotice.data.country, campaign.countries )
					=== -1 ) ) {

					continue;
				}

				// Now filter by banner logged-in status and device.
				// To make buckets work consistently even for strangely
				// configured campaigns, we won't chose buckets yet, so we'll
				// filter on them a little later.
				for ( j = 0; j < campaign.banners.length; j++ ) {
					banner = campaign.banners[j];

					// Logged-in status
					if ( mw.centralNotice.data.anonymous && !banner.display_anon ) {
						continue;
					}
					if ( !mw.centralNotice.data.anonymous && !banner.display_account ) {
						continue;
					}

					// Device
					if ( $.inArray(
						mw.centralNotice.data.device,
						banner.devices ) === -1 ) {
						continue;
					}

					// We get here if the campaign targets the user's country,
					// and has at least one banner for the user's logged-in status
					// and device.
					keepCampaign = true;
				}

				if ( keepCampaign ) {
					filteredChoiceData.push( campaign ) ;
				}
			}

			this.choiceData = filteredChoiceData;
		},

		/**
		 * Filter the choice data on the user's logged-in status, device and
		 * per-campaign buckets (some banners that are not for the user's status
		 * or device may remain following previous filters) and create a flat
		 * list of possible banners to chose from. Add some extra data on to
		 * each banner entry. The result is placed in possibleBanners.
		 *
		 * Note that if the same actual banner is assigned to more than one
		 * campaign it can have more than one entry in that list. That's the
		 * desired result; here "banners" would be more accurately called
		 * "banner assignments".
		 *
		 * The procedure followed here resembles legacy PHP code in
		 * BannerChooser and current PHP code in BannerAllocationCalculator.
		 *
		 * FIXME Re-organize code in all those places to make it easier to
		 * understand.
		 */
		makePossibleBanners: function() {

			var i, campaign, campaignName, j, banner;
			this.possibleBanners = [];

			for ( i = 0; i < this.choiceData.length; i++ ) {
				campaign = this.choiceData[i];
				campaignName = campaign.name;

				for ( j = 0; j < campaign.banners.length; j++ ) {
					banner = campaign.banners[j];

					// Filter for bucket
					if ( this.bucketsByCampaign[campaignName].val %
						campaign.bucket_count !== banner.bucket ) {
						continue;
					}

					// Filter for logged-in status
					if ( mw.centralNotice.data.anonymous && !banner.display_anon ) {
						continue;
					}
					if ( !mw.centralNotice.data.anonymous && !banner.display_account ) {
						continue;
					}

					// Filter for device
					if ( $.inArray(
						mw.centralNotice.data.device, banner.devices ) === -1 ) {
						continue;
					}

					// Add in data about the campaign the banner is part of.
					// This will be used in the calculateBannerAllocations(),
					// the next step in choosing a banner.
					banner.campaignName = campaignName;
					banner.campaignThrottle = campaign.throttle;
					banner.campaignZIndex = campaign.preferred;

					this.possibleBanners.push( banner );
				}
			}
		},

		/**
		 * Calculate the allocation of possible banners (i.e., the required
		 * relative distribution among users that meet the same criteria as
		 * this user). This calculation will be used to randomly select a
		 * banner. This method operates on the values of this.possibleBanners.
		 *
		 * The procedure followed here closely resembles legacy PHP code in
		 * BannerChooser and current PHP code in BannerAllocationCalculator.
		 *
		 * FIXME Re-organize code in all those places to make it easier to
		 * understand.
		 */
		calculateBannerAllocations: function() {

			var campaignTotalWeights = [],
				priorityTotalAllocations = [],
				bannersByPriority = [],
				priorities =  [],
				remainingAllocation = 1,
				i, j,
				banner, campaignName, campaignZIndex, priority,
				bannersAtThisPriority, totalAllocationAtThisPriority, scaling;

			// Calculate the sum of the weight properties for banners in each
			// campaign. This will be used to calculate their proportional
			// weight within each campaign and normalize weights across
			// campaigns.
			for ( i = 0; i < this.possibleBanners.length ; i ++ ) {

				banner = this.possibleBanners[i];
				campaignName = banner.campaignName;

				if ( !campaignTotalWeights[campaignName] ) {
					campaignTotalWeights[campaignName] = 0;
				}

				campaignTotalWeights[campaignName] += banner.weight;
			}

			// Calculate the normalized maximum allocation of each banner
			// within the campaign it's assigned to. First we normalize the
			// banner's weight, then scale down as necessary if the campaign
			// is throttled. This is the maximum allocation because it may
			// be scaled down further in subsequent steps.
			for ( i = 0; i < this.possibleBanners.length ; i ++ ) {
				banner = this.possibleBanners[i];
				banner.maxAllocation =
					( banner.weight / campaignTotalWeights[banner.campaignName] )
					* ( banner.campaignThrottle / 100 );
			}

			// Make an index of banners by priority level, and find the sum of
			// all maximum allocations for each priority level.

			// Note that here we are using a variety of terms for the same thing.
			// Priority level = priority = preferred (DB column) = z-index
			// (as copied in filterChoiceData()). This needs to be fixed, but
			// it's being left as-is for the transition to choosing banners on
			// the client, to make it easier to compare legacy and new code.

			// Note also that the actual values of priority levels are integers,
			// and higher integers represent higher priority. These values are
			// defined by class constants in the CentralNotice PHP class.

			for ( i = 0; i < this.possibleBanners.length ; i ++ ) {

				banner = this.possibleBanners[i];
				campaignZIndex = banner.campaignZIndex;

				// Initialize index vars the first time we hit this priority
				// level/zIndex
				if ( !bannersByPriority[campaignZIndex] ) {
					bannersByPriority[campaignZIndex] = [];
					priorityTotalAllocations[campaignZIndex] = 0;
				}

				bannersByPriority[campaignZIndex].push( banner );
				priorityTotalAllocations[campaignZIndex] += banner.maxAllocation;
			}

			// Dole out chunks of allocation to create the final allocation
			// values. Full allocation is 1 and no allocation is 0; this is
			// tracked by the remainingAllocation variable.

			// First, make an array of priority levels and sort in descending
			// order.
			for ( priority in bannersByPriority ) {
				priorities.push( priority );
			}
			priorities.sort();
			priorities.reverse();

			// Now go through the priority levels from highest to lowest. If
			// campaigns are not throttled, then campaigns with a higher
			// priority level will eclipse all campaigns with lower priority.
			// Only if some campaigns are throttled will they allow some space
			// for campaigns at the next level down.

			// Also note that since priority and throttling are set at the
			// campaign level, there will never be banners from a single
			// campaign at more than one priority level. (This is important for
			// the per-campaign normalizations performed above to be useful
			// here.)

			for ( i = 0; i < priorities.length; i++ ) {

				bannersAtThisPriority = bannersByPriority[priorities[i]];
				totalAllocationAtThisPriority =
					priorityTotalAllocations[priorities[i]];

				// If we fully allocated at a previous level, set allocations
				// at this level to zero. (We check with 0.01 instead of 0 in
				// case of issues due to finite precision.)
				if ( remainingAllocation <= 0.01 ) {
					for ( j = 0; j < bannersAtThisPriority.length; j++ ) {
						bannersAtThisPriority[j].allocation = 0;
					}
					continue;
				}

				// If we are here, there is some allocation remaining.

				// First see if the total allocation for this level is greater
				// than the remaining allocation. This can happen in two
				// circumstances: (1) if there is more than one campaign
				// at this level, and (2) if we are using up some remaining
				// allocation left over from a higher priority level. In both
				// cases, we'll scale the allocation of banners at this level to
				// exactly fill the remaining allocation.
				if ( totalAllocationAtThisPriority > remainingAllocation ) {
					scaling = remainingAllocation / totalAllocationAtThisPriority;
					remainingAllocation = 0;

				} else {
					// If we are here, it means that whatever allocations there
					// are at this level will either not fully take up or will
					// exactly take up the remaining allocation. The former
					// case means some campaigns are throttled, so we don't want
					// to scale up, but rather leave some allocation tidbits for
					// the next level. In the latter case, also, no scaling
					// is needed. So we set scaling to 1, and take the chunk
					// we are due from remainingAllocation.

					scaling = 1;
					remainingAllocation -= totalAllocationAtThisPriority;
				}

				// Set the allocation property of all the banners at this level,
				// scaling the previously set maxAllocation property as required.
				for ( j = 0; j < bannersAtThisPriority.length; j++ ) {
					banner = bannersAtThisPriority[j];
					banner.allocation = banner.maxAllocation * scaling;
				}
			}
		},

		/**
		 * Choose a banner (or choose not to show one) as determined by random
		 * and the allocations in this.possibleBanners. If a banner is chosen,
		 * set the banner's name in mw.centralNotice.data.banner and the
		 * campaign it's associated with in mw.centralNotice.data.campaign. If
		 * no banner is chosen, set mw.centralNotice.data.banner to null.
		 *
		 * @param random float A random number, greater or equal to 0  and less
		 * than 1, to use in choosing a banner.
		 */
		chooseBanner: function( random ) {
			var blockStart = 0,
				i, banner, blockEnd;

			// Cycle through possible banners, calculating which piece of
			// the allocation pie they should get. When random is in the piece,
			// choose the banner. Note that the order of the contents of
			// possibleBanners is not guaranteed to be consistent between
			// requests, but that shouldn't matter.

			for ( i = 0; i < this.possibleBanners.length; i ++ ) {
				banner = this.possibleBanners[i];
				blockEnd = blockStart + banner.allocation;

				if ( ( random >= blockStart ) && ( random < blockEnd ) ) {
					mw.centralNotice.data.banner = banner.name;
					mw.centralNotice.data.campaign = banner.campaignName;
					return;
				}

				blockStart = blockEnd;
			}

			// We get here if there is less than full allocation (including no
			// allocation) and random points to the unallocated chunk.
			mw.centralNotice.data.banner = null;
		}
	};

} )( jQuery, mediaWiki );