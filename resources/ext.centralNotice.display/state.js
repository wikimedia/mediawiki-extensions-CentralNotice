/**
 * Stores data for campaign/banner processing and data related to the state of
 * that processing. Provides cn.internal.state and cn.data.
 *
 * Note: Coordinate with CentralNoticeImpression schema; all data properties must
 * either be in the schema and have the correct data type, or must be removed by
 * calling getDataCopy( true ).
 */
( function () {

	var state,
		status,
		config = require( './config.json' ),
		impressionEventSampleRateOverridden = false,

		UNKNOWN_GEO_CODE = 'XX',

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
			BANNER_LOADER_ERROR: new Status( 'banner_loader_error', 7 ),
			CHOICE_DATA_STALE: new Status( 'choice_data_stale', 8 )
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
		var urlParams = $.extend( state.urlParams, ( new mw.Uri() ).query ),
			impressionEventSampleRateFromUrl;

		state.data.anonymous = ( mw.config.get( 'wgUserName' ) === null );
		state.data.project = mw.config.get( 'wgNoticeProject' );
		state.data.db = mw.config.get( 'wgDBname' );

		// All of the following may be overridden by URL parameters (including
		// language, which can be overridden by uselang).
		state.data.uselang = mw.config.get( 'wgUserLanguage' );
		state.data.device = urlParams.device || getDeviceCode();

		// data.country already have been set
		state.data.country = urlParams.country || state.data.country || UNKNOWN_GEO_CODE;

		// data.region should also already have been set, though it might be empty
		state.data.region = urlParams.region ||
			( state.data.region !== undefined ? state.data.region : false ) ||
			UNKNOWN_GEO_CODE;

		// debug should be set no matter what
		state.data.debug = ( urlParams.debug !== undefined );

		// The following four parameters should be used if they're numbers
		state.data.randomcampaign =
			numericalUrlParamOrVal( urlParams.randomcampaign, Math.random() );

		state.data.randombanner =
			numericalUrlParamOrVal( urlParams.randombanner, Math.random() );

		state.data.recordImpressionSampleRate = numericalUrlParamOrVal(
			urlParams.recordImpressionSampleRate,
			mw.config.get( 'wgCentralNoticeSampleRate' )
		);

		// In the case of impressionEventSampleRate, also remember if it's overridden by
		// a URL param
		impressionEventSampleRateFromUrl =
			numericalUrlParamOrVal( urlParams.impressionEventSampleRate, null );

		if ( impressionEventSampleRateFromUrl !== null ) {
			state.data.impressionEventSampleRate = impressionEventSampleRateFromUrl;
			impressionEventSampleRateOverridden = true;

		} else {
			state.data.impressionEventSampleRate =
				mw.config.get( 'wgCentralNoticeImpressionEventSampleRate' );
		}

		// Legacy code exposed urlParams at mw.centralNotice.data.getVars.
		// TODO Is this still needed? Maybe deprecate?
		state.data.getVars = urlParams;

		// Contains list of available campaigns
		state.data.availableCampaigns = [];

		// Contains list of campaigns statuses
		state.data.campaignStatuses = [];
	}

	function numericalUrlParamOrVal( urlParam, val ) {
		var urlParamAsFloat = parseFloat( urlParam );
		return !isNaN( urlParamAsFloat ) ? urlParamAsFloat : val;
	}

	function setTestingBannerData() {
		state.data.campaign = state.urlParams.campaign;
		state.data.banner = state.urlParams.banner;
		state.data.testingBanner = true;
		state.data.preview = ( state.urlParams.preview !== undefined );
	}

	function setStatus( s, reason ) {
		var cIndex, reasonCodeStr = reason ? ( '.' + state.lookupReasonCode( reason ) ) : '';
		status = s;
		state.data.status = s.key;
		state.data.statusCode = s.code.toString() + reasonCodeStr;
		// Update campaign status (only if there is a campaign set)
		if ( state.data.campaign && state.data.campaignStatuses.length ) {
			// Find campaign object index by name
			cIndex = state.data.campaignStatuses.map( function ( c ) {
				return c.campaign;
			} ).indexOf( state.data.campaign );
			// We don't need to check the cIndex since we know the campaign is on the list
			state.data.campaignStatuses[ cIndex ].statusCode = state.data.statusCode;
		}
	}

	/**
	 * Fails currently selected campaign by removing it from availableCampaigns array
	 * The function should not be called until after setAvailableCampaigns and setCampaign
	 * have been called
	 */
	function failCampaign() {
		var cIndex;
		// Remove campaign from available campaigns list
		// Find campaign object index by name
		cIndex = state.data.availableCampaigns.map( function ( c ) {
			return c.name;
		} ).indexOf( state.data.campaign );
		// We don't need to check the cIndex since we know the campaign is on the list
		state.data.availableCampaigns.splice( cIndex, 1 );
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
			if ( geo ) {
				state.data.country = geo.country;
				state.data.region = geo.region;
			}
		},

		/**
		 * Call this before calling setUp() or setUpForTestingBanner()
		 * if valid geo data is not available.
		 */
		setInvalidGeoData: function () {
			state.data.country = UNKNOWN_GEO_CODE;
			state.data.region = UNKNOWN_GEO_CODE;
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
		 * @param {boolean} prepareForLogging
		 */
		getDataCopy: function ( prepareForLogging ) {

			var dataCopy = $.extend( true, {}, state.data );

			if ( prepareForLogging ) {
				delete dataCopy.getVars;
				delete dataCopy.mixins;
				delete dataCopy.tests;
				delete dataCopy.reducedBucket;
				delete dataCopy.availableCampaigns;
				// Serialized as JSON string for b/c, later, when we switch fully to EventLogging
				// instead of the custom beacon/impression, the serialization could be removed
				dataCopy.campaignStatuses = JSON.stringify( dataCopy.campaignStatuses );
			}

			return dataCopy;
		},

		/**
		 * Set campaigns available
		 */
		setAvailableCampaigns: function ( availableCampaigns ) {
			state.data.availableCampaigns = availableCampaigns;
		},

		/**
		 * Sets the campaign that will be used by the state as current
		 * @param {Object} c the campaign object, must be from the list of available campaigns
		 */
		setCampaign: function ( c ) {
			var prop, i,
				category,
				campaignCategory = null,
				check;

			check = state.data.availableCampaigns.map( function ( availableCampaign ) {
				return availableCampaign.name;
			} ).indexOf( c.name );

			if ( check === -1 ) {
				throw new Error( 'The campaign being set is not in available campaigns list' );
			}

			// Resetting previously set flags (if any)
			delete state.data.result;
			delete state.data.reason;
			delete state.data.bannerCanceledReason;

			state.campaign = c;
			state.data.campaign = state.campaign.name;

			// Push to campaign statuses array
			state.data.campaignStatuses.push( {
				statusCode: null,
				campaign: state.data.campaign, // name
				bannersCount: state.campaign.banners.length
			} );

			setStatus( STATUSES.CAMPAIGN_CHOSEN );

			// Provide the names of mixins enabled in this campaign
			state.data.mixins = {};
			for ( prop in state.campaign.mixins ) {
				if ( Object.hasOwnProperty.call( state.campaign.mixins, prop ) ) {
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
			state.data.campaignCategoryUsesLegacy =
				config.categoriesUsingLegacy.indexOf( campaignCategory ) !== -1;
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

		/**
		 * As a side effect this will remove the currently chosen campaign from the list
		 * of available campaigns, so it can't be chosen again
		 *
		 * @param reason
		 */
		cancelBanner: function ( reason ) {
			state.data.bannerCanceledReason = reason;
			setStatus( STATUSES.BANNER_CANCELED, reason );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;

			failCampaign();
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

		/**
		 * Sets minimal impression sample rate, the highest rate set will be used
		 */
		setMinRecordImpressionSampleRate: function ( rate ) {
			// Update rate only if supplied rate is higher than current one
			if ( rate > state.data.recordImpressionSampleRate ) {
				state.data.recordImpressionSampleRate = rate;
			}
		},

		/**
		 * Sets minimal impression event sample rate, the highest rate set will be used
		 * (unless it was overridden by a URL parameter, in which that takes precedence).
		 */
		setMinImpressionEventSampleRate: function ( rate ) {
			if (
				!impressionEventSampleRateOverridden &&
				// Update rate only if supplied rate is higher than current one
				rate > state.data.impressionEventSampleRate
			) {
				state.data.impressionEventSampleRate = rate;
			}
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
		 * Set a status indicating stale choice data was received.
		 */
		setChoiceDataStale: function () {
			setStatus( STATUSES.CHOICE_DATA_STALE );
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
			if ( tests.indexOf( identifier ) === -1 ) {
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
		},

		/**
		 * Returns number of campaigns were chosen
		 * @returns {number}
		 */
		countCampaignsAttempted: function () {
			return state.data.campaignStatuses.length;
		}
	};
}() );
