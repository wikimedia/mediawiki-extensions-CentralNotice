( function ( mw, $ ) {
	'use strict';

	var
		COOKIE_NAME = 'GeoIP',
		BAD_COOKIE = 'asdfasdf',
		UNKNOWN_IPV6_COOKIE = ':::::v6',
		GOOD_COOKIE = 'US:CO:Denver:39.6762:-104.887:v4',
		GOOD_GEO = {
			af: 'v4',
			city: 'Denver',
			country: 'US',
			lat: 39.6762,
			lon: -104.887,
			region: 'CO'
		},
		realAjax = $.ajax,
		realCookie = $.cookie( COOKIE_NAME ),
		realDeferred = mw.geoIP.deferred,
		realGeo = window.Geo;

	QUnit.module( 'ext.centralNotice.geoIP', QUnit.newMwEnvironment( {
		teardown: function () {
			$.ajax = realAjax;
			// This cookie is always set to '/' in prod and should be here too.
			// If a cookie of the same name is set without a path it may be
			// found first by the jquery getter and will screw some behaviors
			// up until it is removed.
			$.cookie( COOKIE_NAME, realCookie, { path: '/' } );
			mw.geoIP.deferred = realDeferred;
			window.Geo = realGeo;
		}
	} ) );

	QUnit.test( 'parse geo from valid cookie', 2, function ( assert ) {
		var madeAjaxCall = false;
		$.cookie( COOKIE_NAME, GOOD_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		$.ajax = function () {
			madeAjaxCall = true;
			return $.Deferred().resolve().promise();
		};

		mw.geoIP.setWindowGeo();
		mw.geoIP.getPromise().then( function () {
			assert.equal( madeAjaxCall, false, 'no ajax call if cookie was valid' );
			assert.deepEqual( window.Geo, GOOD_GEO, 'good geo was set' );
		} );
	} );

	QUnit.test( 'restore geo if cookie is invalid', 3, function ( assert ) {
		// Break the cookie. This should kill window.Geo
		$.cookie( COOKIE_NAME, BAD_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		// setWindowGeo() should make an ajax call that restores window.Geo
		$.ajax = function () {
			assert.equal( window.Geo.af, 'vx', 'geo filled with vx' );
			window.Geo = GOOD_GEO;
			return $.Deferred().resolve().promise();
		};

		mw.geoIP.setWindowGeo();
		mw.geoIP.getPromise().then( function () {
			assert.equal( $.cookie( COOKIE_NAME ), GOOD_COOKIE, 'cookie was restored' );
			assert.deepEqual( window.Geo, GOOD_GEO, 'good geo was restored' );
		} );
	} );

	QUnit.test( 'geo info unavailable', 2, function ( assert ) {
		$.cookie( COOKIE_NAME, BAD_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		$.ajax = function () {
			// Mock failed call, don't reset geo.
			return $.Deferred().reject().promise();
		};

		mw.geoIP.setWindowGeo();
		mw.geoIP.getPromise().fail( function () {
			assert.equal( $.cookie( COOKIE_NAME ), BAD_COOKIE, 'cookie unchanged' );
			assert.equal( window.Geo.af, 'vx', 'vx geo was set' );
		} );
	} );

	QUnit.test( 'unknown ipv6 cookie', 3, function ( assert ) {
		$.cookie( COOKIE_NAME, UNKNOWN_IPV6_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		$.ajax = function () {
			assert.equal( window.Geo.af, 'vx', 'geo filled with vx' );
			window.Geo = GOOD_GEO;
			return $.Deferred().resolve().promise();
		};

		mw.geoIP.setWindowGeo();
		mw.geoIP.getPromise().done( function () {
			assert.equal( $.cookie( COOKIE_NAME ), GOOD_COOKIE, 'cookie updated' );
			assert.deepEqual( window.Geo, GOOD_GEO, 'good geo was loaded' );
		} );
	} );

}( mediaWiki, jQuery ) );
