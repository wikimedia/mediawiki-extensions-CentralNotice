/**
 * Module for key-value storage in localStorage, use by CentralNotice campaign
 * mixins and in-banner JS.
 *
 * This module is made available at mw.centralNotice.kvStore. However, for most
 * cases, it is preferable to call the related methods directly on
 * mw.centralNotice.
 */
( function ( $, mw ) {

	var KVStorageContext,
		kvStore,
		KV_STORAGE_CN_PREFIX = 'CentralNotice',
		KV_STORAGE_PREFIX_SEPARATOR = '|',
		KV_STORAGE_ERROR_COOKIE_NAME = 'centralnotice_kv_storage_errors',
		campaignName = null,
		bannerName = null,
		category = null;

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
	function logError( message, key, value, context ) {

		// Try to get an existing cookie; if there is one, we'll append this
		// error to it.
		var errCookieVal = kvStore.getErrorLog(),
			err = {
				message: message,
				key: key,
				// Limit the length of the value to store
				value: JSON.stringify( value ).substring( 0, 50 ),
				context: context ? context.key : null,
				time: Math.round( ( new Date() ).getTime() / 1000 )
			};

		// If a campaign and/or a banner name have been set, include their names
		// in the error
		err.campaign = campaignName;
		err.banner = bannerName;

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
	 * Note: when using CAMPAIGN and CATEGORY contexts, ensure that you have set
	 * campaign and category, respectively. We don't check them here.
	 *
	 * @param {string} key
	 * @param {*} value
	 * @param {KVStorageContext} context
	 * @return {string}
	 */
	function makeKeyForLocalStorage( key, context ) {
		var base = KV_STORAGE_CN_PREFIX + KV_STORAGE_PREFIX_SEPARATOR +
				context.key + KV_STORAGE_PREFIX_SEPARATOR;

		switch ( context.key ) {
			case kvStore.contexts.CAMPAIGN.key:
				return base +
					campaignName + KV_STORAGE_PREFIX_SEPARATOR +
					key;

			case kvStore.contexts.CATEGORY.key:
				return base +
					category + KV_STORAGE_PREFIX_SEPARATOR +
					key;

			case kvStore.contexts.GLOBAL.key:
				return base + key;

			default:
				logError( 'Invalid KV storage context', key, null, context );
				return base +
					'invalidContext' + KV_STORAGE_PREFIX_SEPARATOR +
					key;
		}
	}

	// It's impossible to know whether mw.centralNotice has been initialized
	mw.centralNotice = ( mw.CentralNotice || {} );

	/**
	 * kvStore object. Mostly, don't use this! In most cases, access via
	 * methods onmw.centralNotice is preferred.
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
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {*} value
		 * @param {KVStorageContext} context
		 * @return {boolean}
		 */
		setItem: function( key, value, context ) {

			var lsKey, encodedValue;

			// Check validity of key
			if ( key.indexOf( KV_STORAGE_PREFIX_SEPARATOR ) !== -1 ) {
				logError( 'Invalid key', key, value, context );
				return false;
			}

			lsKey = makeKeyForLocalStorage( key, context );
			encodedValue = JSON.stringify( value );

			// Write the value
			localStorage.setItem( lsKey, encodedValue );

			// Check that it was written (it might not have been, if we're over
			// the localStorage quota for this site, for example)
			if ( localStorage.getItem( lsKey ) !== encodedValue ) {
				logError( 'Couldn\'t write value', key, value, context );
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
				rawValue, value;

			try {
				rawValue = localStorage.getItem( lsKey );
				value = JSON.parse( rawValue );

			} catch ( e ) {

				// If the JSON couldn't be parsed, log and return null (which is
				// the same value we'd get if the key were not set).
				if ( e instanceof SyntaxError ) {

					logError( 'Couldn\'t parse value, removing. ' + e.message,
						key, rawValue, context );

					localStorage.removeItem( lsKey );
					return null;

				// For any other errors, log and re-throw
				} else {
					logError( 'Couldn\'t read value ' + e.message,
						key, rawValue, context );

					throw e;
				}
			}

			return value;
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

		getErrorLog: function() {
			var errCookieRaw = $.cookie( KV_STORAGE_ERROR_COOKIE_NAME ),
				errCookieVal;

			// If we didn't get a cookie value, or if it wasn't an array, set it to
			// an empty array.
			if ( !errCookieRaw ) {
				errCookieVal = [];
			} else {
				try {
					errCookieVal = JSON.parse( errCookieRaw );
				} catch ( e ) {
					errCookieVal = [ {
						message: 'Couldn\'t parse error log.',
						time: Math.round( ( new Date() ).getTime() / 1000 )
					} ];
				}

				if ( !Array.isArray( errCookieVal ) ) {
					errCookieVal = [];
				}
			}

			return errCookieVal;
		},

		logNotAvailableError: function() {
			logError( 'LocalStorage not available.', null, null );
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