/* eslint-disable camelcase */
( function () {
	'use strict';

	var realAjax = $.ajax,
		realGeoIP = mw.geoIP,
		realBucketCookie = $.cookie( 'CN' ),
		realHideCookie = $.cookie( 'centralnotice_hide_fundraising' ),
		realSendBeacon = navigator.sendBeacon,
		realshouldHide = mw.centralNotice.internal.hide.shouldHide,
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
				mixins: { testMixin: [ 'arg1', 'arg2' ] }
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
				mixins: { testMixin: [ 'arg1', 'arg2' ] }
			}
		],
		choiceDataCampaignsStaleness = [
			{
				name: 'campaign1_stale',
				preferred: 2,
				throttle: 100,
				bucket_count: 1,
				geotargeted: false,
				start: nowSec - ( 60000 * ( 15 * 60000 ) ), // with leeway
				end: nowSec - ( 600 * ( 15 * 60000 ) ), // with leeway
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
			}
		],
		choiceDataCampaignsFallbackMixin = [
			{
				name: 'campaign1_mixin',
				preferred: 2,
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
				mixins: {
					testMixin: [ 'arg1', 'arg2' ]
				}
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
		choiceDataCampaignsFallbackHidden = [
			{
				name: 'campaign1',
				preferred: 2,
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
						category: 'something',
						devices: [ 'desktop' ],
						display_anon: true,
						display_account: true
					}
				],
				mixins: {}
			}
		],
		choiceDataAllCampaignsFail = [
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
				mixins: { testMixin: [] }
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
				mixins: { testMixin: [] }
			}
		],
		choiceDataAllCampaignsImpressionRates = [
			{
				name: 'campaign1',
				preferred: 2,
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
				mixins: { testMixinRates: [ 0.75 ] }
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
				mixins: { testMixinRates: [ 0.5 ] }
			}
		];

	QUnit.module( 'ext.centralNotice.display', QUnit.newMwEnvironment( {
		beforeEach: function () {

			$( '#siteNotice' ).remove();

			$.removeCookie( 'centralnotice_hide_fundraising', { path: '/' } );
			$.removeCookie( 'CN', { path: '/' } );

			// Suppress background calls
			$.ajax = function () {
				return $.Deferred();
			};

			// Create normalized siteNotice.
			$( '#qunit-fixture' ).append(
				'<div id=siteNotice><div id=centralNotice></div></div>'
			);

			// Mock mw.geoIP
			mw.geoIP = {
				getPromise: function () {
					var deferred = $.Deferred();
					// Resolve with minimal valid geo object
					deferred.resolve( { country: 'AQ' } );
					return deferred.promise();
				}
			};

			// Reset record impression deferred object and array of promises for delaying
			// record impression call
			mw.centralNotice.recordImpressionDeferredObj = null;
			mw.centralNotice.recordImpressionDelayPromises = [];
		},
		afterEach: function () {
			$.ajax = realAjax;
			mw.geoIP = realGeoIP;
			navigator.sendBeacon = realSendBeacon;
			$.cookie( 'centralnotice_hide_fundraising', realHideCookie, { path: '/' } );
			$.cookie( 'CN', realBucketCookie, { path: '/' } );
			mw.centralNotice.internal.state.data = {};
			mw.centralNotice.internal.state.campaign = null;
			mw.centralNotice.internal.state.banner = null;
			mw.centralNotice.internal.state.urlParams.recordImpressionSampleRate = null;
			mw.centralNotice.internal.state.urlParams.impressionEventSampleRate = null;
			mw.centralNotice.internal.state.attemptedCampaignsByName = {};
			mw.centralNotice.internal.hide.shouldHide = realshouldHide;
		}
	} ) );

	QUnit.test( 'canInsertBanner', function ( assert ) {
		mw.centralNotice.choiceData = choiceData2Campaigns;
		mw.centralNotice.chooseAndMaybeDisplay();

		// We call reallyInsertBanner() instead of insertBanner() to avoid
		// the async DOM-waiting of the latter.
		mw.centralNotice.reallyInsertBanner( bannerData );

		assert.strictEqual( $( 'div#test_banner' ).length, 1 );
	} );

	/**
	 * Create the required state in CN for the record impression call to occur. The first
	 * campaign in choiceData2Campaigns will be chosen.
	 *
	 * @param campaignsData
	 */
	function mockChoiceDataForRecordImpressionCall( campaignsData ) {

		// Request 100% sample rate for record impression
		mw.centralNotice.internal.state.urlParams.recordImpressionSampleRate = 1;

		mw.centralNotice.choiceData = campaignsData;
		// Set `randomcampaign` to force the same choice each time, for tests where more
		// than one campaign may be available
		mw.centralNotice.internal.state.urlParams.randomcampaign = 0.25;
		mw.centralNotice.chooseAndMaybeDisplay();
	}

	QUnit.test( 'call record impression', function ( assert ) {

		// Mock navigator.sendBeacon to capture calls and check data points sent
		navigator.sendBeacon = function ( urlString ) {

			// mediawiki.Uri is already a dependency of ext.centralNotice.display
			var url = new mw.Uri( urlString );
			assert.strictEqual( url.query.campaign, 'campaign1', 'record impression campaign' );
			assert.strictEqual( url.query.banner, 'banner1', 'record impression banner' );
		};

		mockChoiceDataForRecordImpressionCall( choiceData2Campaigns );

		// Call reallyInsertBanner() instead of insertBanner() to avoid the async
		// DOM-waiting of the latter. This triggers the call to record impression.
		mw.centralNotice.reallyInsertBanner( bannerData );
	} );

	QUnit.test( 'delay record impression call and register tests', function ( assert ) {
		var deferred = $.Deferred(),
			recordImpresionPromise,
			signalTestDone = assert.async();

		// Mock navigator.sendBeacon to check record impression doesn't fire early
		navigator.sendBeacon = function () {
			// If we get here, it's a failure
			assert.true( false, 'record impression waits, as requested' );
		};

		mockChoiceDataForRecordImpressionCall( choiceData2Campaigns );

		// Request a delay and capture the promise that should run right before the
		// record impression call
		recordImpresionPromise =
			mw.centralNotice.requestRecordImpressionDelay( deferred.promise() );

		recordImpresionPromise.done( function () {
			mw.centralNotice.registerTest( 'test_test' );
		} );

		// Call reallyInsertBanner() instead of insertBanner() to avoid the async
		// DOM-waiting of the latter. This doesn't trigger the call to record impression
		// yet because we requested a delay.
		mw.centralNotice.reallyInsertBanner( bannerData );

		// Mock navigator.sendBeacon to capture calls and check data points sent
		navigator.sendBeacon = function ( urlString ) {
			var url = new mw.Uri( urlString );
			assert.strictEqual( url.query.campaign, 'campaign1', 'record impression campaign' );
			assert.strictEqual( url.query.banner, 'banner1', 'record impression banner' );

			assert.strictEqual(
				url.query.testIdentifiers,
				'test_test',
				'record impression test identifier'
			);

			signalTestDone();
		};

		// Resolve the promise to let the record impression call go ahead
		deferred.resolve();
	} );

	QUnit.test( 'record impression timeout and register tests', function ( assert ) {
		var recordImpresionPromise,
			start = Date.now(),
			MAX_RECORD_IMPRESSION_DELAY = 250, // Coordinate with ext.centralnotice.display.js
			signalTestDone = assert.async();

		mockChoiceDataForRecordImpressionCall( choiceData2Campaigns );

		// Request a delay and capture the promise that should run right before the
		// record impression call
		recordImpresionPromise =
			mw.centralNotice.requestRecordImpressionDelay( $.Deferred().promise() );

		recordImpresionPromise.done( function () {
			mw.centralNotice.registerTest( 'test_timeouted_test' );
		} );

		// Mock navigator.sendBeacon to capture calls and check time and data points sent
		navigator.sendBeacon = function ( urlString ) {
			var url = new mw.Uri( urlString ),
				delay = Date.now() - start;

			assert.strictEqual( url.query.campaign, 'campaign1', 'record impression campaign' );
			assert.strictEqual( url.query.banner, 'banner1', 'record impression banner' );

			// 50 ms leewway is bit arbitrary
			assert.true(
				( delay > MAX_RECORD_IMPRESSION_DELAY - 50 ) &&
				( delay < MAX_RECORD_IMPRESSION_DELAY + 50 ),
				'record impression called by timeout'
			);

			assert.strictEqual(
				url.query.testIdentifiers,
				'test_timeouted_test',
				'record impression test identifier'
			);

			signalTestDone();
		};

		// Call reallyInsertBanner() instead of insertBanner() to avoid the async
		// DOM-waiting of the latter. This starts the record impression timeout.
		mw.centralNotice.reallyInsertBanner( bannerData );

		// We don't resolve the promise to delay record impression. The call should
		// be made by MAX_RECORD_IMPRESSION_DELAY milliseconds (give or take a bit).
	} );

	QUnit.test( 'record impression called only once', function ( assert ) {
		var deferred = $.Deferred(),
			MAX_RECORD_IMPRESSION_DELAY = 250, // Coordinate with ext.centralnotice.display.js
			recordImpresionPromise,
			signalTestDone = assert.async();

		mockChoiceDataForRecordImpressionCall( choiceData2Campaigns );

		// Request a delay and capture the promise that should run right before the
		// record impression call
		recordImpresionPromise =
			mw.centralNotice.requestRecordImpressionDelay( deferred.promise() );

		// Mock navigator.sendBeacon to capture calls
		navigator.sendBeacon = function () {
			assert.true( true, 'record impression called once' );

			// Re-mock to fail test if called again
			navigator.sendBeacon = function () {
				assert.true( false, 'record impression not called twice' );
			};
		};

		// Call reallyInsertBanner() instead of insertBanner() to avoid the async
		// DOM-waiting of the latter. This starts the record impression timeout.
		mw.centralNotice.reallyInsertBanner( bannerData );

		// Set another timeout to resolve the promise requesting a delay after the timeout
		// to call record impression has run. By starting this timeout after the record
		// impression timeout (above) and making it a bit longer, we ensure it runs second.
		setTimeout( function () {
			assert.strictEqual(
				recordImpresionPromise.state(),
				'resolved',
				'record impression call was from timeout'
			);

			// Resolve the promise that delays the call only up until the timeout, to
			// test that record impression isn't called a second time.
			deferred.resolve();
		}, MAX_RECORD_IMPRESSION_DELAY + 1 );

		setTimeout( function () {
			signalTestDone();
		}, MAX_RECORD_IMPRESSION_DELAY + 2 );
	} );

	QUnit.test( 'banner= override param', function ( assert ) {
		mw.centralNotice.internal.state.urlParams.banner = 'test_banner';
		$.ajax = function ( params ) {
			assert.true( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=test_banner/.test( params.url ) );
			return $.Deferred();
		};
		mw.centralNotice.displayTestingBanner();

		assert.true( mw.centralNotice.data.testingBanner );
	} );

	QUnit.test( 'randomcampaign= override param', function ( assert ) {
		mw.centralNotice.choiceData = choiceData2Campaigns;

		// Get the first campaign
		mw.centralNotice.internal.state.urlParams.randomcampaign = 0.25;

		$.ajax = function ( params ) {
			assert.true( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/.test( params.url ) );
			return $.Deferred();
		};
		mw.centralNotice.chooseAndMaybeDisplay();

		// Get the second campaign
		mw.centralNotice.internal.state.urlParams.randomcampaign = 0.75;

		$.ajax = function ( params ) {
			assert.true( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/.test( params.url ) );
			return $.Deferred();
		};
		mw.centralNotice.chooseAndMaybeDisplay();
	} );

	QUnit.test( 'randombanner= override param', function ( assert ) {
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;

		// Get the first banner
		mw.centralNotice.internal.state.urlParams.randombanner = 0.25;

		$.ajax = function ( params ) {
			assert.true( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner1/.test( params.url ) );
			return $.Deferred();
		};
		mw.centralNotice.chooseAndMaybeDisplay();

		// Get the second banner
		mw.centralNotice.internal.state.urlParams.randombanner = 0.75;

		$.ajax = function ( params ) {
			assert.true( /Special(?:[:]|%3A)BannerLoader.*[?&]banner=banner2/.test( params.url ) );
			return $.Deferred();
		};
		mw.centralNotice.chooseAndMaybeDisplay();
	} );

	QUnit.test( 'runs hooks on banner shown', function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		$.ajax = function () {
			mw.centralNotice.reallyInsertBanner( bannerData );
			return $.Deferred();
		};

		mixin.setPreBannerHandler(
			function ( params ) {
				assert.deepEqual( params, choiceData1Campaign2Banners[ 0 ].mixins.testMixin );
			}
		);
		mixin.setPostBannerOrFailHandler(
			function ( params ) {
				assert.deepEqual( params, choiceData1Campaign2Banners[ 0 ].mixins.testMixin );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_SHOWN.key
		);
	} );

	QUnit.test( 'runs hooks on banner canceled', function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		mixin.setPreBannerHandler(
			function ( params ) {
				assert.deepEqual( params, choiceData1Campaign2Banners[ 0 ].mixins.testMixin );
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);
		mixin.setPostBannerOrFailHandler(
			function ( params ) {
				assert.deepEqual( params, choiceData1Campaign2Banners[ 0 ].mixins.testMixin );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_CANCELED.key
		);
		assert.strictEqual( mw.centralNotice.data.bannerCanceledReason, 'testReason' );
		assert.true( mw.centralNotice.internal.state.isCampaignFailed() );
	} );

	QUnit.test( 'runs hooks on no banner available', function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		mixin.setPreBannerHandler(
			function ( params ) {
				assert.deepEqual(
					params,
					choiceData1Campaign2Banners2Buckets[ 0 ].mixins.testMixin
				);
			}
		);
		mixin.setPostBannerOrFailHandler(
			function ( params ) {
				assert.deepEqual(
					params,
					choiceData1Campaign2Banners2Buckets[ 0 ].mixins.testMixin
				);
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceData1Campaign2Banners2Buckets;
		mw.centralNotice.internal.bucketer.setCampaign( choiceData1Campaign2Banners2Buckets[ 0 ] );
		mw.centralNotice.internal.bucketer.process();
		mw.centralNotice.internal.bucketer.setBucket( 0 );

		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.data.status,
			mw.centralNotice.internal.state.STATUSES.NO_BANNER_AVAILABLE.key
		);
	} );

	QUnit.test( 'short-circuit when there is a stale campaign in choices', function ( assert ) {
		mw.centralNotice.choiceData = choiceDataCampaignsStaleness;

		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.data.status,
			mw.centralNotice.internal.state.STATUSES.CHOICE_DATA_STALE.key,
			'choice data is stale'
		);
	} );

	QUnit.test( 'fallback when preferred campaign is filtered out by a mixin', function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		mixin.setPreBannerHandler(
			function () {
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );
		mw.centralNotice.choiceData = choiceDataCampaignsFallbackMixin;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_CHOSEN.key,
			'banner is chosen'
		);

		assert.strictEqual(
			mw.centralNotice.internal.state.data.banner,
			'banner2'
		);

		assert.strictEqual(
			mw.centralNotice.internal.state.campaign.name,
			'campaign2',
			'campaign is chosen correctly'
		);
	} );

	QUnit.test( 'fallback when preferred campaign banner is hidden', function ( assert ) {
		var i = 0;

		mw.centralNotice.internal.hide.shouldHide = function () {
			i++;
			// Ensure only the first campaign from the list will hit hide.shouldHide()
			return i === 1;
		};

		mw.centralNotice.choiceData = choiceDataCampaignsFallbackHidden;

		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.data.status,
			mw.centralNotice.internal.state.STATUSES.BANNER_CHOSEN.key,
			'banner is chosen'
		);

		assert.strictEqual(
			mw.centralNotice.internal.state.data.banner,
			'banner2'
		);

		assert.strictEqual(
			mw.centralNotice.internal.state.campaign.name,
			'campaign2',
			'campaign is chosen correctly'
		);
	} );

	// eslint-disable-next-line qunit/require-expect
	QUnit.test( 'record impression is being called when all campaigns fail', function ( assert ) {
		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		// Expect assertion in sendBeacon to be called once
		assert.expect( 1 );

		// Make every campaign fail by cancelling banners
		mixin.setPreBannerHandler(
			function () {
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		// Mock navigator.sendBeacon
		navigator.sendBeacon = function () {
			assert.true( true );
		};

		mw.centralNotice.registerCampaignMixin( mixin );

		mockChoiceDataForRecordImpressionCall( choiceDataAllCampaignsFail );

	} );

	QUnit.test( 'impression sample rate is kept as highest value', function ( assert ) {

		var mixin = new mw.centralNotice.Mixin( 'testMixinRates' );

		// Set new sample rates via mixin
		mixin.setPreBannerHandler(
			function ( mixinParams ) {
				mw.centralNotice.setMinRecordImpressionSampleRate( mixinParams[ 0 ] );
				// Fail campaign by cancelling a banner
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );

		mw.centralNotice.choiceData = choiceDataAllCampaignsImpressionRates;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.getData().recordImpressionSampleRate,
			0.75
		);

	} );

	QUnit.test( 'event sample rate is kept as highest value', function ( assert ) {

		var mixin = new mw.centralNotice.Mixin( 'testMixinRates' );

		// Set new sample rates via mixin
		mixin.setPreBannerHandler(
			function ( mixinParams ) {
				mw.centralNotice.setMinImpressionEventSampleRate( mixinParams[ 0 ] );
				// Fail campaign by cancelling a banner
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );

		mw.centralNotice.choiceData = choiceDataAllCampaignsImpressionRates;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.getData().impressionEventSampleRate,
			0.75
		);

	} );

	QUnit.test( 'event sample rate is being correctly overridden from url', function ( assert ) {

		var mixin = new mw.centralNotice.Mixin( 'testMixinRates' );

		// Override rate via URL param
		mw.centralNotice.internal.state.urlParams.impressionEventSampleRate = 1;

		// Set new sample rates via mixin
		mixin.setPreBannerHandler(
			function ( mixinParams ) {
				mw.centralNotice.setMinImpressionEventSampleRate( mixinParams[ 0 ] );
				// Fail campaign by cancelling a banner
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		mw.centralNotice.registerCampaignMixin( mixin );

		mw.centralNotice.choiceData = choiceDataAllCampaignsImpressionRates;
		mw.centralNotice.chooseAndMaybeDisplay();

		assert.strictEqual(
			mw.centralNotice.internal.state.getData().impressionEventSampleRate,
			1
		);

	} );

	QUnit.test( 'campaign statuses are tracked as serialized JSON string', function ( assert ) {

		var mixin = new mw.centralNotice.Mixin( 'testMixin' );

		// Make every campaign fail by cancelling banners
		mixin.setPreBannerHandler(
			function () {
				mw.centralNotice.failCampaign( 'testReason' );
			}
		);

		// Mock navigator.sendBeacon
		navigator.sendBeacon = function ( urlString ) {
			var url = new mw.Uri( urlString ),
				statuses = JSON.parse( url.query.campaignStatuses );
			assert.strictEqual( statuses.length, 2, 'correct amount of records' );
		};

		mw.centralNotice.registerCampaignMixin( mixin );

		mockChoiceDataForRecordImpressionCall( choiceDataAllCampaignsFail );

	} );

}() );
