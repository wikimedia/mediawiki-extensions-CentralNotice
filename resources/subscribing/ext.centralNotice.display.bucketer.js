/**
 * Storage, retrieval and other processing of buckets. Provides
 * cn.internal.bucketer.
 */
( function ( $, mw ) {

	var BUCKET_COOKIE_NAME = 'centralnotice_buckets_by_campaign',

		// Bucket objects by campaign; properties are campaign names.
		// Retrieved from bucket cookie, if available.
		buckets,

		// The campaign we're working with.
		campaign = null;

	/**
	 * Attempt to get buckets from the bucket cookie. If there is no
	 * bucket cookie, set buckets to an empty object.
	 */
	function loadBuckets() {
		var cookieVal = $.cookie( BUCKET_COOKIE_NAME );

		if ( cookieVal ) {
			try {
				buckets = JSON.parse( cookieVal );
			} catch ( e ) {
				// Very likely a syntax error due to corrupt cookie contents
				buckets = {};
			}
		} else {
			buckets = {};
		}
	}

	/**
	 * Store buckets in the bucket cookie. The cookie will be set to expire
	 * after the all the buckets it contains do.
	 */
	function storeBuckets() {
		var now = new Date(),
			latestDate,
			campaignName, bucketEndDate;

		// Cycle through the buckets to find the latest end date
		latestDate = now;
		for ( campaignName in buckets ) {

			bucketEndDate = new Date();
			bucketEndDate.setTime( buckets[campaignName].end * 1000 );

			if ( bucketEndDate > latestDate ) {
				latestDate = bucketEndDate;
			}
		}

		latestDate.setDate( latestDate.getDate() + 1 );

		// Store the buckets in the cookie
		$.cookie( BUCKET_COOKIE_NAME,
			JSON.stringify( buckets ),
			{ expires: latestDate, path: '/' }
		);
	}

	/**
	 * Get a random bucket (integer greater or equal to 0 and less than
	 * wgNoticeNumberOfControllerBuckets).
	 *
	 * @returns int
	 */
	function getRandomBucket() {
		return Math.floor(
			Math.random() * mw.config.get( 'wgNoticeNumberOfControllerBuckets' )
		);
	}

	/**
	 * Do all things bucket:
	 * - Get buckets from the cookie, if available.
	 * - If necessary, generate a random bucket for the campaign.
	 * - Ensure the stored end date for this campaign is up-to-date.
	 * - Go through all the buckets, purging expired buckets.
	 * - Store the updated bucket data in the cookie.
	 *
	 * This should be called before a bucket is requested but after
	 * setCampaign() has been called.
	 */
	function retrieveProcessAndGet() {

		var campaignName = campaign.name,
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

		loadBuckets();
		bucket = buckets[campaignName];

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

			if ( retrievedBucketEndDate.getTime() !== bucketEndDate.getTime() ) {
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
			val = getRandomBucket();

			bucket = {
				val: val,
				start: campaignStartDate.getTime() / 1000,
				end: bucketEndDate.getTime() / 1000
			};

			buckets[campaignName] = bucket;
			bucketsModified = true;
		}

		// Purge any expired buckets
		for ( campaignName in buckets ) {

			bucketEndDate = new Date();
			bucketEndDate.setTime( buckets[campaignName].end * 1000 );

			if ( bucketEndDate < now ) {
				delete buckets[campaignName];
				bucketsModified = true;
			}
		}

		// Store the buckets if there were changes
		if ( bucketsModified ) {
			storeBuckets();
		}
	}

	/**
	 * Bucketer object (intended for access from within this RL module).
	 */
	mw.centralNotice.internal.bucketer = {

		/**
		 * @param {Object} c A campaign object. Note: we don't check that the
		 * object is valid.
		 */
		setCampaign: function ( c ) {
			campaign = c;
		},

		/**
		 * This should only be called once. setCampaign() must have been called
		 * first.
		 */
		process: function() {
			retrieveProcessAndGet();
		},

		/**
		 * Get the bucket for this user for the campaign sent in setCampaign().
		 * setCampaign() and process() must have been called first. (Normally
		 * they're called by mw.centralNotice.chooseAndMaybeDisplay(). Calls
		 * to this method from mixin hooks don't have to worry about this.)
		 */
		getBucket: function() {
			return buckets[campaign.name].val;
		},

		/**
		 * Store this bucket value for this user for the campaign sent in
		 * setCampaign().
		 * setCampaign() and process() must have been called first. (Normally
		 * they're called by mw.centralNotice.chooseAndMaybeDisplay(). Calls
		 * to this method from mixin hooks don't have to worry about this.)
		 */
		setBucket: function( val ) {
			buckets[campaign.name].val = val;
			storeBuckets();
		}
	};

} )( jQuery, mediaWiki );