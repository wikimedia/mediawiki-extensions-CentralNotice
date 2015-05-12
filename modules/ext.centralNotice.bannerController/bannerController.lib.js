( function ( $, mw ) {

	var Mixin, KVStorageContext,
		KV_STORAGE_CN_PREFIX = 'CentralNotice',
		KV_STORAGE_PREFIX_SEPARATOR = '|',
		KV_STORAGE_ERROR_COOKIE_NAME = 'centralnotice_kv_storage_errors';

	/**
	 * Class for campaign-associated mixins. Access via mw.cnBannerControllerLib.Mixin.
	 */
	Mixin = function( name ) {
		this.name = name;
	};

	Mixin.prototype.setPreBannerHandler = function( handlerFunc ) {
		this.preBannerHandler = handlerFunc;
	};

	Mixin.prototype.setPostBannerHandler = function( handlerFunc ) {
		this.postBannerHandler = handlerFunc;
	};

	/**
	 * A context for key-value storage.
	 *
	 * @class
	 * @param {string} key A unique string to identify this context. Must not
	 * contain KV_STORAGE_PREFIX_SEPARATOR.
	 */
	KVStorageContext = function( key ) {
		this.key = key;
	};

	/**
	 * Log a problem with key-value storage to the cookie and mw.log.
	 *
	 * The reason we log in a cookie is so that we can identify later which
	 * clients may have goofy KVStorage data.
	 *
	 * @param {string} message A message about the error
	 * @param {string} key
	 * @param {*} value
	 * @param {KVStorageContext} context
	 */
	function logKVStorageError( message, key, value, context ) {

		// Try to get an existing cookie; if there is one, we'll append this
		// error to it.
		var errCookieRaw = $.cookie( KV_STORAGE_ERROR_COOKIE_NAME ),
			errCookieVal,
			err = {
				message: message,
				key: key,
				// Limit the length of the value to store
				value: JSON.stringify( value ).substring( 0, 50 ),
				context: context.key,
				time: Math.round( ( new Date() ).getTime() / 1000 )
			};

		// If we didn't get a cookie value, or if it wasn't an array, set it to
		// an empty array.
		if ( !errCookieRaw ) {
			errCookieVal = [];
		} else {
			errCookieVal = JSON.parse( errCookieRaw );
			if ( !Array.isArray( errCookieVal ) ) {
				errCookieVal = [];
			}
		}

		// If a campaign and/or a banner have been chosen, include their names in
		// the error
		err.campaign = mw.centralNotice.data.campaign || null;
		err.banner = mw.centralNotice.data.banner || null;

		errCookieVal.push( err );

		// Don't let the cookie hold toooooooooo many errors
		if ( errCookieVal.length > 5 ) {
			errCookieVal.shift();
		}

		// Store in the cookie; 2 years expiry should be sufficient
		$.cookie( KV_STORAGE_ERROR_COOKIE_NAME,
			JSON.stringify( errCookieVal ),
			{ expires: 730, path: '/' } );

		mw.log( 'CentralNotice KV storage error: ' + JSON.stringify( err ) );
	}

	/**
	 * Return the actual key to be used in localStorage for the given key and
	 * context.
	 *
	 * The key returned should be unique among all localStorage keys used by
	 * this site. It includes unique strings for centralNotice and context, and
	 * may also include the campaign name or category.
	 *
	 * Note: when using CAMPAIGN and CATEGORY contexts, ensure that there are
	 * values in mw.centralNotice.data.campaign and
	 * mw.centralNotice.data.category, respectively. We don't check them here.
	 *
	 * @param {string} key
	 * @param {*} value
	 * @param {KVStorageContext} context
	 * @return {string}
	 */
	function makeKVStorageKeyForLocalStorage( key, context ) {
		var storageContexts = mw.cnBannerControllerLib.KVStorageContexts,
			base = KV_STORAGE_CN_PREFIX + KV_STORAGE_PREFIX_SEPARATOR +
				context.key + KV_STORAGE_PREFIX_SEPARATOR;

		switch ( context.key ) {
			case storageContexts.CAMPAIGN.key:
				return base +
					mw.centralNotice.data.campaign + KV_STORAGE_PREFIX_SEPARATOR +
					key;

			case storageContexts.CATEGORY.key:
				return base +
					mw.centralNotice.data.category + KV_STORAGE_PREFIX_SEPARATOR +
					key;

			case storageContexts.GLOBAL.key:
				return base + key;

			default:
				logKVStorageError( 'Invalid KV storage context', key, null, context );
				return base +
					'invalidContext' + KV_STORAGE_PREFIX_SEPARATOR +
					key;
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
	 * @param array allocatedArray
	 * @param random float A random number, greater or equal to 0  and less
	 *   than 1, to use in choosing an object.
	 */
	function chooseObjInAllocatedArray( random, allocatedArray ) {
		var blockStart = 0,
			i, obj, blockEnd;

		// Cycle through objects, calculating which piece of
		// the allocation pie they should get. When random is in the piece,
		// choose the object.

		for ( i = 0; i < allocatedArray.length; i ++ ) {
			obj = allocatedArray[i];
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

	// FIXME Temporary location of this object on the mw hierarchy. See FIXME
	// in bannerController.js.
	mw.cnBannerControllerLib = {

		BUCKET_COOKIE_NAME: 'centralnotice_buckets_by_campaign',

		/**
		 * Minutes leeway for checking stale choice data. Should be the same
		 * as SpecialBannerLoader:CAMPAIGN_STALENESS_LEEWAY.
		 */
		CAMPAIGN_STALENESS_LEEWAY: 15,

		/**
		 * Available key-value storage contexts
		 * @enum
		 * @readonly
		 */
		KVStorageContexts: {
			CAMPAIGN: new KVStorageContext( 'campaign' ),
			CATEGORY: new KVStorageContext( 'category' ),
			GLOBAL: new KVStorageContext( 'global' )
		},

		campaignMixins: {},
		choiceData: null,
		/**
		 * Once a campaign is chosen, this will receive a copy of the data for
		 * that campaign. (This is different from mw.centralNotice.data.campaign,
		 * which will hold just the name.)
		 */
		campaign: null,
		bucketsByCampaign: null,
		possibleBanners: null,
		/**
		 * Base class for campaign-associated mixins (defined above).
		 */
		Mixin: Mixin,

		/**
		 * Register a campaign-associated mixin to make it available for campaigns
		 * to use it. Should be called for every campaign-associated mixin.
		 * @param mixin mw.cnBannerControllerLib.Mixin
		 */
		registerCampaginMixin: function( mixin ) {
			this.campaignMixins[mixin.name] = mixin;
		},

		/**
		 * Run pre-banner hook handlers registered by mixins for the selected
		 * campaign. Campaign data (as provided by choiceData) should already
		 * be set in this.campaign by campaign this.chooseCampaign(). Note that
		 * we don't test for a null campaign (a possibility if there are empty
		 * allocation blocks).
		 */
		runMixinsPreBannerHooks: function() {
			var self = this;
			$.each( this.campaign.mixins, function( mixinName, mixinParams ) {
				var handler = self.campaignMixins[mixinName].preBannerHandler;
				if ( typeof handler === 'function' ) {
					handler( mixinParams );
				}
			} );
		},

		/**
		 * Does this browser support our KV storage mechanism?
		 */
		isKVStorageAvailable: function() {
			return ( typeof window.localStorage === 'object' );
		},

		/**
		 * Set the given value for the given key in the given context, using
		 * localStorage. If the key already exists, its value will be
		 * overwritten. Fails if localStorage is not available.
		 *
		 * Value can be any type; will be json-encoded.
		 *
		 * If the value was set, return true; if the value could not be set, we
		 * log the error to mw.log and a cookie, and return false.
		 *
		 * Note: check isKVStorageAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {*} value
		 * @param {KVStorageContext} context
		 * @return {boolean}
		 */
		setKVStorageItem: function( key, value, context ) {

			var lsKey, encodedValue;

			// Check validity of key
			if ( key.indexOf( KV_STORAGE_PREFIX_SEPARATOR ) !== -1 ) {
				logKVStorageError( 'Invalid key', key, value, context );
				return false;
			}

			lsKey = makeKVStorageKeyForLocalStorage( key, context );
			encodedValue = JSON.stringify( value );

			// Write the value
			localStorage.setItem( lsKey, encodedValue );

			// Check that it was written (it might not have been, if we're over
			// the localStorage quota for this site, for example)
			if ( localStorage.getItem( lsKey ) !== encodedValue ) {
				logKVStorageError( 'Couldn\'t write value', key, value, context );
				return false;
			}

			return true;
		},

		/**
		 * Get the stored value for the given key in the given context.
		 *
		 * Note: check isKVStorageAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 */
		getKVStorageItem: function ( key, context ) {
			var lsKey = makeKVStorageKeyForLocalStorage( key, context ),
				rawValue, value;

			try {
				rawValue = localStorage.getItem( lsKey );
				value = JSON.parse( rawValue );

			} catch ( e ) {

				// If the JSON couldn't be parsed, log and return null (which is
				// the same value we'd get if the key were not set).
				if ( e instanceof SyntaxError ) {

					logKVStorageError(
						'Couldn\'t parse value, removing. ' + e.message,
						key, rawValue, context );

					localStorage.removeItem( lsKey );
					return null;

				// For any other errors, log and re-throw
				} else {
					logKVStorageError(
						'Couldn\'t read value ' + e.message,
						key, rawValue, context );

					throw e;
				}
			}

			return value;
		},

		/**
		 * Remove the stored value for the given key in the given context
		 *
		 * Note: check isKVStorageAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 */
		removeKVStorageItem: function ( key, context ) {
			var lsKey = makeKVStorageKeyForLocalStorage( key, context );
			localStorage.removeItem( lsKey );
		},

		/**
		 * Set possible campaign and banner choices. Called by
		 * ext.centralNotice.bannerChoices.
		 */
		setChoiceData: function( choiceData ) {
			this.choiceData = choiceData;
		},

		/**
		 * Has choiceData been set?
		 */
		isChoiceDataSet: function() {
			return this.choiceData !== null;
		},

		/**
		 * Are there any possible campaigns to choose from?
		 * Note: ensure there is choice data by calling isChoiceDataSet() first.
		 */
		choicesAvailable: function() {
			return this.choiceData.length > 0;
		},

		/**
		 * Has a campaign been chosen?
		 */
		isAnyCampaignChosen: function() {
			return this.campaign !== null;
		},

		/**
		 * Are one or more banners available?
		 * Note: call makePossibleBanners first.
		 */
		bannersAvailable: function() {
			return this.possibleBanners.length > 0;
		},

		/**
		 * For targeted users (users meeting the same logged-in status, country,
		 * and language criteria as this user) calculate the probability that
		 * of receiving each campaign in this.choiceData. This takes into account
		 * campaign priority and throttling. The equivalent server-side method
		 * is AllocationCalculator::calculateCampaignAllocations().
		 */
		calculateCampaignAllocations: function() {

			var i, campaign, campaignPriority,
				campaignsByPriority = [],
				priorities = [],
				priority, campaignsAtThisPriority,
				remainingAllocation = 1,
				j, campaignsAtThisPriorityCount, currentFullAllocation,
				actualAllocation;

			// Optimize for the common scenario of a single campaign
			if ( this.choiceData.length === 1 ) {
				 this.choiceData[0].allocation = this.choiceData[0].throttle / 100;
				 return;
			}

			// Make an index of campaigns by priority level.
			// Note that the actual values of priority levels are integers,
			// and higher integers represent higher priority. These values are
			// defined by class constants in the CentralNotice PHP class.

			for ( i = 0; i < this.choiceData.length ; i ++ ) {

				campaign = this.choiceData[i];
				campaignPriority = campaign.preferred;

				// Initialize index the first time we hit this priority
				if ( !campaignsByPriority[campaignPriority] ) {
					campaignsByPriority[campaignPriority] = [];
				}

				campaignsByPriority[campaignPriority].push( campaign );
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

				campaignsAtThisPriority = campaignsByPriority[priorities[i]];

				// If we fully allocated at a previous level, set allocations
				// at this level to zero. (We check with 0.01 instead of 0 in
				// case of issues due to finite precision.)
				if ( remainingAllocation < 0.01 ) {
					for ( j = 0; j < campaignsAtThisPriority.length; j++ ) {
						campaignsAtThisPriority[j].allocation = 0;
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

					campaign = campaignsAtThisPriority[j];

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
		},

		/**
		 * Choose a campaign (or no campaign) as determined by random and the
		 * allocations in this.choiceData.
		 *
		 * @param random float A random number, greater or equal to 0  and less
		 *   than 1, to use in choosing a campaign.
		 */
		chooseCampaign: function( random ) {
			this.setCampaign(
				chooseObjInAllocatedArray( random, this.choiceData ) );
		},

		/**
		 * Set the campaign. Set this.campaign to the object received and set
		 * mw.centralnotice.data.campaign to the campaign name, or both to
		 * null if campaign is null.
		 */
		setCampaign: function( campaign ) {
			this.campaign = campaign;
			mw.centralNotice.data.campaign = campaign ? campaign.name : null;
		},

		/**
		 * Do all things bucket:
		 * - Retrieve or generate a random bucket for the campaign in
		 *   this.campaign.
		 * - Store the updated bucket data in a cookie.
		 * - Go through all the buckets stored, purging expired buckets.
		 */
		processBuckets: function() {

			var campaign = this.campaign,
				campaignName = campaign.name,
				campaignStartDate,
				bucket, bucketEndDate, retrievedBucketEndDate, val,
				extension = mw.config.get( 'wgCentralNoticePerCampaignBucketExtension' ),
				now = new Date(),
				bucketsModified = false;

			campaignStartDate = new Date();
			campaignStartDate.setTime( campaign.start * 1000  );

			// Buckets should end the time indicated by extension after
			// the campaign's end
			bucketEndDate = new Date();
			bucketEndDate.setTime( campaign.end * 1000 );
			bucketEndDate.setUTCDate( bucketEndDate.getUTCDate() + extension );

			this.retrieveBuckets();
			bucket = this.bucketsByCampaign[campaignName];

			// If we have a valid bucket, just check and possibly update its
			// expiry.

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

				// We always use wgNoticeNumberOfControllerBuckets, and
				// not the campaign's number of buckets, to determine
				// how many possible buckets to randomly choose from. If
				// the campaign actually has less buckets than that,
				// the value is mapped down as necessary. This lets
				// campaigns modify the number of buckets they use.
				val = this.getRandomBucket();

				this.bucketsByCampaign[campaignName] = {
					val: val,
					start: campaignStartDate.getTime() / 1000,
					end: bucketEndDate.getTime() / 1000
				};

				bucketsModified = true;
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
		 * Filter choiceData on the user's country, logged-in status and device.
		 * Campaigns that don't target the user's country or have no banners for
		 * their logged-in status and device will be removed.
		 *
		 * The server-side equivalent of this method is
		 * AllocationCalculator::filterChoiceData().
		 *
		 * We also check for campaigns that are have already ended, which might
		 * happen due to incorrect caching of choiceData between us and the user.
		 * If that happens we just toss everything out because one stale campaign
		 * spoils the basket. (This freshness check is not performed in the
		 * server-side method.) TODO: Log when this happens.
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
					break;
				}

				if ( keepCampaign ) {
					filteredChoiceData.push( campaign ) ;
				}
			}

			this.choiceData = filteredChoiceData;
		},

		/**
		 * Filter banners for this.campaign on the user's logged-in status,
		 * device and bucket (some banners that are not for the user's status
		 * or device may remain following previous filters) and create a list
		 * of possible banners to chose from. The result is placed in
		 * this.possibleBanners.
		 *
		 * The equivalent server-side method
		 * AllocationCalculator::makePossibleBanners().
		 */
		makePossibleBanners: function() {

			var i, campaign, campaignName, banner;
			this.possibleBanners = [];

			campaign = this.campaign;
			campaignName = campaign.name;

			for ( i = 0; i < campaign.banners.length; i++ ) {
				banner = campaign.banners[i];

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

				this.possibleBanners.push( banner );
			}
		},

		/**
		 * Calculate the allocation of banners in a single campaign, based on
		 * relative weights. Operations on this.possibleBanners. The equivalent
		 * server-side method is
		 * AllocationCalculator::calculateBannerAllocations().
		 */
		calculateBannerAllocations: function() {
			var i, banner,
				totalWeights = 0;

			// Optimize for just one banner available for the user in this
			// campaign, by far our most common scenario.
			if ( mw.cnBannerControllerLib.possibleBanners.length === 1 ) {
				mw.cnBannerControllerLib.possibleBanners[0].allocation = 1;
				return;
			}

			// Find the sum of all banner weights
			for ( i = 0; i < this.possibleBanners.length; i++ ) {
				totalWeights += this.possibleBanners[i].weight;
			}

			// Set allocation property to the normalized weight
			for ( i = 0; i < this.possibleBanners.length; i++ ) {
				banner = this.possibleBanners[i];
				banner.allocation = banner.weight / totalWeights;
			}
		},

		/**
		 * Choose a banner as determined by random and the allocations in
		 * this.possibleBanners. Set the banner's name in
		 * mw.centralNotice.data.banner.
		 *
		 * @param random float A random number, greater or equal to 0  and less
		 *   than 1, to use in choosing a banner.
		 */
		chooseBanner: function( random ) {
			// Since we never have an allocation gap for banners, this should
			// always work.
			this.setBanner(
				chooseObjInAllocatedArray( random, this.possibleBanners ) );
		},

		/**
		 * Set the banner (should never be null).
		 */
		setBanner: function( banner ) {
			mw.centralNotice.data.banner = banner.name;
		}
	};

} )( jQuery, mediaWiki );