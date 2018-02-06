/**
 * Stores data for campaign/banner processing and data related to the state of
 * that processing. Provides cn.internal.state and cn.data.
 *
 * Note: Coordinate with CentralNoticeImpression schema; all data properties must
 * either be in the schema and have the correct data type, or must be removed by
 * calling getDataCopy( true ).
 */
( function ( $, mw ) {

	var state,
		status,

		UNKNOWN_COUNTRY_CODE = 'XX',

		// Campaign category, a temporary hack due to our hiccupy data model, is
		// gleaned from the categories of all the banners in a campaign. In the
		// unlikely case that a campaign contains banners of different
		// categories, the campaign category is set to this value.
		CAMPAIGN_CATEGORY_FOR_MIXED_BANNER_CATEGORIES = 'mixed_banner_categories',

		// These must coordinate with device names in the CN database
		DEVICES = {
			DESKTOP: 'desktop',
			IPHONE: 'iphone',
			IPAD: 'ipad',
			ANDROID: 'android',
			UNKNOWN: 'unknown'
		},

		STATUSES = {
			CAMPAIGN_NOT_CHOSEN: new Status( 'campaign_not_chosen', 0 ),
			CAMPAIGN_CHOSEN: new Status( 'campaign_chosen', 1 ),
			BANNER_CANCELED: new Status( 'banner_canceled', 2 ),
			NO_BANNER_AVAILABLE: new Status( 'no_banner_available', 3 ),
			BANNER_CHOSEN: new Status( 'banner_chosen', 4 ),
			BANNER_LOADED_BUT_HIDDEN: new Status( 'banner_loaded_but_hidden', 5 ),
			BANNER_SHOWN: new Status( 'banner_shown', 6 ),
			BANNER_LOADER_ERROR: new Status( 'banner_loader_error', 7 )
		},

		// Until T114078 is closed, we minify banner history logs. This lookup
		// table maps from hide reason string to a numeric code.
		REASONS = {
			// Any reason not listed here will be stored as "other".
			other: 0,
			close: 1,
			waitdate: 2,
			waitimps: 3,
			waiterr: 4, // Deprecated
			belowMinEdits: 5,
			viewLimit: 6,
			'seen-fullscreen': 7,
			'cookies-disabled': 8,
			donate: 9,
			cookies: 10,
			seen: 11,
			empty: 12,
			waitnorestart: 13, // Deprecated
			waitnostorage: 14, // TODO Switch impression diet to use just noStorage?
			namespace: 15,
			noStorage: 16,
			requestedBannerNotAvailable: 17,
			jsonParamError: 18,
			bannerSequenceEmptyStep: 19,
			bannerSequenceAllStepsSkipped: 20
		};

	function Status( key, code ) {
		this.key = key;
		this.code = code;
	}

	/**
	 * Get a code for the general category the user's device is in.
	 */
	function getDeviceCode() {
		var ua;

		// If we're on the desktop site, all your device are belong to DESKTOP
		// TODO Fix this! Skin != device. Maybe screen width? P.S. Talk to users.
		// TODO Make a test for this; it could stop working without notice.
		// See also https://phabricator.wikimedia.org/T71366
		if ( mw.config.get( 'skin' ) !== 'minerva' ) {
			return DEVICES.DESKTOP;
		}

		ua = navigator.userAgent;

		if ( ua.match( /iphone/i ) ) {
			return DEVICES.IPHONE;
		}
		if ( ua.match( /ipad/i ) ) {
			return DEVICES.IPAD;
		}
		if ( ua.match( /android/i ) ) {
			return DEVICES.ANDROID;
		}
		return DEVICES.UNKNOWN;
	}

	/**
	 * Set the data we need for campaign/banner selection and display, and for
	 * recording what happened. Here we load up all the data that's available
	 * initially.
	 */
	function setInitialData() {

		// Keep existing properties of state.urlParams, which may be set by tests
		var urlParams = $.extend( state.urlParams, ( new mw.Uri() ).query );

		state.data.anonymous = ( mw.config.get( 'wgUserName' ) === null );
		state.data.project = mw.config.get( 'wgNoticeProject' );
		state.data.db = mw.config.get( 'wgDBname' );

		// All of the following may be overridden by URL parameters (including
		// language, which can be overridden by uselang).
		state.data.uselang = mw.config.get( 'wgUserLanguage' );
		state.data.device = urlParams.device || getDeviceCode();

		// data.country should already have been set
		state.data.country = urlParams.country || state.data.country ||
			UNKNOWN_COUNTRY_CODE;

		// Some parameters should get through even if they have falsey values
		state.data.debug = ( urlParams.debug !== undefined );

		state.data.randomcampaign = urlParams.randomcampaign !== undefined ?
			urlParams.randomcampaign : Math.random();

		state.data.randombanner = urlParams.randombanner !== undefined ?
			urlParams.randombanner : Math.random();

		state.data.recordImpressionSampleRate =
			urlParams.recordImpressionSampleRate !== undefined ?
				urlParams.recordImpressionSampleRate :
				mw.config.get( 'wgCentralNoticeSampleRate' );

		state.data.impressionEventSampleRate =
			urlParams.impressionEventSampleRate !== undefined ?
				urlParams.impressionEventSampleRate :
				mw.config.get( 'wgCentralNoticeImpressionEventSampleRate' );

		// Legacy code exposed urlParams at mw.centralNotice.data.getVars.
		// TODO Is this still needed? Maybe deprecate?
		state.data.getVars = urlParams;
	}

	function setTestingBannerData() {
		state.data.campaign = state.urlParams.campaign;
		state.data.banner = state.urlParams.banner;
		state.data.testingBanner = true;
	}

	function setStatus( s, reason ) {
		var reasonCodeStr = reason ? ( '.' + state.lookupReasonCode( reason ) ) : '';
		status = s;
		state.data.status = s.key;
		state.data.statusCode = s.code.toString() + reasonCodeStr;
	}

	/**
	 * State object (intended for access from within this RL module)
	 */
	state = mw.centralNotice.internal.state = {

		STATUSES: STATUSES,

		// The following four properties are only exposed so QUnit
		// tests can manipulate data

		/**
		 * @private
		 */
		urlParams: {},

		/**
		 * @private
		 */
		data: {},

		/**
		 * @private
		 */
		campaign: null,

		/**
		 * @private
		 */
		banner: null,

		/**
		 * Call this with geo data before calling setUp() or
		 * setUpForTestingBanner().
		 */
		setGeoData: function ( geo ) {
			state.data.country = ( geo && geo.country );
		},

		/**
		 * Call this before calling setUp() or setUpForTestingBanner()
		 * if valid geo data is not available.
		 */
		setInvalidGeoData: function () {
			state.data.country = UNKNOWN_COUNTRY_CODE;
		},

		setUp: function () {
			setInitialData();
			setStatus( STATUSES.CAMPAIGN_NOT_CHOSEN );
		},

		setUpForTestingBanner: function () {
			setInitialData();

			// Load banner and campaign URL params into data
			setTestingBannerData();

			// For testing, we'll set the status to what it normally is after
			// a banner is chosen
			setStatus( STATUSES.BANNER_CHOSEN );
		},

		/**
		 * Return the data object, with data needed for campaign and banner
		 * selection, and data about the state of the selection process. The
		 * returned object should be considered read-only; i.e., don't modify
		 * it.
		 */
		getData: function () {
			return state.data;
		},

		/**
		 * Get a copy of the data object. If cleanForLogging is true, remove
		 * properties or those that are not strings, numbers or booleans,
		 * to provide an object with properties appropriate to send as URL
		 * params (for legacy impression recordings) or as an impression event
		 * (via EventLogging).
		 *
		 * Note: Coordinate with CentralNoticeImpression schema; remove any
		 * data properties that do not conform to that schema.
		 *
		 * @param {boolean} cleanForLogging
		 */
		getDataCopy: function ( cleanForLogging ) {

			var dataCopy = $.extend( true, {}, state.data );

			if ( cleanForLogging ) {
				delete dataCopy.getVars;
				delete dataCopy.mixins;
				delete dataCopy.tests;
				delete dataCopy.reducedBucket;
			}

			return dataCopy;
		},

		setCampaign: function ( c ) {
			var prop, i,
				category,
				campaignCategory = null;

			state.campaign = c;
			state.data.campaign = state.campaign.name;
			setStatus( STATUSES.CAMPAIGN_CHOSEN );

			// Provide the names of mixins enabled in this campaign
			// Note: Object.keys() not available in IE8
			// Another note: We expose an object to make testing for a specific
			// mixin easy in IE8, too
			state.data.mixins = {};
			for ( prop in state.campaign.mixins ) {
				if ( state.campaign.mixins.hasOwnProperty( prop ) ) {
					state.data.mixins[ prop ] = true;
				}
			}

			// Set the campaignCategory property if all the banners in this
			// campaign have the same category. This is necessary so we can
			// use category even if a banner has not been chosen. In all normal
			// cases, this won't be a problem.
			// TODO Eventually, category should be a property of campaigns,
			// not banners.
			for ( i = 0; i < state.campaign.banners.length; i++ ) {

				category = state.campaign.banners[ i ].category;

				if ( campaignCategory === null ) {
					campaignCategory = category;
				} else if ( campaignCategory !== category ) {
					campaignCategory =
						CAMPAIGN_CATEGORY_FOR_MIXED_BANNER_CATEGORIES;
					break;
				}
			}

			state.data.campaignCategory = campaignCategory;

			// Is the campaign category among the categories configured to use
			// legacy mechanisms?
			state.data.campaignCategoryUsesLegacy = (
				$.inArray(
					campaignCategory,
					mw.config.get( 'wgCentralNoticeCategoriesUsingLegacy' )
				) !== -1
			);
		},

		getCampaign: function () {
			return state.campaign;
		},

		setBanner: function ( b ) {
			state.banner = b;
			state.data.banner = state.banner.name;
			state.data.bannerCategory = state.banner.category;
			setStatus( STATUSES.BANNER_CHOSEN );
		},

		setBucket: function ( bucket ) {
			state.data.bucket = bucket;
		},

		setReducedBucket: function ( reducedBucket ) {
			state.data.reducedBucket = reducedBucket;
		},

		setBannersNotGuaranteedToDisplay: function () {
			state.data.bannersNotGuaranteedToDisplay = true;
		},

		cancelBanner: function ( reason ) {
			state.data.bannerCanceledReason = reason;
			setStatus( STATUSES.BANNER_CANCELED, reason );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;
		},

		isBannerCanceled: function () {
			return status === STATUSES.BANNER_CANCELED;
		},

		isBannerShown: function () {
			return status === STATUSES.BANNER_SHOWN;
		},

		setNoBannerAvailable: function () {
			setStatus( STATUSES.NO_BANNER_AVAILABLE );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = 'empty';
		},

		setRequestedBannerNotAvailable: function ( bannerName ) {
			state.data.requestedBanner = bannerName;
			setStatus( STATUSES.NO_BANNER_AVAILABLE, 'requestedBannerNotAvailable' );
		},

		setBannerLoadedButHidden: function ( reason ) {
			state.data.bannerLoadedButHiddenReason = reason;
			setStatus( STATUSES.BANNER_LOADED_BUT_HIDDEN, reason );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;
		},

		setAlterFunctionMissing: function () {
			state.data.alterFunctionMissing = true;
		},

		setBannerShown: function () {
			setStatus( STATUSES.BANNER_SHOWN );

			// Legacy field for Special:RecordImpression
			state.data.result = 'show';
		},

		/**
		 * Sets banner_count, a legacy field for Special:RecordImpression
		 */
		setBannerCount: function ( bannerCount ) {
			// eslint-disable-next-line camelcase
			state.data.banner_count = bannerCount;
		},

		setRecordImpressionSampleRate: function ( rate ) {
			state.data.recordImpressionSampleRate = rate;
		},

		setImpressionEventSampleRate: function( rate ) {
			state.data.impressionEventSampleRate = rate;
		},

		/**
		 * Set a banner loader error, with an optional message
		 * @param {string} [msg]
		 */
		setBannerLoaderError: function ( msg ) {
			if ( msg ) {
				state.data.errorMsg = msg;
			}
			setStatus( STATUSES.BANNER_LOADER_ERROR );
		},

		/**
		 * Register that the current page view is included in a test.
		 *
		 * @param {string} identifier A string to identify the test. Should not contain
		 *   commas.
		 */
		registerTest: function ( identifier ) {
			var tests = state.data.tests = state.data.tests || [];

			// Add if it isn't already registered.
			if ( $.inArray( identifier, tests ) === -1 ) {
				tests.push( identifier );

				if ( tests.length === 1 ) {
					state.data.testIdentifiers = identifier;
				} else {
					state.data.testIdentifiers += ',' + identifier;
				}
			}
		},

		/**
		 * Set a string with information for debugging. (All strings set here will be
		 * added to state data).
		 *
		 * @param {string} str A string with the debugging information; should not
		 *   contain pipe characters ('|').
		 */
		setDebugInfo: function ( str ) {
			if ( !state.data.debugInfo ) {
				state.data.debugInfo = str;
			} else {
				state.data.debugInfo += '|' + str;
			}
		},

		lookupReasonCode: function ( reasonName ) {
			if ( reasonName in REASONS ) {
				return REASONS[ reasonName ];
			}
			return REASONS.other;
		}
	};
}( jQuery, mediaWiki ) );
