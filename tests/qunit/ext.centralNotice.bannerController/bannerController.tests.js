( function ( mw, $ ) {
	'use strict';

	var bannerJson = {
			bannerName: 'test_banner',
			campaign: 'test_campaign',
			category: 'test',
			bannerHtml: '<div id="test_banner"></div>'
		};

	QUnit.module( 'ext.centralNotice.bannerController', QUnit.newMwEnvironment( {
		setup: function () {
			var realLoadBanner = mw.centralNotice.loadBanner;

			// Remove any existing div#siteNotice, so we are not testing the skin.
			$( '#siteNotice' ).remove();

			// Reset in case the testing page itself ran CentralNotice.
			mw.centralNotice.alreadyRan = false;

			// Fool code that prevents CentralNotice from running on Special pages.
			mw.config.set( 'wgNamespaceNumber', 0 );

			// Prevent banner load during initialize().
			mw.centralNotice.loadBanner = function () {};

			// Suppress GeoIP call
			mw.centralNotice.data.getVars.country = 'US';

			mw.centralNotice.initialize();

			mw.centralNotice.loadBanner = realLoadBanner;

			// Create normalized siteNotice.
			$( "#qunit-fixture" ).append(
				'<div id=siteNotice><div id=centralNotice></div></div>'
			);
		}
	} ) );

	QUnit.test( 'hasAlreadyRan', 1, function( assert ) {
		assert.ok( mw.centralNotice.alreadyRan );
	} );

	QUnit.test( 'canInsertBanner', 1, function( assert ) {
		mw.centralNotice.insertBanner( bannerJson );
		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

	QUnit.test( 'canPreloadHide', 1, function( assert ) {
		mw.centralNotice.bannerData.preload = function () {
			return false;
		};

		mw.centralNotice.insertBanner( bannerJson );
		assert.equal( $( 'div#test_banner' ).length, 0 );
	} );

	QUnit.test( 'canPreloadShow', 1, function( assert ) {
		mw.centralNotice.bannerData.preload = function () {
			return true;
		};

		mw.centralNotice.insertBanner( bannerJson );
		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

}( mediaWiki, jQuery ) );
