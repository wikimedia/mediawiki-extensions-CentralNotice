( function ( $, mw ) {

	// FIXME Temporary location of this object on the mw hierarchy. See FIXME
	// in bannerController.js.
	mw.cnBannerControllerLib = {

		/**
		 * Set possible campaign and banner choices. Called by
		 * ext.centralNotice.bannerChoices.
		 */
		setChoiceData: function ( choices ) {
			this.choiceData = choices;
		},

		choiceData: null,

		possibleBanners: null,

		/**
		 * Filter the choice data and create a flat list of possible banners
		 * to chose from. Add some additional data on to each banner entry. The
		 * result is placed in possibleBanners.
		 *
		 * Note that if the same actual banner is assigned to more than one
		 * campaign it can have more than one entry in that list. That's the
		 * desired result; here "banners" would be more accurately called
		 * "banner assignments".
		 *
		 * The procedure followed here closely resembles legacy PHP code in
		 * BannerChooser and current PHP code in BannerAllocationCalculator.
		 *
		 * FIXME Re-organize code in all those places to make it easier to
		 * understand.
		 */
		filterChoiceData: function() {

			var i, campaign, j, banner;
			this.possibleBanners = [];

			for ( i = 0; i < this.choiceData.length; i++ ) {
				campaign = this.choiceData[i];

				// Filter for country if geotargetted
				if ( campaign.geotargetted &&
					( $.inArray(
					mw.centralNotice.data.country, campaign.countries )
					=== -1 ) ) {

					continue;
				}

				// Now filter by banner properties
				for ( j = 0; j < campaign.banners.length; j++ ) {
					banner = campaign.banners[j];

					// Device
					if ( $.inArray(
						mw.centralNotice.data.device, banner.devices ) === -1 ) {
						continue;
					}

					// Bucket
					if ( parseInt( mw.centralNotice.data.bucket, 10) %
						campaign.bucket_count !== banner.bucket ) {
						continue;
					}

					// Add in data about the campaign the banner is part of.
					// This will be used in the calculateBannerAllocations(),
					// the next step in choosing a banner.
					banner.campaignName = campaign.name;
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
			// allocation).
			mw.centralNotice.data.banner = null;
		}
	};

} )( jQuery, mediaWiki );