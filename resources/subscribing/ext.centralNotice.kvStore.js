/**
 * Module for key-value storage in localStorage, use by CentralNotice campaign
 * mixins and in-banner JS.
 *
 * This module is made available at mw.centralNotice.kvStore.
 */
( function ( $, mw ) {

	var KVStorageContext,
		kvStore,
		error = null,
		campaignName = null,
		bannerName = null,
		category = null,
		now = Math.round( ( new Date() ).getTime() / 1000 ),

		SEPARATOR = '|',
		FIND_KEY_REGEX = /\|([^|]*)$/,

		// Prefix for all localStorage keys. Must correspond with PREFIX_REGEX
		// in ext.centralNotice.kvStoreMaintenance.js
		PREFIX = 'CentralNoticeKV',

		// TTL of KV store items is 1/2 year, in seconds
		DEFAULT_ITEM_TTL = 15768000;

	/**
	 * A context for key-value storage.
	 *
	 * @class
	 * @param {string} key A unique string to identify this context. Must not
	 * contain SEPARATOR.
	 */
	KVStorageContext = function( key ) {
		this.key = key;
	};

	/**
	 * Flag that a problem with key-value storage occurred, and log via mw.log.
	 *
	 * @param {string} message A message about the error
	 * @param {string} key
	 * @param {*} value
	 * @param {KVStorageContext} context
	 */
	function setError( message, key, value, context ) {

		error = {
			message: message,
			key: key,
			value: value,
			context: context ? context.key : null,
			time: new Date()
		};

		// If a campaign and/or a banner name have been set, include their names
		// in the error
		error.campaign = campaignName;
		error.banner = bannerName;

		mw.log( 'CentralNotice KV storage error: ' + JSON.stringify( error ) );
	}

	/**
	 * Return the actual key to be used in localStorage for the given key and
	 * context.
	 *
	 * The key returned should be unique among all localStorage keys used by
	 * this site. It includes unique strings for centralNotice and context, and
	 * may also include the campaign name or category.
	 *
	 * Note: when using CAMPAIGN and CATEGORY contexts, ensure that you have set
	 * campaign and category, respectively. We don't check them here.
	 *
	 * @param {string} key
	 * @param {*} value
	 * @param {KVStorageContext} context
	 * @return {string}
	 */
	function makeKeyForLocalStorage( key, context ) {
		var base = PREFIX + SEPARATOR + context.key + SEPARATOR;

		switch ( context.key ) {
			case kvStore.contexts.CAMPAIGN.key:
				return base + campaignName + SEPARATOR + key;

			case kvStore.contexts.CATEGORY.key:
				return base + category + SEPARATOR + key;

			case kvStore.contexts.GLOBAL.key:
				return base + key;

			default:
				setError( 'Invalid KV storage context', key, null, context );
				return base + 'invalidContext' + SEPARATOR + key;
		}
	}

	// We know mw.centralNotice has been initialized since we have as a
	// dependency kvStoreMaintenance, which ensures it.

	/**
	 * Public API
	 */
	kvStore = mw.centralNotice.kvStore = {

		/**
		 * Available key-value storage contexts
		 * @enum
		 * @readonly
		 */
		contexts: {
			CAMPAIGN: new KVStorageContext( 'campaign' ),
			CATEGORY: new KVStorageContext( 'category' ),
			GLOBAL: new KVStorageContext( 'global' )
		},

		/**
		 * Does this browser support our KV storage mechanism?
		 */
		isAvailable: function() {
			return Boolean( window.localStorage );
		},

		/**
		 * Set the given value for the given key in the given context, using
		 * localStorage. If the key already exists, its value will be
		 * overwritten. Fails if localStorage is not available.
		 *
		 * Value can be any type; will be json-encoded.
		 *
		 * If the value was set, return true; if the value could not be set, we
		 * log the error via mw.log and return false. The error will be
		 * available via getError().
		 *
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {*} value
		 * @param {KVStorageContext} context
		 * @param {number} [ttl] time to live for this item, in days; defaults
		 *   to 1/2 a year.
		 * @return {boolean}
		 */
		setItem: function( key, value, context, ttl ) {

			var lsKey, encodedWrappedValue;

			// Check validity of key
			if ( key.indexOf( SEPARATOR ) !== -1 ) {
				setError( 'Invalid key', key, value, context );
				return false;
			}

			lsKey = makeKeyForLocalStorage( key, context );
			encodedWrappedValue = JSON.stringify( {
				expiry: ttl ? ( ttl * 86400 ) + now : DEFAULT_ITEM_TTL + now,
				val: value
			} );

			// Write the value
			localStorage.setItem( lsKey, encodedWrappedValue );

			// Check that it was written (it might not have been, if we're over
			// the localStorage quota for this site, for example)
			if ( localStorage.getItem( lsKey ) !== encodedWrappedValue ) {
				setError( 'Couldn\'t write value', key, value, context );
				return false;
			}

			return true;
		},

		/**
		 * Get the stored value for the given key in the given context.
		 *
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 */
		getItem: function ( key, context ) {
			var lsKey = makeKeyForLocalStorage( key, context ),
				rawValue, wrappedValue;

			rawValue = localStorage.getItem( lsKey );

			if ( rawValue === null ) {
				return null;
			}

			try {
				wrappedValue = JSON.parse( rawValue );

			} catch ( e ) {

				// If the JSON couldn't be parsed, log and return null (which is
				// the same value we'd get if the key were not set).
				if ( e instanceof SyntaxError ) {

					setError( 'Couldn\'t parse value, removing. ' + e.message,
						key, rawValue, context );

					localStorage.removeItem( lsKey );
					return null;

				// For any other errors, set and re-throw
				} else {
					setError( 'Couldn\'t read value ' + e.message,
						key, rawValue, context );

					throw e;
				}
			}

			if ( !wrappedValue.expiry || wrappedValue.expiry < now ) {
				return null;
			}

			return wrappedValue.val;
		},

		/**
		 * Remove the stored value for the given key in the given context
		 *
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 */
		removeItem: function ( key, context ) {
			var lsKey = makeKeyForLocalStorage( key, context );
			localStorage.removeItem( lsKey );
		},

		/**
		 * If a KVStore error has occurred (during this page view), return an
		 * object with information about it. If no KVStore errors have occurred,
		 * return null.
		 * @returns {?Object}
		 */
		getError: function() {
			return error;
		},

		setNotAvailableError: function() {
			setError( 'LocalStorage not available.', null, null );
		},

		setMaintenanceError: function ( lsKey ) {
			var m = lsKey.match( FIND_KEY_REGEX ),
				key = m ? m[1] : null;

			setError( 'Error during KVStore maintenance.', key, null );
		},

		setCampaignName: function( cName ) {
			campaignName = cName;
		},

		setBannerName:  function( bName ) {
			bannerName = bName;
		},

		setCategory:  function( c ) {
			category = c;
		}
	};

} )( jQuery, mediaWiki );