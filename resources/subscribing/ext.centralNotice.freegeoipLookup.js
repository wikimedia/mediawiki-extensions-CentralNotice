/**
 * Provides a callback that ext.centralNotice.geoIP can use to fetch geo data
 * from freegeoip.net. Returns a promise that resolves with this data. If no
 * GeoIP cookie is present, mw.geoIP may use the callback, if configured to do
 * so.
 * TODO Move this out of CentralNotice. See https://phabricator.wikimedia.org/T102848
 */
( function () {
	var GEOIP_LOOKUP_URL = '//freegeoip.net/json/';

	module.exports = function () {

		return $.ajax( {
			url: GEOIP_LOOKUP_URL,
			dataType: 'jsonp'
		} ).then( function ( data ) {

			var geo = {
				country: data.country_code,
				region: data.region_code,
				city: data.city,
				lat: data.latitude,
				lon: data.longitude
			};

			// This value will be picked up by done() handlers
			return geo;
		} );
	};

}() );
