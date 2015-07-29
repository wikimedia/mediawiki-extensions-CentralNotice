( function ( mw, $ ) {
	'use strict';

	var
		BAD_COOKIE = 'asdfasdf',
		COOKIE_NAME = 'GeoIP',
		GOOD_COOKIE = 'US:CO:Denver:39.6762:-104.887:v4',
		GOOD_GEO = {
			af: "v4",
			city: "Denver",
			country: "US",
			lat: 39.6762,
			lon: -104.887,
			region: "CO"
		},
		realAjax,
		realCookie,
		realDeferred,
		realGeo;

	QUnit.module( 'ext.centralNotice.geoIP', QUnit.newMwEnvironment( {
		setup: function () {
			realAjax = $.ajax;
			realCookie = $.cookie( COOKIE_NAME );
			realDeferred = mw.geoIP.deferred;
			realGeo = window.Geo;
		},
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
			var p = $.Deferred();
			p.resolve();
			return p.promise();
		};

		$.when( mw.geoIP.getPromise() ).then( function () {
			// Should not make a background call when cookie is present.
			assert.equal( madeAjaxCall, false );
			// Should re-add parsed geo info from cookie.
			assert.deepEqual( window.Geo, GOOD_GEO );
		} );

		// Running should parse the cookie.
		mw.geoIP.setWindowGeo();
	} );

	QUnit.test( 'restore geo if cookie is invalid', 3, function ( assert ) {
		// Break the cookie. This should kill window.Geo
		$.cookie( COOKIE_NAME, BAD_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		// setWindowGeo() should make an ajax call that restores window.Geo
		$.ajax = function () {
			assert.equal( window.Geo.af, 'vx' );
			window.Geo = GOOD_GEO;
			var p = $.Deferred();
			p.resolve();
			return p.promise();
		};

		// Should restore the cookie.
		$.when( mw.geoIP.getPromise() ).then( function () {
			assert.equal( $.cookie( COOKIE_NAME ), GOOD_COOKIE );
			assert.deepEqual( window.Geo, GOOD_GEO );
		} );
		mw.geoIP.setWindowGeo();
	} );

	QUnit.test( 'geo info unavailable', 2, function ( assert ) {
		$.cookie( COOKIE_NAME, BAD_COOKIE, { path: '/' } );
		mw.geoIP.deferred = $.Deferred();
		window.Geo = null;

		$.ajax = function () {
			// Mock failed call, don't reset geo.
			var p = $.Deferred();
			p.reject();
			return p.promise();
		};

		$.when( mw.geoIP.getPromise() ).then( function () {
		}, function () {
			assert.equal( window.Geo.af, 'vx' );
			assert.equal( $.cookie( COOKIE_NAME ), BAD_COOKIE );
		});
		mw.geoIP.setWindowGeo();
	} );

}( mediaWiki, jQuery ) );
