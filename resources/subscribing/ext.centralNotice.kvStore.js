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
		cookiesEnabled = null,
		localStorageAvailable = null,
		now = Math.round( ( new Date() ).getTime() / 1000 ),

		SEPARATOR = '|',

		// | gets encoded in cookies, but is already in use in localStorage
		SEPARATOR_IN_COOKIES = '!',

		FIND_KEY_REGEX = /\|([^|]*)$/,

		// Prefix for all localStorage keys. Must correspond with PREFIX_REGEX
		// in ext.centralNotice.kvStoreMaintenance.js
		PREFIX = 'CentralNoticeKV',

		// In cookies, keep it short
		PREFIX_IN_COOKIES = 'CN',

		// Default TTL of KV store items is 1/2 year, in seconds
		DEFAULT_ITEM_TTL = 15768000;

	/**
	 * A context for key-value storage.
	 *
	 * @class
	 * @param {string} key A unique string to identify this context, when using
	 *   LocalStorage. Must not contain SEPARATOR.
	 * @param {string} keyInCookies A unique string to identify this context,
	 *   when using cookies. Must not contain SEPARATOR_IN_COOKIES. (Distinct
	 *   keys for cookies help keep cookies small, improving performance.)
	 */
	KVStorageContext = function( key, keyInCookies ) {
		this.key = key;
		this.keyInCookies = keyInCookies;
	};

	/**
	 * Are cookies enabled on this client?
	 * TODO Should this go in core?
	 */
	function areCookiesEnabled() {

		// On the first call, set a cookie and try to read it back
		if ( cookiesEnabled === null ) {

			// TODO Using jquery.cookie since it's already a dependency; switch
			// to mw.cookie when we make a general switch.
			$.cookie( 'cookieTest', 'testVal' );
			cookiesEnabled = ( $.cookie( 'cookieTest' ) === 'testVal' );
			// Clear it out
			$.removeCookie( 'cookieTest' );
		}

		return cookiesEnabled;
	}

	/**
	 * Is LocalStorage available as a storage option? (Browser
	 * compatibility and certain user privacy options are required.)
	 */
	function isLocalStorageAvailable() {

		if ( localStorageAvailable === null ) {

			// For the KV store to work, the browser has to support
			// localStorage, and not throw an error if we try to access or use
			// it. (An error can be thrown if the user completely disables
			// offline website data/cookies, and in a few other circumstances)
			try {
				if ( !window.localStorage ) {
					localStorageAvailable = false;

				} else {
					localStorage.setItem( 'localStorageTest', 'testVal' );
					localStorageAvailable =
						( localStorage.getItem( 'localStorageTest' ) === 'testVal' );

					localStorage.removeItem( 'localStorageTest' );
				}
			} catch ( e ) {
				localStorageAvailable = false;
			}
		}

		return localStorageAvailable;
	}

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
	 * Return the actual key to be used in localStorage, for the given key and
	 * context.
	 *
	 * The key returned should be unique among all localStorage keys used by
	 * this site. It includes unique strings for centralNotice and context, and
	 * may also include the campaign name or category.
	 *
	 * @param {string} key
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

	/**
	 * Return the actual key to be used for a cookie (i.e., the cookie name)
	 * for the given key and context.
	 *
	 * Note: the key used in cookies contains the same information as the
	 * key used for localStorage, though the cookie key will be shorter.
	 *
	 * @param {string} key
	 * @param {KVStorageContext} context
	 * @return {string}
	 */
	function makeKeyForCookie( key, context ) {

		var base = PREFIX_IN_COOKIES + SEPARATOR_IN_COOKIES +
			context.keyInCookies + SEPARATOR_IN_COOKIES;

		switch ( context.key ) {
			case kvStore.contexts.CAMPAIGN.key:
				return base + campaignName + SEPARATOR_IN_COOKIES + key;

			case kvStore.contexts.CATEGORY.key:
				return base + category + SEPARATOR_IN_COOKIES + key;

			case kvStore.contexts.GLOBAL.key:
				return base + key;

			default:
				setError( 'Invalid KV storage context', key, null, context );
				return base + 'invalidContext' + SEPARATOR_IN_COOKIES + key;
		}
	}

	function setLocalStorageItem( key, value, context, ttl ) {

		var lsKey, encodedWrappedValue;

		lsKey = makeKeyForLocalStorage( key, context );
		encodedWrappedValue = JSON.stringify( {
			expiry: ttl ? ( ttl * 86400 ) + now : DEFAULT_ITEM_TTL + now,
			val: value
		} );

		// Write the value
		try {

			localStorage.setItem( lsKey, encodedWrappedValue );

			// Check that it was written (it might not have been, if we're over
			// the localStorage quota for this site, for example)
			if ( localStorage.getItem( lsKey ) !== encodedWrappedValue ) {
				setError( 'Couldn\'t write value', key, value, context );
				return false;
			}

			return true;

		} catch ( e ) {
			setError( 'Couldn\'t write value due to LocalStorage exception ' +
				e.toString(), key, value, context );

			return false;
		}
	}

	function setCookieItem( key, value, context, ttl ) {

		return Boolean( $.cookie(
			makeKeyForCookie( key, context ),
			encodeURIComponent( JSON.stringify( value ) ),
			{ expires: ttl, path: '/' }
		) );
	}

	function getLocalStorageItem( key, context ) {

		var lsKey = makeKeyForLocalStorage( key, context ),
			rawValue, wrappedValue;

		try {
			rawValue = localStorage.getItem( lsKey );

		} catch ( e ) {
			setError( 'Couldn\'t read value due to LocalStorage exception ' +
				e.toString(), key, null, context );

			return null;
		}

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

				try {
					localStorage.removeItem( lsKey );
				} catch ( ex ) {
					setError( 'Couldn\'t remove value due to LocalStorage exception ' +
						ex.toString(), key, rawValue, context );
				}

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
	}

	function getCookieItem( key, context ) {

		var storageKey = makeKeyForCookie( key, context),
			rawCookie = $.cookie( storageKey );

		try {
			return JSON.parse( decodeURIComponent( rawCookie ) );
		} catch ( e ) {
			// The cookie is probably corrupt. Remove.
			$.removeCookie( storageKey, { path: '/' } );
			return null;
		}
	}

	function removeLocalStorageItem( key, context) {

		try {
			localStorage.removeItem( makeKeyForLocalStorage( key, context ) );

		} catch ( e ) {
			setError( 'Couldn\'t remove value due to LocalStorage exception ' +
				e.toString(), key, null, context );
		}
	}

	function removeCookieItem( key, context ) {
		$.removeCookie( makeKeyForCookie( key, context), { path: '/' } );
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
			CAMPAIGN: new KVStorageContext( 'campaign', 'c' ),
			CATEGORY: new KVStorageContext( 'category', 't' ),
			GLOBAL: new KVStorageContext( 'global', 'g' )
		},

		/**
		 * Options for storing data with a cookie or with the kvStore
		 * (LocalStorage).
		 * @enum
		 * @readonly
		 */
		multiStorageOptions: {
			LOCAL_STORAGE: 'kv_store',
			COOKIE: 'cookie',
			NO_STORAGE: 'no_storage'
		},

		/**
		 * Set the given value for the given key in the given context, using
		 * LocalStorage or a cookie. If the key already exists, its value will
		 * be overwritten.
		 *
		 * Value can be any type; will be json-encoded.
		 *
		 * Only when using LocalStorage: if the value was set, return true; if
		 * the value could not be set, we log the error via mw.log and return
		 * false. The error will be available via getError().
		 *
		 * Note: check isAvailable() before calling, or provide a
		 * multiStorageOption.
		 *
		 * Note: when using CAMPAIGN and CATEGORY contexts, ensure that you have
		 * set campaign and category, respectively. We don't check them here.
		 *
		 * @param {string} key
		 * @param {*} value
		 * @param {KVStorageContext} context
		 *
		 * @param {number} [ttl] Time to live for this item, in days; defaults
		 *   to 1/2 a year. Null will trigger the default.
		 *
		 * @param {string} [multiStorageOption] A key from among
		 *   kvStore.multiStorageOptions, to indicate how to store the item.
		 *   Defaults to kvStore.multiStorageOptions.LOCAL_STORAGE.
		 *
		 * @return {boolean} true if the value could be set, false otherwise
		 */
		setItem: function( key, value, context, ttl, multiStorageOption ) {

			// Check validity of key
			if ( ( key.indexOf( SEPARATOR ) !== -1 ) ||
				( key.indexOf( SEPARATOR_IN_COOKIES ) !== -1 ) ) {

				setError( 'Invalid key', key, value, context );
				return false;
			}

			multiStorageOption =
				multiStorageOption || kvStore.multiStorageOptions.LOCAL_STORAGE;

			switch ( multiStorageOption ) {

				case kvStore.multiStorageOptions.LOCAL_STORAGE:
					return setLocalStorageItem( key, value, context, ttl );

				case kvStore.multiStorageOptions.COOKIE:
					return setCookieItem( key, value, context, ttl );

				case kvStore.multiStorageOptions.NO_STORAGE:
					return false;

				default:
					throw 'Unexpected multi-storage option';
			}
		},

		/**
		 * Get the stored value for the given key in the given context.
		 *
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 * @param {string} [multiStorageOption] A key from among
		 *   kvStore.multiStorageOptions, to indicate how to store the item.
		 *   Defaults to kvStore.multiStorageOptions.LOCAL_STORAGE.
		 */
		getItem: function ( key, context, multiStorageOption ) {

			multiStorageOption =
				multiStorageOption || kvStore.multiStorageOptions.LOCAL_STORAGE;

			switch ( multiStorageOption ) {

				case kvStore.multiStorageOptions.LOCAL_STORAGE:
					return getLocalStorageItem( key, context );

				case kvStore.multiStorageOptions.COOKIE:
					return getCookieItem( key, context );

				case kvStore.multiStorageOptions.NO_STORAGE:
					return null;

				default:
					throw 'Unexpected multi-storage option';
			}


		},

		/**
		 * Remove the stored value for the given key in the given context
		 *
		 * Note: check isAvailable() before calling.
		 *
		 * @param {string} key
		 * @param {KVStorageContext} context
		 * @param {string} [multiStorageOption] A key from among
		 *   kvStore.multiStorageOptions, to indicate how to store the item.
		 *   Defaults to kvStore.multiStorageOptions.LOCAL_STORAGE.
		 */
		removeItem: function ( key, context, multiStorageOption ) {

			multiStorageOption =
				multiStorageOption || kvStore.multiStorageOptions.LOCAL_STORAGE;

			switch ( multiStorageOption ) {

				case kvStore.multiStorageOptions.LOCAL_STORAGE:
					removeLocalStorageItem( key, context );
					return;

				case kvStore.multiStorageOptions.COOKIE:
					removeCookieItem( key, context );
					return;

				case kvStore.multiStorageOptions.NO_STORAGE:
					return;

				default:
					throw 'Unexpected multi-storage option';
			}
		},

		/**
		 * Convenience method to check for availability of storage without
		 * falling back to cookies.
		 */
		isAvailable: function () {
			return ( kvStore.getMultiStorageOption( false ) !==
				kvStore.multiStorageOptions.NO_STORAGE );
		},

		/**
		 * Determine the appropriate multi-storage option
		 *
		 * @param {boolean} cookieAllowed
		 * @returns {string} A string key
		 */
		getMultiStorageOption: function( cookieAllowed ) {

			if ( isLocalStorageAvailable() ) {
				return kvStore.multiStorageOptions.LOCAL_STORAGE;
			}

			if ( cookieAllowed && areCookiesEnabled() ) {
				return kvStore.multiStorageOptions.COOKIE;
			}

			return kvStore.multiStorageOptions.NO_STORAGE;
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
