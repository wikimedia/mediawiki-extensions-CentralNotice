/**
 * Module for maintenance of items in kvStore. During idle time, it checks the
 * expiry times of items and removes those that expired a specified "leeway"
 * time ago.
 *
 * This module provides an API at mw.centralNotice.kvStoreMaintenance.
 */
( function ( $, mw ) {
	var	cn,
		now = new Date().getTime() / 1000,

		// Regex to find kvStore localStorage keys. Must correspond with PREFIX
		// in ext.centralNotice.kvStore.js.
		PREFIX_REGEX = /^CentralNoticeKV/,

		// Must coordinate with PREFIX_IN_COOKIES and SEPARATOR_IN_COOKIES in
		// ext.centralNotice.kvStore.js.
		PREFIX_AND_SEPARATOR_IN_COOKIES = 'CN!',

		// Time past expiry before actually removing items: 1 day (in seconds).
		// (This should prevent race conditions among browser tabs.)
		LEEWAY_FOR_REMOVAL = 86400,

		// Minimum amount of time (in milliseconds) for an iteration involving localStorage access.
		MIN_WORK_TIME = 3;

	/**
	 * @return {jQuery.Promise} List of key strings
	 */
	function getKeys() {
		return $.Deferred( function ( d ) {
			mw.requestIdleCallback( function ( deadline ) {
				var key,
					keys = [],
					index = localStorage.length;

				// We don't expect to have more keys than we can handle in a single iteration.
				// But just in case, ensure we don't stall for too long.
				while ( index-- > 0 && deadline.timeRemaining() > MIN_WORK_TIME ) {
					key = localStorage.key( index );
					// Operate only on our own localStorage items.
					// Also recheck key existence as it may race with other tabs.
					if ( key !== null && PREFIX_REGEX.test( key ) ) {
						keys.push( key );
					}
				}
				d.resolve( keys );
			} );
		} ).promise();
	}

	/**
	 * @return {jQuery.Promise}
	 */
	function processKeys( keys ) {
		return $.Deferred( function ( d ) {
			var queue = keys.slice();
			mw.requestIdleCallback( function iterate( deadline ) {
				var key, rawValue, value;
				while ( queue[ 0 ] !== undefined && deadline.timeRemaining() > MIN_WORK_TIME ) {
					key = queue.shift();
					try {
						rawValue = localStorage.getItem( key );
						if ( rawValue ) {
							value = JSON.parse( rawValue );
							if ( !value.expiry || ( value.expiry + LEEWAY_FOR_REMOVAL ) < now ) {
								localStorage.removeItem( key );
							}
						}
					} catch ( e ) {
						localStorage.removeItem( key );
						if ( cn.kvStore ) {
							cn.kvStore.setMaintenanceError( key );
						}
					}
				}
				if ( queue[ 0 ] !== undefined ) {
					// Time's up, continue later
					mw.requestIdleCallback( iterate );
				} else {
					d.resolve();
				}
			} );
		} ).promise();
	}

	function purgeFallbackCookies() {
		var cookies = document.cookie.split( ';' ),
			i, matches,
			r = new RegExp( '^' + PREFIX_AND_SEPARATOR_IN_COOKIES + '[^=]*(?=\=)' );

		for ( i = 0; i < cookies.length; i++ ) {
			matches = cookies[i].trim().match( r );
			if ( matches ) {
				document.cookie = matches[0] +
					'=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
			}
		}
	}

	// Don't assume mw.centralNotice has or hasn't been initialized
	mw.centralNotice = cn = ( mw.centralNotice || {} );

	/**
	 * Public API
	 */
	cn.kvStoreMaintenance = {

		/**
		 * Start the removal of expired KVStore items. Also check for fallback
		 * cookies and remove them if LocalStorage is available.
		 *
		 * @return {jQuery.Promise}
		 */
		doMaintenance: function () {
			try {
				if ( !window.localStorage || !localStorage.length ) {
					return $.Deferred().resolve();
				}
			} catch ( e ) {
				return $.Deferred().resolve();
			}

			// Fallback cookies? LocalStorage seems to work, so purge them.
			if ( document.cookie.indexOf( PREFIX_AND_SEPARATOR_IN_COOKIES ) !== -1 ) {
				purgeFallbackCookies();
			}

			return getKeys().then( processKeys );
		}
	};

} )( jQuery, mediaWiki );
