/**
 * Module for maintenance of items in kvStore. Specifically, the module keeps
 * track of items' expiry date and remove expired items. It's separate from
 * kvStore because most of the time we only need these facilities, not the whole
 * kvStore.
 *
 * This module provides an API at mw.centralNotice.kvStoreMaintenance.
 */
( function ( $, mw ) {
	var METADATA_KEY = 'CentralNoticeKVMetadata',

	// TTL of KV store items is 1/2 year, in seconds
	ITEM_TTL = 15768000,
	metadata = null,
	isAvailable = typeof window.localStorage === 'object',
	now = Math.round( ( new Date() ).getTime() / 1000 ),
	maintenance;

	/**
	 * Convenience method for a check and load that we need in several methods.
	 * @returns {boolean} true if localStorage is available and metadata is
	 *   loaded; false if no localStorage or there was a problem loading
	 *   metadata.
	 */
	function initialCheckAndLoad() {
		if ( !isAvailable ) {
			return false;
		}

		if ( !metadata ) {
			if ( !loadMetadata() ) {
				return false;
			}
		}

		return true;
	}

	function loadMetadata() {
		var rawValue = localStorage.getItem( METADATA_KEY );

		if ( rawValue === null ) {
			metadata = {};
			return true;
		}

		try {
			metadata = JSON.parse( rawValue );
		} catch ( e ) {
			return false;
		}

		return true;
	}

	function saveMetadata() {
		localStorage.setItem( METADATA_KEY, JSON.stringify( metadata ) );
	}

	// Don't assume mw.centralNotice has or hasn't been initialized
	mw.centralNotice = ( mw.centralNotice || {} );

	/**
	 * Public API
	 */
	maintenance = mw.centralNotice.kvStoreMaintenance = {

		/**
		 * This will be set to true once expired items have been removed.
		 */
		expiredItemsRemoved: false,

		/**
		 * Update or create an item's metadata, giving it ITEM_TTL time to live.
		 * @param {string} lsKey The full key used in localStorage
		 * @returns {boolean} false if there was a problem, true otherwise
		 */
		touchItem: function ( lsKey ) {

			if ( !initialCheckAndLoad() ) {
				return false;
			}

			metadata[lsKey] = now + ITEM_TTL;
			saveMetadata();
			return true;
		},

		/**
		 * Remove metadata for an item. This should be called when an item
		 * is removed.
		 * @param {string} lsKey The full key used in localStorage
		 * @returns {boolean} false if there was a problem, true otherwise
		 */
		removeItem: function ( lsKey ) {

			if ( !initialCheckAndLoad() ) {
				return false;
			}

			delete metadata[lsKey];
			saveMetadata();
			return true;
		},

		/**
		 * Remove expired KVStore items.
		 * @returns {boolean} false if there was a problem, true otherwise.
		 */
		removeExpiredItems: function () {

			var lsKey;

			if ( !initialCheckAndLoad() ) {
				return false;
			}

			for ( lsKey in metadata ) {
				if ( metadata[lsKey] < now ) {
					localStorage.removeItem( lsKey );
					delete metadata[lsKey];
				}
			}

			maintenance.expiredItemsRemoved = true;
			saveMetadata();
			return true;
		}
	};

} )( jQuery, mediaWiki );