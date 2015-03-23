( function ( mw, $ ) {
	'use strict';

	var realAjax = $.ajax,
		realGetVars = mw.centralNotice.data.getVars,
		bannerData = {
			bannerName: 'test_banner',
			campaign: 'test_campaign',
			category: 'test',
			bannerHtml: '<div id="test_banner"></div>'
		},
		nowSec = Date.now() / 1000,
		choiceData2Campaigns = [
			{
				name: 'campaign1',
				preferred: 1,
				throttle: 100,
				bucket_count: 1,
				geotargeted: false,
				start: nowSec - 1,
				end: nowSec + 600,
				banners: [
					{
						name: 'banner1',
						weight: 25,
						bucket: 0,
						category: 'fundraising',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					}
				],
				mixins: {}
			},
			{
				name: 'campaign2',
				preferred: 1,
				throttle: 100,
				bucket_count: 1,
				geotargeted: false,
				start: nowSec - 1,
				end: nowSec + 600,
				banners: [
					{
						name: 'banner2',
						weight: 25,
						bucket: 0,
						category: 'fundraising',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					}
				],
				mixins: {}
			}
		],
		choiceData1Campaign2Banners = [
			{
				name: 'campaign1',
				preferred: 1,
				throttle: 100,
				bucket_count: 1,
				geotargeted: false,
				start: nowSec - 1,
				end: nowSec + 600,
				banners: [
					{
						name: 'banner1',
						weight: 25,
						bucket: 0,
						category: 'fundraising',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					},
					{
						name: 'banner2',
						weight: 25,
						bucket: 0,
						category: 'fundraising',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					}
				],
				mixins: {}
			}
		];

	QUnit.module( 'ext.centralNotice.bannerController', QUnit.newMwEnvironment( {
		setup: function () {
			var realLoadBanner = mw.centralNotice.loadBanner;

			// Reset in case the testing page itself ran CentralNotice.
			mw.centralNotice.alreadyRan = false;

			// Fool code that prevents CentralNotice from running on Special pages.
			mw.config.set( 'wgNamespaceNumber', 0 );

			// Prevent banner load during initialize().
			mw.centralNotice.loadBanner = function () {};

			mw.centralNotice.data.getVars = {};
			$.extend( mw.centralNotice.data.getVars, realGetVars, {
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

			// Mock out buckets
			mw.cnBannerControllerLib.bucketsByCampaign = {};
			mw.cnBannerControllerLib.bucketsByCampaign[bannerData.campaign] = {
				val: 0,
				start: 1419937200,
				end: 1414754400
			};

			// Create normalized siteNotice.
			$( '#qunit-fixture' ).append(
				'<div id=siteNotice><div id=centralNotice></div></div>'
			);
		},
		teardown: function () {
			$.ajax = realAjax;
			mw.centralNotice.data.getVars = realGetVars;
		}
	} ) );

	QUnit.test( 'hasAlreadyRan', 1, function( assert ) {
		assert.ok( mw.centralNotice.alreadyRan );
	} );

	QUnit.test( 'canInsertBanner', 1, function( assert ) {
		mw.centralNotice.insertBanner( bannerData );
		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

	QUnit.test( 'canResultCauseHide', 1, function( assert ) {
		mw.centralNotice.impressionData.result = 'hide';

		mw.centralNotice.insertBanner( bannerData );
		assert.equal( $( 'div#test_banner' ).length, 0 );
	} );

	QUnit.test( 'canResultCauseShow', 1, function( assert ) {
		mw.centralNotice.impressionData.result = 'show';

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

	QUnit.test( 'randomcampaign= override param', 2, function( assert ) {
		mw.cnBannerControllerLib.setChoiceData( choiceData2Campaigns );

		// Get the first banner
		mw.centralNotice.data.getVars.randomcampaign = 0.25;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/ ) );
		};
		mw.centralNotice.loadBanner();

		// Get the second banner
		mw.centralNotice.data.getVars.randomcampaign = 0.75;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/ ) );
		};
		mw.centralNotice.loadBanner();
	} );

	QUnit.test( 'randombanner= override param', 2, function( assert ) {
		mw.cnBannerControllerLib.setChoiceData( choiceData1Campaign2Banners );

		// Get the first banner
		mw.centralNotice.data.getVars.randombanner = 0.25;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/ ) );
		};
		mw.centralNotice.loadBanner();

		// Get the second banner
		mw.centralNotice.data.getVars.randombanner = 0.75;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/ ) );
		};
		mw.centralNotice.loadBanner();
	} );

}( mediaWiki, jQuery ) );
