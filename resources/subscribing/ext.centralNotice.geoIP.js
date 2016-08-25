/**
 * Processes location data and sets up a promise that resolves with that data.
 * Sets the global window.Geo and provides the public mw.geoIP object.
 * TODO Deprecate global window.Geo
 * TODO Move this out of CentralNotice. See https://phabricator.wikimedia.org/T102848
 */
( function ( $, mw ) {

	var COOKIE_NAME = 'GeoIP',
		geoPromise;

	/**
	 * Parse geo data in cookieValue and return an object with properties from
	 * the fields therein. Returns null if the value couldn't be parsed or
	 * doesn't contain location data.
	 *
	 * The cookie will look like one of the following:
	 * - "US:CO:Denver:39.6762:-104.887:v4"
	 * - ":::::v4"
	 *
	 * @param {string} cookieValue
	 * @return {?Object}
	 */
	function parseCookieValue( cookieValue ) {

		// TODO Verify that these Regexes are optimal. (Why no anchors? Why the
		// semicolon in the last group?)

		var matches =
			// Parse cookie format currently set by WMF servers
			cookieValue.match( /([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^;]*)/ ) ||

			// If that didn't match, try the old cookie format (no region data).
			// Even though these are session cookies, some might still be around.
			cookieValue.match( /([^:]*):([^:]*):([^:]*):([^:]*):([^;]*)/ );

		// No matches...? Boo, no data from geo cookie.
		if ( !matches ) {
			return null;
		}

		// If the matches were from the old cookie format, add an empty region
		// element.
		if ( matches.length === 6 ) {
			matches = matches.slice( 0, 2 ).concat( [ '' ] )
				.concat( matches.slice( 2 ) );
		}

		// There was no info found if there's no country field, or if it's
		// empty
		if ( ( typeof matches[ 1 ] !== 'string' ) || ( matches[ 1 ].length === 0 ) ) {
			return null;
		}

		// Return a juicy Geo object
		return {
			country: matches[ 1 ],
			region: matches[ 2 ],
			city: matches[ 3 ],
			lat: matches[ 4 ] && parseFloat( matches[ 4 ] ),
			lon: matches[ 5 ] && parseFloat( matches[ 5 ] ),
			af: matches[ 6 ]
		};
	}

	/**
	 * Serialize a geo object and store it in the cookie
	 * @param {Object} geo
	 */
	function storeGeoInCookie( geo ) {
		var parts = [
				geo.country,
				geo.region || '',
				( geo.city && geo.city.replace( /[^a-z]/i, '_' ) ) || '',
				geo.lat || '',
				geo.lon || '',
				geo.af || ''
			],
			cookieValue = parts.join( ':' );

		$.cookie( COOKIE_NAME, cookieValue, { path: '/' } );
	}

	/**
	 * Public geoIP object
	 */
	mw.geoIP = {

		/**
		 * Don't call this function! It is only exposed for tests.
		 *
		 * Set a promise that resolves with geo. First try to get data from the
		 * GeoIP cookie. If that fails, and if a background lookup callback
		 * module is configured, try the background lookup.
		 * @private
		 */
		makeGeoWithPromise: function () {

			var cookieValue = $.cookie( COOKIE_NAME ),
				geo, deferred, lookupModule;

			// Were we able to read the cookie?
			if ( cookieValue ) {
				geo = parseCookieValue( cookieValue );

				// All good? Resolve with geo and get outta here.
				if ( geo ) {
					deferred = $.Deferred();
					geoPromise = deferred.promise();
					deferred.resolve( geo );
					return;
				}
			}

			// Handle no geo data from the cookie.

			// If there's a background lookup to fall back to, do that
			lookupModule =
				mw.config.get( 'wgCentralNoticeGeoIPBackgroundLookupModule' );

			if ( lookupModule ) {

				geoPromise = mw.loader.using( lookupModule )

					// require arg needed for debug mode to work TODO fixed?
					.then( function ( require ) {
						var lookupCallback = require( lookupModule );

						// Chaining lookup: here, return the promise provided by
						// lookupCallback(), so it controls the result of the
						// new promise we get from then(). Also, the geo object
						// returned by the lookup promise's then() handler will
						// be passed on as an argument to the new promise's
						// done() handlers.
						return lookupCallback();
					} );

				// If the lookup was successful, store geo in a cookie
				geoPromise.done( function ( geo ) {
					storeGeoInCookie( geo );
				} );

			// If no background lookup is available, we don't have geo data
			} else {
				deferred = $.Deferred();
				geoPromise = deferred.promise();
				deferred.reject();
			}
		},

		/**
		 * Returns a promise that resolves with geo when it's available. While
		 * it's usually available right away, it may not be if a background
		 * call is performed.
		 *
		 * @return {jQuery.Promise}
		 */
		getPromise: function () {
			return geoPromise;
		}
	};

	mw.geoIP.makeGeoWithPromise();

	// For legacy code, set global window.Geo TODO: deprecate
	geoPromise.done( function ( geo ) {
		window.Geo = geo;
	} );

} )(  jQuery, mediaWiki );
