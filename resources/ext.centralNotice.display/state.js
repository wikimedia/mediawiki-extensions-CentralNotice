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
		campaignAttemptsManager,
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
			// TODO Rename this status to ATTEMPTING_CAMPAIGN (T232236)
			CAMPAIGN_CHOSEN: new Status( 'campaign_chosen', 1 ),
			// TODO Rename this status to CAMPAIGN_FAILED (T232236)
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
			bannerSequenceAllStepsSkipped: 20,
			userOptOut: 21
		};

	campaignAttemptsManager = ( function () {
		var attemptedCampaignStatusesByName = {},
			hasOwn = Object.prototype.hasOwnProperty;

		return {
			setCampaignStatus: function ( c, statusCode ) {
				var statusObj;

				if ( !hasOwn.call( state.attemptedCampaignsByName, c.name ) ) {
					// If this is the first time we've seen this campaign, add it to the
					// indexes and to the list of campiagn statuses.
					statusObj = {
						statusCode: statusCode,
						campaign: c.name,
						bannersCount: c.banners.length
					};

					state.data.campaignStatuses.push( statusObj );
					attemptedCampaignStatusesByName[ c.name ] = statusObj;
					state.attemptedCampaignsByName[ c.name ] = c;

				} else {
					// Otherwise, just update the status code in campaign status object.
					// The following will update the object in state.data.campaignStatuses,
					// since the objects in that array are the same as the values of
					// attemptedCampaignStatusesByName.

					attemptedCampaignStatusesByName[ c.name ].statusCode = statusCode;
				}
			},

			getAttemptedCampaigns: function () {
				return state.data.campaignStatuses.map( function ( statusObj ) {
					return state.attemptedCampaignsByName[ statusObj.campaign ];
				} );
			}
		};
	}() );

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

		if ( /iphone/i.test( ua ) ) {
			return DEVICES.IPHONE;
		}
		if ( /ipad/i.test( ua ) ) {
			return DEVICES.IPAD;
		}
		if ( /android/i.test( ua ) ) {
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
		state.data.optedOutCampaigns = getOptedOutCampaignsForUser();

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

		// Contains list of campaigns statuses
		state.data.campaignStatuses = [];
	}

	function getOptedOutCampaignsForUser() {
		var allOptions, matches, key,
			blocked = [],
			// Note: coordinate with CampaignType::PREFERENCE_KEY_PREFIX
			regex = /^centralnotice-display-campaign-type-(.*)$/;

		if ( mw.config.get( 'wgUserName' ) === null ) {
			return [];
		}

		allOptions = $.extend( {}, mw.user.options.values );

		for ( key in allOptions ) {
			if ( !Object.prototype.hasOwnProperty.call( allOptions, key ) ) {
				continue;
			}

			matches = regex.exec( key );

			if ( Array.isArray( matches ) && matches.length === 2 && allOptions[ key ] === 0 ) {
				blocked.push( matches[ 1 ] );
			}
		}

		return blocked;
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
		var reasonCodeStr = reason ? ( '.' + state.lookupReasonCode( reason ) ) : '';
		status = s;
		state.data.status = s.key;
		state.data.statusCode = s.code.toString() + reasonCodeStr;

		// Update campaign status in the campaign attempts manager if a campaign is
		// currently being attempted.
		if ( state.data.campaign ) {
			campaignAttemptsManager.setCampaignStatus(
				state.campaign,
				state.data.statusCode
			);
		}
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
		 * @private
		 */
		attemptedCampaignsByName: {},

		/**
		 * Call this with geo data before calling setUp() or
		 * setUpForTestingBanner().
		 *
		 * @param geo
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
				delete dataCopy.optedOutCampaigns;
				// Serialized as JSON string for b/c, later, when we switch fully to EventLogging
				// instead of the custom beacon/impression, the serialization could be removed
				dataCopy.campaignStatuses = JSON.stringify( dataCopy.campaignStatuses );
			}

			return dataCopy;
		},

		/**
		 * Set a list of campaigns that may be selected for this pageview. This method
		 * will be called to update the list on each iteration of the fallback loop.
		 *
		 * @param availableCampaigns
		 */
		setAvailableCampaigns: function ( availableCampaigns ) {
			state.data.availableCampaigns = availableCampaigns;
		},

		/**
		 * Sets the campaign that is currently being attempted. This campaign will be used
		 * by state as current, and if no others are attempted, final.
		 *
		 * @param {Object} c the campaign object, from the list of available campaigns
		 */
		setAttemptingCampaign: function ( c ) {
			var prop, i, category,
				campaignCategory = null;

			// Resetting previously set flags (if any)
			delete state.data.result;
			delete state.data.reason;
			delete state.data.bannerCanceledReason;
			delete state.data.bannersNotGuaranteedToDisplay;

			state.campaign = c;
			state.data.campaign = c.name;

			// The following should ony be called _after_ state.campaign is set, otherwise
			// the status won't be included in the record of attempted campaign statuses.
			setStatus( STATUSES.CAMPAIGN_CHOSEN );

			// Provide the names of mixins enabled in this campaign. (By re-setting each time a
			// campaign is attempted, we'll get here only mixins enabled for this specific campaign.)
			// This is used in in-banner js to sanity-check that specific mixins are available and
			// enabled.
			state.data.mixins = {};
			for ( prop in c.mixins ) {
				if ( Object.hasOwnProperty.call( c.mixins, prop ) ) {
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

		/**
		 * Return the campaign currently being attempted, or null if no campaign has
		 * been attempted yet.
		 */
		getAttemptingCampaign: function () {
			return state.campaign === undefined ? null : state.campaign;
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
		 * Legacy method, deprecated. Use failCampaign().
		 *
		 * @param {string} reason
		 */
		cancelBanner: function ( reason ) {
			state.failCampaign( reason );
		},

		/**
		 * Marks a campaign as failed.
		 *
		 * @param reason
		 */
		failCampaign: function ( reason ) {
			state.data.bannerCanceledReason = reason;
			setStatus( STATUSES.BANNER_CANCELED, reason );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;
		},

		/**
		 * Legacy metod, deprecated. Use isCampaignFailed().
		 */
		isBannerCanceled: function () {
			return state.isCampaignFailed();
		},

		isCampaignFailed: function () {
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
		 *
		 * @param bannerCount
		 */
		setBannerCount: function ( bannerCount ) {
			// eslint-disable-next-line camelcase
			state.data.banner_count = bannerCount;
		},

		/**
		 * Sets minimal impression sample rate, the highest rate set will be used
		 *
		 * @param rate
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
		 *
		 * @param rate
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
		 *
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
		 *
		 * @return {number}
		 */
		countCampaignsAttempted: function () {
			return state.data.campaignStatuses.length;
		},

		getAttemptedCampaigns: function () {
			return campaignAttemptsManager.getAttemptedCampaigns();
		}
	};
}() );
