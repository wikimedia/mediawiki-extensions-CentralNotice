/**
 * Storage, retrieval and other processing of buckets. Provides
 * cn.internal.bucketer.
 *
 * Bucket assignments are stored in a cookie named simply 'CN', to maximize
 * concision. It consists of '*'-separated campaigns, each of which is made up
 * for '!'-separated fields. The format is:
 *
 *  NAME!START!END!VALUE[*NAME!START!END!VALUE..]
 *
 * - Start is stored as a second offset from UNIX timestamp 1400000000
 *   (March 2014).
 * - End is stored as second offset from start.
 *
 * For example:
 *
 *  CN=WikiConference_USA!39942400!3729600
 *
 * ...would be deserialized to:
 *
 * { WikiConference_USA: { start: 1439942400, end: 1443672000 } }
 *
 */
( function ( $, mw ) {

		// Name of the old (pre-I2b39d153b) cookie for CentralNotice buckets.
		// Its value is a JSON-encoded object, mapping campaign names to plain
		// objects with 'start', 'end', and 'val' parameters.
	var LEGACY_COOKIE = 'centralnotice_buckets_by_campaign',

		// Bucket objects by campaign; properties are campaign names.
		// Retrieved from bucket cookie, if available.
		buckets = null,

		// The campaign we're working with.
		campaign = null;

	/**
	 * Escape '*' and '!' in a campaign name to make it safe for serialization.
	 */
	function escapeCampaignName( name ) {
		return name.replace( /[*!]/g, function ( match ) {
			return '&#' + match.charCodeAt( 0 );
		} );
	}

	/**
	 * Decode any escaped '*' and '!' characters in a serialized campaign name.
	 */
	function decodeCampaignName( name ) {
		return name.replace( /&#(33|42)/, function ( match, $1 ) {
			return String.fromCharCode( $1 );
		} );
	}

	/**
	 * Attempt to get buckets from the bucket cookie. If there is no
	 * bucket cookie, check for a 'legacy cookie' (i.e., a cookie with
	 * the name and format used prior to I2b39d153b); if there is one,
	 * migrate it to the newer cookie format. If neither cookie exists,
	 * set buckets to an empty object.
	 */
	function loadBuckets() {
		var cookieVal = $.cookie( 'CN' );

		buckets = {};

		if ( !cookieVal ) {
			// Prior to I2b39d153be, the campaign cookie had a different
			// (longer) name and used JSON encoding. If the user has such
			// a cookie, migrate it to the new format.
			cookieVal = $.cookie( LEGACY_COOKIE );
			if ( cookieVal ) {
				$.removeCookie( LEGACY_COOKIE, { path: '/' } );
				try {
					$.extend( buckets, JSON.parse( cookieVal ) );
				} catch ( e ) {}
				if ( !$.isEmptyObject( buckets ) ) {
					storeBuckets();
				}
			}
			return;
		}

		$.each( cookieVal.split( '*' ), function ( idx, strBucket ) {
			var parts = strBucket.split( '!' ),
				key = decodeCampaignName( parts[0] ),
				start = parseInt( parts[1], 10 ) + 14e8,
				end = start + parseInt( parts[2], 10 ),
				val = parts[3];

			if ( key && start && end && val !== undefined ) {
				buckets[ key ] = {
					start: start,
					end: end,
					val: val
				};
			}
		} );
	}

	/**
	 * Store buckets in the bucket cookie. The cookie will be set to expire
	 * after the all the buckets it contains do.
	 */
	function storeBuckets() {
		var expires = Math.ceil( ( new Date() ) / 1000 ),
			cookieVal = $.map( buckets, function ( opts, key ) {
				var parts = [
					escapeCampaignName( key ),
					Math.floor( opts.start - 14e8 ),
					Math.ceil( opts.end - opts.start ),
					opts.val
				];

				if ( opts.end > expires ) {
					expires = Math.ceil( opts.end );
				}

				return parts.join( '!' );
			} ).join( '*' );

		// Store the buckets in the cookie
		$.cookie( 'CN', cookieVal, {
			expires: new Date( expires * 1000 ),
			path: '/',
		} );
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
