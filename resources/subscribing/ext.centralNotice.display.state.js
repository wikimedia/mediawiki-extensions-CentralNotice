/**
 * Stores data for campaign/banner processing and data related to the state of
 * that processing. Provides cn.internal.state and cn.data.
 */
( function ( $, mw ) {

	var state,
		data = {},
		campaign,
		banner,

		// Cached regex to speed URL processing (see decode(), below)
		rPlus = /\+/g,

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
			CAMPAIGN_NOT_CHOSEN:      'campaign_not_chosen',
			CAMPAIGN_CHOSEN:          'campaign_chosen',
			BANNER_CANCELED:          'banner_canceled',
			NO_BANNER_AVAILABLE:      'no_banner_available',
			BANNER_CHOSEN:            'banner_chosen',
			BANNER_LOADED_BUT_HIDDEN: 'banner_loaded_but_hidden',
			BANNER_SHOWN:             'banner_shown',
		};

	/**
	 * Return an object with URL query string parameters.
	 * TODO Taken from legacy code. Is this the right way to do this?
	 * @returns {Object}
	 */
	function setURLParams() {

		document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g,
			function ( str, p1, p2 ) {
			state.urlParams[decode( p1 )] = decode( p2 );
		} );
	}

	/**
	 * '+'-replacer and try-catch wrapper for decodeURIComponent
	 * TODO Taken from legacy code. Is this the right way to do this?
	 * @param {string} s
	 * @returns {string}
	 */
	function decode( s ) {
		try {
			// decodeURIComponent can throw an exception for unknown char encodings.
			return decodeURIComponent( s.replace( rPlus, ' ' ) );
		} catch ( e ) {
			return '';
		}
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

		var urlParams = state.urlParams;

		data.anonymous = ( mw.config.get( 'wgUserName' ) === null );
		data.project = mw.config.get( 'wgNoticeProject' );
		data.db = mw.config.get( 'wgDBname' );

		// All of the following may be overridden by URL parameters (including
		// language, which can be overridden by uselang).
		data.uselang = mw.config.get( 'wgUserLanguage' );
		data.device = urlParams.device || getDeviceCode();

		// data.country may already have been set, if setInvalidGeoData() was
		// called
		data.country = urlParams.country || data.country ||
			( window.Geo && window.Geo.country ) || UNKNOWN_COUNTRY_CODE;

		// Some parameters should get through even if they have falsey values
		data.debug = urlParams.debug !== undefined ? true : false;

		data.randomcampaign = urlParams.randomcampaign !== undefined ?
			urlParams.randomcampaign : Math.random();

		data.randombanner = urlParams.randombanner !== undefined ?
			urlParams.randombanner : Math.random();

		data.recordImpressionSampleRate =
			urlParams.recordImpressionSampleRate !== undefined ?
			urlParams.recordImpressionSampleRate :
			mw.config.get( 'wgCentralNoticeSampleRate' );

		// Legacy code exposed urlParams at mw.centralNotice.data.getVars.
		// TODO Is this still needed? Maybe deprecate?
		data.getVars = urlParams;
	}

	function setTestingBannerData() {
		data.campaign = state.urlParams.campaign;
		data.banner = state.urlParams.banner;
		data.testingBanner = true;
	}

	/**
	 * State object (intended for access from within this RL module)
	 */
	state = mw.centralNotice.internal.state = {

		STATUSES: STATUSES,

		/**
		 * This is only exposed so QUnit tests can manipulate data
		 * @private
		 */
		urlParams: {},

		/**
		 * Call this before calling setUp() or setUpForTestingBanner()
		 * if window.Geo is known to be invalid.
		 */
		setInvalidGeoData: function() {
			data.country = UNKNOWN_COUNTRY_CODE;
		},

		setUp: function() {
			setURLParams();
			setInitialData();
			data.status = STATUSES.CAMPAIGN_NOT_CHOSEN;
		},

		setUpForTestingBanner: function() {
			setURLParams();
			setInitialData();

			// Load banner and campaign URL params into data
			setTestingBannerData();

			// For testing, we'll set the status to what it normally is after
			// a banner is chosen
			data.status = STATUSES.BANNER_CHOSEN;
		},

		/**
		 * Return the data object, with data needed for campaign and banner
		 * selection, and data about the state of the selection process. The
		 * returned object should be considered read-only; i.e., don't modify
		 * it.
		 */
		getData: function() {
			return data;
		},

		/**
		 * Get a copy of the data object. If cleanForURLSerialization is true,
		 * remove non-string properties.
		 * @param {boolean} cleanForURLSerialization
		 */
		getDataCopy: function( cleanForURLSerialization ) {

			var dataCopy = $.extend( true, {}, data );

			if ( cleanForURLSerialization ) {
				delete dataCopy.getVars;
			}

			return dataCopy;
		},

		setCampaign: function( c ) {
			var i,
				category,
				campaignCategory = null;

			campaign = c;
			data.campaign = campaign.name;
			data.status = STATUSES.CAMPAIGN_CHOSEN;

			// Set the campaignCategory property if all the banners in this
			// campaign have the same category. This is necessary so we can
			// use category even if a banner has not been chosen. In all normal
			// cases, this won't be a problem.
			// TODO Eventually, category should be a property of campaigns,
			// not banners.
			for ( i = 0; i < campaign.banners.length; i++ ) {

				category = campaign.banners[i].category;

				if ( campaignCategory === null ) {
					campaignCategory = category;
				} else if ( campaignCategory !== category ) {
					campaignCategory =
						CAMPAIGN_CATEGORY_FOR_MIXED_BANNER_CATEGORIES;
					break;
				}
			}

			data.campaignCategory = campaignCategory;
		},

		getCampaign: function() {
			return campaign;
		},

		setBanner: function ( b ) {
			banner = b;
			data.banner = banner.name;
			data.bannerCategory = banner.category;
			data.status = STATUSES.BANNER_CHOSEN;
		},

		setBucket: function ( bucket ) {
			data.bucket = bucket;
		},

		setBannerNotGuaranteedToDisplay: function() {
			data.bannerNotGuaranteedToDisplay = true;
		},

		cancelBanner: function( reason ) {
			data.bannerCanceledReason = reason;
			data.status = STATUSES.BANNER_CANCELED;

			// Legacy fields for Special:RecordImpression
			data.result = 'hide';
			data.reason = reason;
		},

		isBannerCanceled: function() {
			return data.status === STATUSES.BANNER_CANCELED;
		},

		setNoBannerAvailable: function() {
			data.status = STATUSES.NO_BANNER_AVAILABLE;

			// Legacy fields for Special:RecordImpression
			data.result = 'hide';
			data.reason = 'empty';
		},

		setBannerLoadedButHidden: function( reason ) {
			data.status = STATUSES.BANNER_LOADED_BUT_HIDDEN;
			data.bannerLoadedButHiddenReason = reason;

			// Legacy fields for Special:RecordImpression
			data.result = 'hide';
			data.reason = reason;
		},

		setAlterFunctionMissing: function() {
			data.alterFunctionMissing = true;
		},

		setBannerShown: function() {
			data.status = STATUSES.BANNER_SHOWN;

			// Legacy field for Special:RecordImpression
			data.result = 'show';
		},

		setRecordImpressionSampleRate: function( rate ) {
			data.recordImpressionSampleRate = rate;
		}
	};
} )(  jQuery, mediaWiki );