( function () {
	'use strict';

	var i,
		cn = mw.centralNotice,

		// Parts of API to be replaced with mocks, below
		realKvStore = cn.kvStore,
		realGetDataProperty = cn.getDataProperty,
		realFailCampaign = cn.failCampaign,
		realIsCampaignFailed = cn.isCampaignFailed,
		realRequestBanner = cn.requestBanner,
		realIsBannerShown = cn.isBannerShown,

		// bannerSequence internals used in tests
		bannerSequence = require( 'ext.centralNotice.bannerSequence' ).private,

		// Test sequence
		sequence = [
			{
				banner: 'Banner_step1',
				numPageViews: 3,
				skipWithIdentifier: null
			},
			{
				banner: 'Banner_step2',
				numPageViews: 2,
				skipWithIdentifier: 'identifier'
			},
			{
				banner: null,
				numPageViews: 2,
				skipWithIdentifier: null
			}
		],

		// Expected sequence manager states at each page view
		expectedSequenceStates = [
			{
				currentStep: 0,
				banner: 'Banner_step1',
				identifierToCheck: null
			},
			{
				currentStep: 0,
				banner: 'Banner_step1',
				identifierToCheck: null
			},
			{
				currentStep: 0,
				banner: 'Banner_step1',
				identifierToCheck: null
			},
			{
				currentStep: 1,
				banner: 'Banner_step2',
				identifierToCheck: 'identifier'
			},
			{
				currentStep: 1,
				banner: 'Banner_step2',
				identifierToCheck: 'identifier'
			},
			{
				currentStep: 2,
				banner: null,
				identifierToCheck: null
			},
			{
				currentStep: 2,
				banner: null,
				identifierToCheck: null
			}
		],

		// Expected results of processPageView() following each page view
		expectedProcessPageViewResults = [
			{
				nextPageView: 1,
				identifierToSet: null
			},
			{
				nextPageView: 2,
				identifierToSet: null
			},
			{
				nextPageView: 3,
				identifierToSet: null
			},
			{
				nextPageView: 4,
				identifierToSet: null
			},
			{
				nextPageView: 5,
				identifierToSet: 'identifier'
			},
			{
				nextPageView: 6,
				identifierToSet: null
			},
			{
				nextPageView: 0,
				identifierToSet: null
			}
		],

		// Expected states after calling skipToNextStep()
		expectedStatesAfterSkip = [
			{
				currentPageView: 3,
				currentStep: 1
			},
			{
				currentPageView: 3,
				currentStep: 1
			},
			{
				currentPageView: 3,
				currentStep: 1
			},
			{
				currentPageView: 5,
				currentStep: 2
			},
			{
				currentPageView: 5,
				currentStep: 2
			},
			{
				currentPageView: 0,
				currentStep: 0
			},
			{
				currentPageView: 0,
				currentStep: 0
			}
		];

	QUnit.module( 'ext.centralNotice.bannerSequence', QUnit.newMwEnvironment( {
		afterEach: function () {

			// Restore original centralNotice API
			cn.kvStore = realKvStore;
			cn.getDataProperty = realGetDataProperty;
			cn.failCampaign = realFailCampaign;
			cn.isCampaignFailed = realIsCampaignFailed;
			cn.requestBanner = realRequestBanner;
			cn.isBannerShown = realIsBannerShown;
		}
	} ) );

	// Test sequence manager states for all page views in test sequence
	for ( i = 0; i < expectedSequenceStates.length; i++ ) {

		QUnit.test(
			'sequence manager state, page view ' + i,
			makeSeqManagerStateTestFunction( expectedSequenceStates[ i ], i )
		);
	}

	/**
	 * Return a function to test the sequence manager state on a specific page view
	 *
	 * @param {Object} expectedState
	 * @param {number} pageView
	 * @return {Function}
	 */
	function makeSeqManagerStateTestFunction( expectedState, pageView ) {

		return function ( assert ) {
			var sequenceManager = new bannerSequence.SequenceManager( sequence, pageView );
			assert.expect( 3 );

			assert.strictEqual(
				sequenceManager.currentStep,
				expectedState.currentStep,
				'Current step for page view ' + pageView
			);

			assert.strictEqual(
				sequenceManager.banner(),
				expectedState.banner,
				'Banner for page view ' + pageView
			);

			assert.strictEqual(
				sequenceManager.identifierToCheck(),
				expectedState.identifierToCheck,
				'Identifier to check for page view ' + pageView
			);
		};
	}

	// Test processPageView() for all page views in test sequence
	for ( i = 0; i < expectedProcessPageViewResults.length; i++ ) {

		QUnit.test(
			'processPageView(), page view ' + i,
			makeProcessPageViewTestFunction( expectedProcessPageViewResults[ i ], i )
		);
	}

	/**
	 * Return a function to test processPageView() on a specific page view
	 *
	 * @param {Object} expectedResult
	 * @param {number} pageView
	 * @return {Function}
	 */
	function makeProcessPageViewTestFunction( expectedResult, pageView ) {

		return function ( assert ) {
			var sequenceManager = new bannerSequence.SequenceManager( sequence, pageView );
			assert.expect( 2 );

			sequenceManager.processPageView();

			assert.strictEqual(
				sequenceManager.nextPageView,
				expectedResult.nextPageView,
				'Next page view for page view ' + pageView
			);

			assert.strictEqual(
				sequenceManager.identifierToSet,
				expectedResult.identifierToSet,
				'Identifier to set for page view ' + pageView
			);
		};
	}

	// Test states after skipToNextStep() for all page views in test sequence
	for ( i = 0; i < expectedStatesAfterSkip.length; i++ ) {

		QUnit.test(
			'skipToNextStep(), page view ' + i,
			makeSkipToNextStepTestFunction( expectedStatesAfterSkip[ i ], i )
		);
	}

	/**
	 * Return a function to test skipToNextStep() on a specific page view
	 *
	 * @param {Object} expectedCurrentPageView
	 * @param {number} pageView
	 * @return {Function}
	 */
	function makeSkipToNextStepTestFunction( expectedCurrentPageView, pageView ) {

		return function ( assert ) {
			var sequenceManager = new bannerSequence.SequenceManager( sequence, pageView );
			assert.expect( 2 );

			sequenceManager.skipToNextStep();

			assert.strictEqual(
				sequenceManager.currentPageView,
				expectedCurrentPageView.currentPageView,
				'Current page view after skip, for page view ' + pageView
			);

			assert.strictEqual(
				sequenceManager.currentStep,
				expectedCurrentPageView.currentStep,
				'Current step after skip, for page view ' + pageView
			);
		};
	}

	// Test that skipToNextStep() initially returns true, then false when we run out of
	// steps
	QUnit.test( 'skipToNextStep() return values', function ( assert ) {
		var sequenceManager = new bannerSequence.SequenceManager( sequence, 0 );

		assert.true(
			sequenceManager.skipToNextStep(),
			'Return value for successful skip to next step'
		);

		assert.true(
			sequenceManager.skipToNextStep(),
			'Return value for successful skip to next step'
		);

		assert.false(
			sequenceManager.skipToNextStep(),
			'Return value for unsuccessful skip to next step'
		);
	} );

	// Test that the current page view is reset if it's beyond the sequence limit
	QUnit.test( 'reset current page view if beyond limit', function ( assert ) {
		var sequenceManager = new bannerSequence.SequenceManager( sequence, 7 );

		assert.strictEqual(
			sequenceManager.currentPageView,
			0,
			'Current page view reset if provided value is beyond limit'
		);
	} );

	QUnit.test(
		'pre-banner handler uses bucket and stored page view, requests banner',
		function ( assert ) {
			// Mock required API bits

			cn.kvStore = {

				// Mock to get page view
				getItem: function ( key ) {
					if ( key === bannerSequence.PAGE_VIEW_STORAGE_KEY ) {
						assert.true( true, 'Retrieve page view' );
						return 1;
					}

					throw new Error( 'Incorrect key ' + key + ' in call to cn.kvStore.getItem()' );
				},

				// Stubs, not under test here
				contexts: {},
				getMultiStorageOption: function () { return 'stubStorageOption'; },
				multiStorageOptions: {}
			};

			// Mock to get bucket
			cn.getDataProperty = function ( property ) {

				if ( property === 'reducedBucket' ) {
					assert.true( true, 'Request reduced bucket' );
					return 1;
				}

				// The call to get this property is not under test here
				if ( property === 'campaignCategoryUsesLegacy' ) {
					return;
				}

				throw new Error( 'Incorrect property ' + property + ' in call to cn.getDataProperty()' );
			};

			// Mock to request banner
			cn.requestBanner = function ( banner ) {
				assert.strictEqual(
					banner,
					'Banner_step1',
					'Request correct banner'
				);
			};

			// Stub
			cn.isCampaignFailed = function () {};

			// Call the function under test
			bannerSequence.preBannerHandler( { sequences: [ null, sequence ] } );
		} );

	QUnit.test(
		'pre-banner handler uses stored identifier and hides banner on empty step',
		function ( assert ) {

			// Mock required API bits

			cn.kvStore = {

				// Mock to get page view and identifier
				getItem: function ( key ) {

					if ( key === bannerSequence.FLAG_STORAGE_KEY + '_identifier' ) {
						assert.true( true, 'Retrieve identifier' );
						return true;
					}

					// The call to get the page view is not under test here
					if ( key === bannerSequence.PAGE_VIEW_STORAGE_KEY ) {
						return 3;
					}

					throw new Error( 'Incorrect key ' + key + ' in call to cn.kvStore.getItem()' );
				},

				// Stubs
				contexts: {},
				getMultiStorageOption: function () { return 'stubStorageOption'; },
				multiStorageOptions: {}
			};

			// Mock needed to run code (calls to this function are not under test here)
			cn.getDataProperty = function ( property ) {

				// These calls are not under test here
				if ( property === 'campaignCategoryUsesLegacy' ) {
					return;
				}

				if ( property === 'reducedBucket' ) {
					return 0;
				}

				throw new Error( 'Incorrect property ' + property + ' in call to getDataProperty()' );
			};

			// Mock to fail campaign (empty step)
			cn.failCampaign = function ( reason ) {
				assert.strictEqual(
					reason,
					'bannerSequenceEmptyStep',
					'Cancel banner for empty step'
				);
			};

			// Stub
			cn.isCampaignFailed = function () {};

			// Call the function under test
			bannerSequence.preBannerHandler( { sequences: [ sequence ] } );
		} );

	QUnit.test( 'post-banner handler checks banner shown, sets identifier and page view',
		function ( assert ) {

			// Mock required API bits

			cn.kvStore = {

				// Mock setItem() to test calls
				setItem: function ( key, value ) {
					if ( key === bannerSequence.PAGE_VIEW_STORAGE_KEY ) {
						assert.strictEqual( value, 5, 'Set next page view' );
					} else if ( key === bannerSequence.FLAG_STORAGE_KEY + '_identifier' ) {
						// Value only needs to be truthy
						assert.true( value > 0, 'Set identifier' );
					} else {
						throw new Error( 'Incorrect key ' + key + ' in call to cn.kvStore.setItem()' );
					}
				},

				// Mock and stubs not under test here

				getItem: function ( key ) {
					if ( key === bannerSequence.PAGE_VIEW_STORAGE_KEY ) {
						return 4;
					}

					if ( ( key === bannerSequence.FLAG_STORAGE_KEY + '_identifier' ) ||
						( key ===
						bannerSequence.LARGE_BANNER_LIMIT_STORAGE_KEY + '_identifier' ) ) {

						return false;
					}

					throw new Error( 'Incorrect key ' + key + ' in call to cn.kvStore.getItem()' );
				},

				contexts: {},
				getMultiStorageOption: function () { return 'stubStorageOption'; },
				multiStorageOptions: {}
			};

			// Mock for isBannerShown()
			cn.isBannerShown = function () {
				assert.true( true, 'Call to isBannerShown()' );
				return true;
			};

			// Mock needed to run code (calls to this function are not under test here)
			cn.getDataProperty = function ( property ) {

				// These calls are not under test here
				if ( property === 'campaignCategoryUsesLegacy' ) {
					return;
				}

				if ( property === 'reducedBucket' ) {
					return 0;
				}

				throw new Error( 'Incorrect property ' + property + ' in call to getDataProperty()' );
			};

			// Stubs
			cn.requestBanner = function () {};
			cn.isCampaignFailed = function () {};

			// Call to pre-banner handler required for call to post-banner handler to work
			bannerSequence.preBannerHandler( { sequences: [ sequence ] } );

			// Call the function under test
			bannerSequence.postBannerOrFailHandler();
		} );

}() );
