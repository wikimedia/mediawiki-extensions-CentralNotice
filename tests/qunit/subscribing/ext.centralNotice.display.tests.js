( function ( mw, $ ) {
	'use strict';

	var realAjax = $.ajax,
		realWindowGeo = window.Geo,
		realGeoIP = mw.geoIP,
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

	QUnit.module( 'ext.centralNotice.display', QUnit.newMwEnvironment( {
		setup: function () {

			$( '#siteNotice' ).remove();

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

}( mediaWiki, jQuery ) );
