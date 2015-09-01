/**
 * Stores data for campaign/banner processing and data related to the state of
 * that processing. Provides cn.internal.state and cn.data.
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
			CAMPAIGN_NOT_CHOSEN:      new Status( 'campaign_not_chosen', 0 ),
			CAMPAIGN_CHOSEN:          new Status( 'campaign_chosen', 1 ),
			BANNER_CANCELED:          new Status( 'banner_canceled', 2 ),
			NO_BANNER_AVAILABLE:      new Status( 'no_banner_available', 3 ),
			BANNER_CHOSEN:            new Status( 'banner_chosen', 4 ),
			BANNER_LOADED_BUT_HIDDEN: new Status( 'banner_loaded_but_hidden', 5 ),
			BANNER_SHOWN:             new Status( 'banner_shown', 6 )
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
		if ( mw.config.get('skin') !== 'minerva' ) {
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

		// data.country may already have been set, if setInvalidGeoData() was
		// called
		state.data.country = urlParams.country || state.data.country ||
			( window.Geo && window.Geo.country ) || UNKNOWN_COUNTRY_CODE;

		// Some parameters should get through even if they have falsey values
		state.data.debug = urlParams.debug !== undefined ? true : false;

		state.data.randomcampaign = urlParams.randomcampaign !== undefined ?
			urlParams.randomcampaign : Math.random();

		state.data.randombanner = urlParams.randombanner !== undefined ?
			urlParams.randombanner : Math.random();

		state.data.recordImpressionSampleRate =
			urlParams.recordImpressionSampleRate !== undefined ?
			urlParams.recordImpressionSampleRate :
			mw.config.get( 'wgCentralNoticeSampleRate' );

		// Legacy code exposed urlParams at mw.centralNotice.data.getVars.
		// TODO Is this still needed? Maybe deprecate?
		state.data.getVars = urlParams;
	}

	function setTestingBannerData() {
		state.data.campaign = state.urlParams.campaign;
		state.data.banner = state.urlParams.banner;
		state.data.testingBanner = true;
	}

	function setStatus( s, reasonCode ) {
		var reasonCodeStr = reasonCode ? '.' + reasonCode : '';
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
		 * Call this before calling setUp() or setUpForTestingBanner()
		 * if window.Geo is known to be invalid.
		 */
		setInvalidGeoData: function() {
			state.data.country = UNKNOWN_COUNTRY_CODE;
		},

		setUp: function() {
			setInitialData();
			setStatus( STATUSES.CAMPAIGN_NOT_CHOSEN );
		},

		setUpForTestingBanner: function() {
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
		getData: function() {
			return state.data;
		},

		/**
		 * Get a copy of the data object. If cleanForURLSerialization is true,
		 * remove non-string properties.
		 * @param {boolean} cleanForURLSerialization
		 */
		getDataCopy: function( cleanForURLSerialization ) {

			var dataCopy = $.extend( true, {}, state.data );

			if ( cleanForURLSerialization ) {
				delete dataCopy.getVars;
			}

			return dataCopy;
		},

		setCampaign: function( c ) {
			var i,
				category,
				campaignCategory = null;

			state.campaign = c;
			state.data.campaign = state.campaign.name;
			setStatus( STATUSES.CAMPAIGN_CHOSEN );

			// Set the campaignCategory property if all the banners in this
			// campaign have the same category. This is necessary so we can
			// use category even if a banner has not been chosen. In all normal
			// cases, this won't be a problem.
			// TODO Eventually, category should be a property of campaigns,
			// not banners.
			for ( i = 0; i < state.campaign.banners.length; i++ ) {

				category = state.campaign.banners[i].category;

				if ( campaignCategory === null ) {
					campaignCategory = category;
				} else if ( campaignCategory !== category ) {
					campaignCategory =
						CAMPAIGN_CATEGORY_FOR_MIXED_BANNER_CATEGORIES;
					break;
				}
			}

			state.data.campaignCategory = campaignCategory;
		},

		getCampaign: function() {
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

		setBannerNotGuaranteedToDisplay: function() {
			state.data.bannerNotGuaranteedToDisplay = true;
		},

		cancelBanner: function( reason, reasonCode ) {
			state.data.bannerCanceledReason = reason;
			setStatus( STATUSES.BANNER_CANCELED, reasonCode );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;
		},

		isBannerCanceled: function() {
			return status === STATUSES.BANNER_CANCELED;
		},

		setNoBannerAvailable: function() {
			setStatus( STATUSES.NO_BANNER_AVAILABLE );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = 'empty';
		},

		setBannerLoadedButHidden: function( reason, reasonCode ) {
			state.data.bannerLoadedButHiddenReason = reason;
			setStatus( STATUSES.BANNER_LOADED_BUT_HIDDEN, reasonCode );

			// Legacy fields for Special:RecordImpression
			state.data.result = 'hide';
			state.data.reason = reason;
		},

		setAlterFunctionMissing: function() {
			state.data.alterFunctionMissing = true;
		},

		setBannerShown: function() {
			setStatus( STATUSES.BANNER_SHOWN );

			// Legacy field for Special:RecordImpression
			state.data.result = 'show';
		},

		/**
		 * Sets banner_count, a legacy field for Special:RecordImpression
		 */
		setBannerCount: function ( bannerCount ) {
			state.data.banner_count = bannerCount;
		},

		setRecordImpressionSampleRate: function( rate ) {
			state.data.recordImpressionSampleRate = rate;
		}
	};
} )(  jQuery, mediaWiki );
