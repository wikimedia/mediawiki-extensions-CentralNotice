/**
 * Module for maintenance of items in kvStore. During idle time, it checks the
 * expiry times of items and removes those that expired a specified "leeway"
 * time ago.
 *
 * This module provides an API at mw.centralNotice.kvStoreMaintenance.
 */
( function ( $, mw ) {
	var	now = Math.round( ( new Date() ).getTime() / 1000 ),
		cn,

		// Regex to find kvStore localStorage keys. Must correspond with PREFIX
		// in ext.centralNotice.kvStore.js.
		PREFIX_REGEX = /^CentralNoticeKV/,

		// Time past expiry before actually removing items: 1 day (in seconds).
		// (This should prevent race conditions among browser tabs.)
		LEEWAY_FOR_REMOVAL = 86400,

		// Maximum number of keys to process at a time.
		MAX_BATCH_SIZE = 10,

		// Maximum number of items to process on any given pageview.
		// This ensures we don't block the browser too much when we prepare the
		// arrays of keys and batch functions.
		MAX_ITEMS_TO_PROCESS = 60;

	function makeRemoveExpiredBatchFunction( keys ) {
		return function() {

			var n, key, rawValue, value;

			for ( n = 0; n < keys.length; n++ ) {
				key = keys[n];

				// Operate only on localStorage items used by the kvStore
				if ( !PREFIX_REGEX.test( key ) ) {
					continue;
				}

				try {
					rawValue = localStorage.getItem( key );
				} catch ( e ) {
					return;
				}

				// The item might have been removed since we retrieved the key
				if ( rawValue === null ) {
					continue;
				}

				try {
					value = JSON.parse( rawValue );
				} catch ( e ) {
					// Remove any unparseable items and maybe set an error
					localStorage.removeItem( key );

					if ( cn.kvStore ) {
						cn.kvStore.setMaintenanceError( key );
					}

					continue;
				}

				if ( !value.expiry ||
					( value.expiry + LEEWAY_FOR_REMOVAL ) < now ) {

					localStorage.removeItem( key );
				}
			}
		};
	}

	// Don't assume mw.centralNotice has or hasn't been initialized
	mw.centralNotice = cn = ( mw.centralNotice || {} );

	/**
	 * Public API
	 */
	cn.kvStoreMaintenance = {

		/**
		 * Schedule the batched removal of expired KVStore items.
		 */
		removeExpiredItemsWhenIdle: function () {

			var funcs, keys, i, stopBefore, key,
				j = 0,
				keysToProcess;

			try {
				if ( !window.localStorage || localStorage.length === 0) {
					return;
				}
			} catch ( e ) {
				return;
			}

			// We don't know how many batches we'll get through before the user
			// navigates away, and there may be more localStorage items than
			// MAX_ITEMS_TO_PROCESS. So we choose a random key index to start
			// at, and wrap around until we've collected all keys, or the
			// maximum number.
			// This way we're likely to get to all items eventually, even if
			// there are a lot of them and/or each pageview is very quick.
			i = Math.floor( Math.random() * localStorage.length );
			stopBefore =
				( i + Math.min( MAX_ITEMS_TO_PROCESS, localStorage.length ) )
				% localStorage.length;

			// Build an array of localStorage keys
			keys = [];
			do {
				key = localStorage.key( i );

				// Don't assume that the number of keys hasn't changed and that
				// the key exists.
				if ( key !== null ) {
					keys.push( key );
				}

				i++;
				if ( i === localStorage.length ) {
					i = i - localStorage.length;
				}
			} while ( i !== stopBefore );

			// Build an array of functions to process the keys in batches
			funcs = [];
			while ( j < keys.length ) {
				keysToProcess = keys.slice( j,
					Math.min( keys.length, j + MAX_BATCH_SIZE ) );

				funcs.push( makeRemoveExpiredBatchFunction( keysToProcess ) );
				j += MAX_BATCH_SIZE;
			}

			cn.doIdleWork( funcs );
		}
	};

} )( jQuery, mediaWiki );