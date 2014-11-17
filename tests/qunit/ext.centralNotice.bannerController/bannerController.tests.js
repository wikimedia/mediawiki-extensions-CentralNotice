( function ( mw, $ ) {
	'use strict';

	var realAjax = $.ajax,
		bannerData = {
			bannerName: 'test_banner',
			campaign: 'test_campaign',
			category: 'test',
			bannerHtml: '<div id="test_banner"></div>'
		};

	QUnit.module( 'ext.centralNotice.bannerController', QUnit.newMwEnvironment( {
		setup: function () {
			var realLoadBanner = mw.centralNotice.loadBanner;

			// Reset in case the testing page itself ran CentralNotice.
			mw.centralNotice.alreadyRan = false;

			// Fool code that prevents CentralNotice from running on Special pages.
			mw.config.set( 'wgNamespaceNumber', 0 );

			// Prevent banner load during initialize().
			mw.centralNotice.loadBanner = function () {};

			// Force to the first bucket.
			mw.centralNotice.getBucket = function() { return 0; };

			$.extend( mw.centralNotice.data.getVars, {
				// Boring defaults, assumed by test fixtures.
				// FIXME: move to tests that actually assume this.  Move the
				// initialize() call as well.
				// FIXME: see below
				country: 'XX',
				uselang: 'en',
				project: 'wikipedia',
				anonymous: true
			} );

			// Remove any existing div#siteNotice, so we are not testing the skin.
			// Do it before initialize, so nothing 
			$( '#siteNotice' ).remove();

			// Sigh.  Suppress the GeoIP call, and prevent any other side-
			// effects, unless $.ajax is explictly mocked by a test case.
			$.ajax = function() { return $.Deferred(); };

			mw.centralNotice.initialize();

			mw.centralNotice.loadBanner = realLoadBanner;

			// Create normalized siteNotice.
			$( "#qunit-fixture" ).append(
				'<div id=siteNotice><div id=centralNotice></div></div>'
			);
		},
		teardown: function () {
			$.ajax = realAjax;
		}
	} ) );

	QUnit.test( 'hasAlreadyRan', 1, function( assert ) {
		assert.ok( mw.centralNotice.alreadyRan );
	} );

	QUnit.test( 'canInsertBanner', 1, function( assert ) {
		mw.centralNotice.insertBanner( bannerData );
		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

	QUnit.test( 'canPreloadHide', 1, function( assert ) {
		mw.centralNotice.bannerData.preload = function () {
			return false;
		};

		mw.centralNotice.insertBanner( bannerData );
		assert.equal( $( 'div#test_banner' ).length, 0 );
	} );

	QUnit.test( 'canPreloadShow', 1, function( assert ) {
		mw.centralNotice.bannerData.preload = function () {
			return true;
		};

		mw.centralNotice.insertBanner( bannerData );
		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

	QUnit.test( 'banner= override param', 2, function( assert ) {
		mw.centralNotice.data.getVars.banner = 'test_banner';
		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=test_banner/ ) );
		};
		mw.centralNotice.loadBanner();

		assert.ok( mw.centralNotice.data.testing );
	} );

}( mediaWiki, jQuery ) );
