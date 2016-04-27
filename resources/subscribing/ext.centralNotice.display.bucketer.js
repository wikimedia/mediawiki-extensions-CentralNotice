/**
 * Storage, retrieval and other processing of buckets. Provides
 * cn.internal.bucketer.
 *
 * Bucket assignments are stored using the kvStore, in LocalStorage or a cookie.
 * To maximize concision for users that fall back to cookies, the value consists
 * of '*'-separated campaigns, each of which is made up of '!'-separated fields.
 * The format is:
 *
 *  NAME!START!END!VALUE[*NAME!START!END!VALUE..]
 *
 * - Start is stored as a second offset from UNIX timestamp 1400000000
 *   (March 2014).
 * - End is stored as second offset from start.
 *
 * For example:
 *
 * 'WikiConference_USA!39942400!3729600!2'
 *
 * ...would be deserialized to:
 *
 * { WikiConference_USA: { start: 1439942400, end: 1443672000, val: 2 } }
 *
 */
( function ( $, mw ) {

	// Bucket objects by campaign; properties are campaign names.
	// Retrieved from kvStore (which uses LocalStorage or a fallback cookie)
	// or from a legacy cookie.
	var buckets = null,

		// The campaign we're working with.
		campaign = null,

		kvStore = mw.centralNotice.kvStore,
		multiStorageOption,

		// Name of the legacy cookie for CentralNotice buckets. Its value is
		// a compact serialization of buckets in the the same format as is
		// currently used here.
		LEGACY_COOKIE = 'CN',

		STORAGE_KEY = 'buckets';

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

	function parseSerializedBuckets( serialized ) {

		var parsedBuckets = {};

		$.each( serialized.split( '*' ), function ( idx, strBucket ) {
			var parts = strBucket.split( '!' ),
				key = decodeCampaignName( parts[0] ),
				start = parseInt( parts[1], 10 ) + 14e8,
				end = start + parseInt( parts[2], 10 ),
				val = parseInt( parts[3], 10 );

			if ( key && start && end && !isNaN( val ) ) {
				parsedBuckets[ key ] = {
					start: start,
					end: end,
					val: val
				};
			}
		} );

		return parsedBuckets;
	}

	/**
	 * Check legacy bucket cookie, and try to migrate. If a legacy cookie is
	 * found, load buckets from there.
	 *
	 * @returns {boolean} true if a legacy cookie was migrated, false if not
	 */
	function possiblyLoadAndMigrateLegacyBuckets() {

		var cookieVal = $.cookie( LEGACY_COOKIE );

		if ( cookieVal ) {

			// We need to deserialize and store again to determine ttl
			buckets = parseSerializedBuckets( cookieVal );
			storeBuckets();
			$.removeCookie( LEGACY_COOKIE, { path: '/' } );
			return true;
		}

		return false;
	}

	/**
	 * Attempt to get buckets from the storage. If no stored buckets are
	 * found, set buckets to an empty object.
	 */
	function loadBuckets() {

		var val = kvStore.getItem(
			STORAGE_KEY,
			kvStore.contexts.GLOBAL,
			multiStorageOption
		);

		buckets = ( val ? parseSerializedBuckets( val ) : {} );
	}

	/**
	 * Store buckets using the kvStore. The storage item will be set to
	 * expire after the all the buckets it contains do.
	 *
	 * Though compact serialization is no longer needed for the majority
	 * of users, who'll get LocalStorage, it's useful for those who fall
	 * back to cookies, and it seems preferable to keep things consistent.
	 */
	function storeBuckets() {
		var expires = Math.ceil( ( new Date() ) / 1000 ),
			serialized = $.map( buckets, function ( opts, key ) {
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

		kvStore.setItem(
			STORAGE_KEY,
			serialized,
			kvStore.contexts.GLOBAL,
			// Convert expires to ttl in days
			Math.ceil( ( expires - ( new Date() ) / 1000 ) / 86400 ),
			multiStorageOption
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
	 * - Get buckets from the kvStore or legacy cookie, if available.
	 * - If necessary, generate a random bucket for the campaign.
	 * - Ensure the stored end date for this campaign is up-to-date.
	 * - Go through all the buckets, purging expired buckets.
	 * - Store the updated bucket data using the kvStore.
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

		// Check if and how we can store buckets. Allow cookie fallback in all
		// cases in which localStorage isn't available.

		// If we have no storage options (cookies and localStorage disabled),
		// loading and storing buckets will be no-ops, and a new bucket will be
		// chosen every time.
		multiStorageOption = kvStore.getMultiStorageOption( true );

		// In all cases, check for a legacy cookie and try to migrate if one
		// was found. Otherwise, load normally.
		if ( !possiblyLoadAndMigrateLegacyBuckets() ) {
			loadBuckets();
		}

		bucket = buckets[campaignName];

		// If we have a valid bucket, just check and possibly update its
		// expiry.

		// Note that buckets that are expired but that were retrieved from
		// storage (because they didn't have the chance to get purged) are
		// not considered valid. In that case, for consistency, we choose a
		// new random bucket, just as if no bucket had been found.

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
		 * @returns {Number}
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
		 * @param {Number} val The numeric bucket value to store
		 */
		setBucket: function( val ) {
			buckets[campaign.name].val = val;
			storeBuckets();
		}
	};
} )( jQuery, mediaWiki );
