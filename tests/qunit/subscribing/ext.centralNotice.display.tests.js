( function ( mw, $ ) {
	'use strict';

	var realAjax = $.ajax,
		realWindowGeo = window.Geo,
		realGeoIP = mw.geoIP,
		realBucketCookie = $.cookie( 'CN' ),
		realHideCookie = $.cookie( 'centralnotice_hide_fundraising' ),
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
				mixins: { 'testMixin': [ 'arg1', 'arg2' ] }
			}
		],
		choiceData1Campaign2Banners2Buckets = [
			{
				name: 'campaign1',
				preferred: 1,
				throttle: 100,
				bucket_count: 2,
				geotargeted: false,
				start: nowSec - 1,
				end: nowSec + 600,
				banners: [
					{
						name: 'banner1',
						weight: 25,
						bucket: 0,
						category: 'fundraising',
						devices: [ 'mobile' ],
						display_anon: false,
						display_account: false
					},
					{
						name: 'banner2',
						weight: 25,
						bucket: 1,
						category: 'fundraising',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					}
				],
				mixins: { 'testMixin': [ 'arg1', 'arg2' ] }
			}
		];

	// Make this property configurable before the first time it's set,
	// so the browser lets us define it again and again (since public objects
	// aren't re-created between tests).
	Object.defineProperty( mw.centralNotice, 'data', { configurable: true } );

	QUnit.module( 'ext.centralNotice.display', QUnit.newMwEnvironment( {
		setup: function () {

			$( '#siteNotice' ).remove();

			$.removeCookie( 'centralnotice_hide_fundraising', { path: '/' } );
			$.removeCookie( 'CN', { path: '/' } );

			// Suppress background calls
			$.ajax = function() { return $.Deferred(); };

			// Create normalized siteNotice.
			$( '#qunit-fixture' ).append(
				'<div id=siteNotice><div id=centralNotice></div></div>'
			);

			// Mock window.Geo object and mw.geoIP
			window.Geo = {};
			mw.geoIP = {
				getPromise: function() {
					var deferred = $.Deferred();
					deferred.resolve();
					return deferred.promise();
				}
			};
		},
		teardown: function () {
			$.ajax = realAjax;
			mw.geoIP = realGeoIP;
			$.cookie( 'centralnotice_hide_fundraising', realHideCookie, { path: '/' } );
			$.cookie( 'CN', realBucketCookie, { path: '/' } );
			mw.centralNotice.internal.state.data = {};
			mw.centralNotice.internal.state.campaign = null;
			mw.centralNotice.internal.state.banner = null;

			if ( typeof realWindowGeo !== 'undefined' ) {
				window.Geo = realWindowGeo;
			}
		}
	} ) );

	QUnit.test( 'canInsertBanner', 1, function( assert ) {
		mw.centralNotice.choiceData = choiceData2Campaigns;
		mw.centralNotice.chooseAndMaybeDisplay();

		// We call reallyInsertBanner() instead of insertBanner() to avoid
		// the async DOM-waiting of the latter.
		mw.centralNotice.reallyInsertBanner( bannerData );

		assert.equal( $( 'div#test_banner' ).length, 1 );
	} );

	QUnit.test( 'banner= override param', 2, function( assert ) {
		mw.centralNotice.internal.state.urlParams.banner = 'test_banner';
		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=test_banner/ ) );
		};
		mw.centralNotice.displayTestingBanner();

		assert.ok( mw.centralNotice.data.testingBanner );
	} );

	QUnit.test( 'randomcampaign= override param', 2, function( assert ) {

		mw.centralNotice.choiceData = choiceData2Campaigns;

		// Get the first banner
		mw.centralNotice.internal.state.urlParams.randomcampaign = 0.25;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/ ) );
		};
		mw.centralNotice.chooseAndMaybeDisplay();

		// Get the second banner
		mw.centralNotice.internal.state.urlParams.randomcampaign = 0.75;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/ ) );
		};
		mw.centralNotice.chooseAndMaybeDisplay();
	} );

	QUnit.test( 'randombanner= override param', 2, function( assert ) {
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;

		// Get the first banner
		mw.centralNotice.internal.state.urlParams.randombanner = 0.25;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/ ) );
		};
		mw.centralNotice.chooseAndMaybeDisplay();

		// Get the second banner
		mw.centralNotice.internal.state.urlParams.randombanner = 0.75;

		$.ajax = function( params ) {
			assert.ok( params.url.match( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/ ) );
		};
		mw.centralNotice.chooseAndMaybeDisplay();
	} );

	QUnit.test( 'runs hooks on banner shown', 3, function ( assert ) {
		$.ajax = function() {
			mw.centralNotice.reallyInsertBanner( bannerData );
		};

		var mixin = new mw.centralNotice.Mixin( 'testMixin' );
		mixin.setPreBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners[0].mixins.testMixin );
		} );
		mixin.setPostBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners[0].mixins.testMixin );
		} );

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.equal( mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_SHOWN.key );
	} );

	QUnit.test( 'runs hooks on banner canceled', 5, function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );
		mixin.setPreBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners[0].mixins.testMixin );
			mw.centralNotice.cancelBanner( 'testReason' );
		} );
		mixin.setPostBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners[0].mixins.testMixin );
		} );

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.equal( mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_CANCELED.key );
		assert.equal( mw.centralNotice.data.bannerCanceledReason, 'testReason' );
		assert.ok( mw.centralNotice.internal.state.isBannerCanceled() );
	} );

	QUnit.test( 'runs hooks on no banner available', 3, function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );
		mixin.setPreBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners2Buckets[0].mixins.testMixin );
		} );
		mixin.setPostBannerHandler( function( params ) {
			assert.deepEqual( params, choiceData1Campaign2Banners2Buckets[0].mixins.testMixin );
		} );

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners2Buckets;
		mw.centralNotice.internal.bucketer.setCampaign( choiceData1Campaign2Banners2Buckets[0] );
		mw.centralNotice.internal.bucketer.process();
		mw.centralNotice.internal.bucketer.setBucket( 0 );

		mw.centralNotice.chooseAndMaybeDisplay();

		assert.equal( mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.NO_BANNER_AVAILABLE.key );
	} );

}( mediaWiki, jQuery ) );
