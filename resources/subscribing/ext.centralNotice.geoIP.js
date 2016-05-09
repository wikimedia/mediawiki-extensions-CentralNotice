/**
 * Process location data and set up window.Geo.
 * Provides mw.centralNotice.setWindowGeo().
 * TODO Move this out of CentralNotice. See https://phabricator.wikimedia.org/T102848
 */
( function ( $, mw ) {

	var COOKIE_NAME = 'GeoIP',
		GEOIP_LOOKUP_URL = '//geoiplookup.wikimedia.org/';

	/**
	 * Parse geo data in cookieValue and return an object with properties from
	 * the fields therein. Returns null if the value couldn't be parsed.
	 *
	 * The cookie will look like one of the following:
	 * - "US:CO:Denver:39.6762:-104.887:v4"
	 * - ":::::v6"
	 * - ""
	 *
	 * @param {string} cookieValue
	 * @returns {?Object}
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
			matches = matches.slice( 0, 2 ).concat( [''] )
				.concat( matches.slice( 2 ) );
		}

		// Return a juicy Geo object
		return {
			country: matches[1],
			region: matches[2],
			city: matches[3],
			lat: matches[4] && parseFloat( matches[4] ),
			lon: matches[5] && parseFloat( matches[5] ),
			af: matches[6]
		};
	}

	/**
	 * Serialize geoObj to store it in the cookie.
	 *
	 * @param {Object} geoObj
	 * @returns {string}
	 */
	function serializeCookieValue( geoObj ) {

		var parts = [
			geoObj.country,
			geoObj.region,
			( geoObj.city && geoObj.city.replace( /[^a-z]/i, '_' ) ) || '',
			geoObj.lat,
			geoObj.lon,
			( geoObj.IP && geoObj.IP.match(':') ) ? 'v6' : 'v4'
		];

		return parts.join( ':' );
	}

	/**
	 * Can we rely on this geo data?
	 * @param {Object} geoObj
	 * @returns boolean
	 */
	function isGeoDataValid( geoObj ) {
		// - Ensure 'country' is set to detect whether the cookie was succesfully
		//   parsed by parseCookieValue().
		// - Ensure 'country' is non-empty to detect empty values for when
		//   geo lookup failed (typically on IPv6 connections). This check
		//   is mandatory as otherwise the below code does not fallback to
		//   geoiplookup.wikimedia.org (IPv4-powered).
		// - The check for geoObj.af !== 'vx' became mandatory in recent
		//   refactoring to account for the temporary Geo value for during the
		//   lookup request. It (or something similar) is necesssary.
		return ( typeof geoObj.country === 'string' &&
			geoObj.country.length > 0 &&
			geoObj.af !== 'vx' );
	}

	/**
	 * Public geoIP object
	 */
	mw.geoIP = {

		/**
		 * Deferred object used to indicate when window.Geo is fully processed
		 * @private
		 */
		deferred: $.Deferred(),

		/**
		 * Attempt to set window.Geo with data from the GeoIP cookie. If that fails,
		 * make a background call that sets window.Geo, and attempt to set the
		 * cookie.
		 * @private
		 */
		setWindowGeo: function() {

			var cookieValue = $.cookie( COOKIE_NAME ),
				geoObj;

			// Were we able to read the cookie?
			if ( cookieValue ) {
				geoObj = parseCookieValue( cookieValue );

				// All good? Set window.Geo and get outta here.
				if ( geoObj && isGeoDataValid( geoObj ) ) {
					window.Geo = geoObj;
					mw.geoIP.deferred.resolve();
					return;
				}
			}

			// Handle no geo data from the cookie.

			// First, set window.Geo to signal the lack of geo data.
			// TODO Is this really how we want to do this?
			// Note: This should coordinate with check for af !== 'vx' in
			// isGeoDataValid().
			window.Geo = {
				country: '',
				region: '',
				city: '',
				lat: '',
				lon: '',
				af: 'vx'
			};

			// Try to get geo data via a background request.
			// The WMF host used for this has no IPv6 address, so the request will
			// force dual-stack users to fall back to IPv4. This is intentional;
			// IPv4 lookups may succeed when a IPv6 one fails.
			$.ajax( {
				url: GEOIP_LOOKUP_URL,
				dataType: 'script',
				cache: true
			} ).always( function () {

				// The script should set window.Geo itself. Regardless of what
				// happened, we'll store the contents of window.Geo in a cookie...
				// If the call was unsuccessful, we'll just be storing the
				// invalid data, which should trigger another attempt next
				// time around. If it was successful and we have good data,
				// subsequent page views should trigger neither an IP lookup in
				// Varnish nor an AJAX request get the data.

				// Sanity check
				if ( !window.Geo || typeof window.Geo !== 'object' ) {

					mw.log.warn( 'window.Geo cleared or ' +
						'incorrectly set by GeoIP lookup.' );

					mw.geoIP.deferred.reject();
					return;
				}

				if ( !isGeoDataValid( window.Geo ) ) {
					mw.geoIP.deferred.reject();
					return;
				}

				cookieValue = serializeCookieValue( window.Geo );

				// Update the cookie so we don't need to fetch it next time.
				// FIXME: This doesn't work in WMF production, because Varnish sets its initial
				// Geo cookie with a wildcard domain (e.g. '.wikipedia.org'). This avoids sending
				// the client a cookie for each domain. But, doesn't work with this function
				// because cookies vary on path and domain. This doesn't update the '.wikipedia.org'
				// cookie but creates a new 'en.wikipedia.org' cookie.
				// TODO: Update retreival code above to bypass $.cookie() and use document.cookie
				// directly to find the better entry instead of the first one. Both cookies will
				// be available through document.cookie.
				// http://blog.jasoncust.com/2012/01/problem-with-documentcookie.html
				$.cookie( COOKIE_NAME, cookieValue, { path: '/' } );

				mw.geoIP.deferred.resolve();
			} );
		},

		/**
		 * Returns a promise that resolves when window.Geo is available. While
		 * it's usually available right away, it may not be if a background
		 * call is needed.
		 *
		 * @returns {jQuery.Promise}
		 */
		getPromise: function() {
			return mw.geoIP.deferred.promise();
		}
	};

	mw.geoIP.setWindowGeo();

} )(  jQuery, mediaWiki );
