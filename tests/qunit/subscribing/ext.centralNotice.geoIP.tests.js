( function () {
	'use strict';

	var
		COOKIE_NAME = 'GeoIP',
		BAD_COOKIE = 'asdfasdf',
		UNKNOWN_COOKIE = ':::::v4',
		GOOD_COOKIE = 'US:CO:Denver:39.6762:-104.887:v4',
		GOOD_GEO = {
			af: 'v4',
			city: 'Denver',
			country: 'US',
			lat: 39.6762,
			lon: -104.887,
			region: 'CO'
		},
		realCookie = $.cookie( COOKIE_NAME );

	QUnit.module( 'ext.centralNotice.geoIP', QUnit.newMwEnvironment( {
		afterEach: function () {
			// This cookie is always set to '/' in prod and should be here too.
			// If a cookie of the same name is set without a path it may be
			// found first by the jquery getter and will screw some behaviors
			// up until it is removed.
			$.cookie( COOKIE_NAME, realCookie, { path: '/' } );
		}
	} ) );

	QUnit.test( 'parse geo from valid cookie', function ( assert ) {
		$.cookie( COOKIE_NAME, GOOD_COOKIE, { path: '/' } );

		mw.geoIP.makeGeoWithPromise();
		return mw.geoIP.getPromise().then( function ( geo ) {
			assert.deepEqual( geo, GOOD_GEO, 'parsed geo' );
		}, function () {
			// Message to show when promise fails
			return 'geo not retrieved';
		} );
	} );

	QUnit.test( 'cookie invalid', function ( assert ) {
		$.cookie( COOKIE_NAME, BAD_COOKIE, { path: '/' } );

		// Make sure that we don't fall back
		mw.config.set( 'wgCentralNoticeGeoIPBackgroundLookupModule', false );

		mw.geoIP.makeGeoWithPromise();
		mw.geoIP.getPromise().fail( function () {
			assert.true( true, 'geoIP promise fails, as expected' );
		} ).done( function () {
			assert.true( false, 'geoIP promise succeeded, but should not have' );
		} );
	} );

	QUnit.test( 'cookie valid but unknown location', function ( assert ) {
		$.cookie( COOKIE_NAME, UNKNOWN_COOKIE, { path: '/' } );

		// Make sure that we don't fall back
		mw.config.set( 'wgCentralNoticeGeoIPBackgroundLookupModule', false );

		mw.geoIP.makeGeoWithPromise();
		mw.geoIP.getPromise().fail( function () {
			assert.true( true, 'geoIP promise fails, as expected' );
		} ).done( function () {
			assert.true( false, 'geoIP promise succeeded, but should not have' );
		} );
	} );

}() );
