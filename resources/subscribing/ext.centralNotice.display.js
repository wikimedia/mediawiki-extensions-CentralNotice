/**
 * CentralNotice display module.
 *
 * Handles campaign and banner selection, execution of campaign mixin code, and
 * banner display. Provides an API to be used from campaign mixins and in-banner
 * JS.
 *
 * The layout of this module is:
 *
 *     ext.centralNotice.display.js (this file): General logic, banner injection
 *         and external access points, provided on mw.centralNotice. Code here
 *         may know about and manipulate other objects in this module, but not
 *         vice versa.
 *
 *     ext.centralNotice.display.state.js: Stores data for campaign/banner
 *         processing and data related to the state of that processing. Provides
 *         cn.internal.state and cn.data.
 *
 *     ext.centralNotice.display.chooser.js: Logic for selecting a campaign and
 *         a banner (or not). Provides cn.internal.chooser.
 *
 *     ext.centralNotice.display.bucketer.js: Storage, retrieval and other
 *         processing of buckets. Provides cn.internal.bucketer.
 *
 *     ext.centralNotice.display.hide.js: Retrieves, processes and stores 'hide'
 *         cookies, which prevent banners from showing in certain circumstances.
 *         Provides cn.internal.hide.
 *
 * For an overview of how this all fits together, see
 * mw.centralNotice.reallyChooseAndMaybeDisplay() (below).
 */
( function ( $, mw ) {

	var cn, Mixin,

		// Registry of campaign-associated mixins
		campaignMixins = {},

		// For providing a jQuery.Promise to signal when a banner has loaded
		bannerLoadedDeferredObj,

		// Name of a requested banner; see cn.requestBanner(), below.
		requestedBannerName = null,

		// Maximum time to delay the record impression call, in milliseconds
		MAX_RECORD_IMPRESSION_DELAY = 250,

		// EventLogging schema name for logging impressions
		IMPRESSION_EVENT_LOGGING_SCHEMA = 'CentralNoticeImpression',

		// EventLogging schema revision. Coordinate with on-wiki schema.
		// Note: We don't register this in extension.json because we don't need the
		// client-side schema module.
		IMPRESSION_EVENT_LOGGING_SCHEMA_REVISION = 17995347;

	// TODO: make data.result options explicit via constants

	/**
	 * Class for campaign-associated mixins. Access via mw.centralNotice.Mixin.
	 */
	Mixin = function ( name ) {
		this.name = name;
	};

	Mixin.prototype.setPreBannerHandler = function ( handlerFunc ) {
		this.preBannerHandler = handlerFunc;
	};

	Mixin.prototype.setPostBannerHandler = function ( handlerFunc ) {
		this.postBannerHandler = handlerFunc;
	};

	/**
	 * Run handlers stored in the mixin property indicated by hookPropertyName,
	 * for all campaign mixins.
	 *
	 * @param {string} hookPropertyName The name of a Mixin property containing
	 *  hook handlers
	 */
	function runMixinHooks( hookPropertyName ) {
		var state = cn.internal.state;
		$.each( state.getCampaign().mixins, function ( mixinName, mixinParams ) {
			var handler;
			// Sanity check
			if ( !( mixinName in campaignMixins ) ) {
				mw.log.warn( 'Mixin ' + mixinName + ' not registered.' );
				return;
			}

			// Mixins need not handle all hooks
			if ( !( hookPropertyName in campaignMixins[ mixinName ] ) ) {
				return;
			}

			handler = campaignMixins[ mixinName ][ hookPropertyName ];

			// Another sanity check
			if ( typeof handler !== 'function' ) {
				mw.log.warn( hookPropertyName + ' for ' + mixinName + ' not a function.' );
				return;
			}

			handler( mixinParams );
		} );
	}

	function runPreBannerMixinHooks() {
		runMixinHooks( 'preBannerHandler' );
	}

	function runPostBannerMixinHooks() {
		runMixinHooks( 'postBannerHandler' );
	}

	/**
	 * Set up the legacy cn.data property using a getter, or a normal property
	 * (for browsers that don't support getters).
	 */
	function setUpDataProperty() {

		// try/catch since some browsers don't support Object.defineProperty
		// or don't support it fully
		try {
			Object.defineProperty( cn, 'data', {
				get: function () {
					return cn.internal.state.getData();
				}
			} );

			return;

		} catch ( e ) {}

		// FIXME For browsers that don't support defineProperty, we don't
		// fully respect our internal contract with the state object to
		// manage data, since we assume the object reference won't change.
		cn.data = cn.internal.state.getData();
	}

	/**
	 * Expose a promise object to be resolved when the banner is loaded.
	 */
	function setUpBannerLoadedPromise() {
		bannerLoadedDeferredObj = $.Deferred();
		cn.bannerLoadedPromise = bannerLoadedDeferredObj.promise();

		// Legacy location of the above
		// TODO Deprecate and remove
		cn.events = {};
		cn.events.bannerLoaded = cn.bannerLoadedPromise;
	}

	function fetchBanner() {

		var data = cn.internal.state.getData(),
			urlBase = new mw.Uri(
				mw.config.get( 'wgCentralNoticeActiveBannerDispatcher' )
			),

			// For Varnish purges of banner content, we ensure query param order (thus we
			// can't use the object-based facilities for params in mw.Uri).
			// Rather, we use mw.Uri only to parse the URL set in config and to
			// reconstruct the bits before the query.
			// Param order must coordinate with CdnCacheUpdateBannerLoader in php.
			urlQuery = [
				'banner=' + mw.Uri.encode( data.banner ),
				'uselang=' + mw.Uri.encode( data.uselang ),
				'debug=' + ( !!data.debug ).toString()
			];

		// If this is a preview, there might not be a campaign
		if ( data.campaign ) {
			urlQuery.unshift( 'campaign=' + mw.Uri.encode( data.campaign ) );
		}

		// Only a title param (for ugly URL format) is allowed as a param on the
		// configured banner dispatchers
		if ( urlBase.query.title ) {
			// As per mediawiki.Uri.js
			urlQuery.unshift( 'title=' + mw.util.wikiUrlencode( urlBase.query.title ) );
		}

		// Remove any other query or fragment info parsed from the configured URL
		urlBase.query = {};
		urlBase.fragment = '';

		// The returned javascript will call mw.centralNotice.insertBanner()
		// or mw.centralNotice.handleBannerLoaderError() (if an error was
		// handled on the server).
		$.ajax( {
			url: urlBase.toString() + '?' + urlQuery.join( '&' ),
			dataType: 'script',
			cache: true
		} ).fail( function ( jqXHR, status, error ) {
			cn.handleBannerLoaderError( status + ': ' + error );
		} );
	}

	function injectBannerHTML( bannerHtml ) {

		// The centralNotice div should already have been added by
		// ext.centralNotice.startUp.

		// Inject the HTML
		$( 'div#centralNotice' )
			.attr(
				'class',
				mw.html.escape( 'cn-' + cn.internal.state.getData().bannerCategory )
			)
			.prepend( bannerHtml );
	}

	/**
	 * Adds reallyRecordImpression() as the last handler for cn.recordImpressionDeferredObj,
	 * then resolves.
	 */
	function resolveRecordImpressionDeferred() {
		cn.recordImpressionDeferredObj.done( reallyRecordImpression );
		cn.recordImpressionDeferredObj.resolve();
	}

	function recordImpression() {
		var timeout,
			timeoutHasRun = false;

		if ( cn.recordImpressionDelayPromises.length === 0 ) {
			reallyRecordImpression();
			return;
		}

		// If there are promises in cn.recordImpressionDelayPromises, then
		// cn.recordImpressionDeferredObj (used in resolveRecordImpressionDeferred())
		// should already have been set.

		timeout = setTimeout( function() {
			timeoutHasRun = true;
			resolveRecordImpressionDeferred();
		}, MAX_RECORD_IMPRESSION_DELAY );

		// This function can only run once, so checking that the timeout hasn't run yet
		// should be sufficient to prevent extra record impression calls.
		$.when.apply( $, cn.recordImpressionDelayPromises ).always( function () {
			if ( !timeoutHasRun ) {
				clearTimeout( timeout );
				resolveRecordImpressionDeferred();
			}
		} );
	}

	function reallyRecordImpression() {
		var state = cn.internal.state,
			random = Math.random(),
			url, dataCopy, elBaseUrl, elData, elQueryString;

		// Legacy record impression
		if ( random <= state.getData().recordImpressionSampleRate ) {
			url = new mw.Uri( mw.config.get( 'wgCentralBannerRecorder' ) );
			dataCopy = state.getDataCopy( true );
			url.extend( dataCopy );
			sendBeacon( url.toString() );
		}

		// Impression event
		// NOTE: Coordinate with EventLogging extension!
		// Specifically, ensure that EventLoggingHooks.php always provides
		// wgEventLoggingBaseUri to JavaScript, and that the URL constructed here is
		// equivalent to the one normally sent by ext.EventLogging.core.js.
		if ( random <= state.getData().impressionEventSampleRate ) {
			elBaseUrl = mw.config.get( 'wgEventLoggingBaseUri' );

			// If this is not set, it should mean EventLogging isn't installed.
			if ( elBaseUrl ) {
				dataCopy = dataCopy || state.getDataCopy( true );

				// Coordinate with mw.eventLogging.prepare()
				elData = {
					event: dataCopy,
					revision: IMPRESSION_EVENT_LOGGING_SCHEMA_REVISION,
					schema: IMPRESSION_EVENT_LOGGING_SCHEMA,
					webHost: location.hostname,
					wiki: mw.config.get( 'wgDBname' )
				};

				// As per mw.eventLogging.makeBeaconUrl()
				elQueryString = encodeURIComponent( JSON.stringify( elData ) );
				sendBeacon( elBaseUrl + '?' + elQueryString + ';' );
			}
		}
	}

	function sendBeacon( urlStr ) {
		if ( navigator.sendBeacon ) {
			try {
				navigator.sendBeacon( urlStr );
			} catch ( e ) {}
		} else {
			setTimeout( function () {
				document.createElement( 'img' ).src = urlStr;
			}, 0 );
		}
	}

	function reallyChooseAndMaybeDisplay() {

		var chooser = cn.internal.chooser,
			bucketer = cn.internal.bucketer,
			state = cn.internal.state,
			hide = cn.internal.hide,
			campaign,
			banner;

		// This will gather initial data needed for selection and display.
		// We expose it above via a getter on the data property.
		state.setUp();

		// Because of browser limitations, and to maintain our contract among
		// components of this module, we have to do this here.
		// TODO do this some other way...
		setUpDataProperty();

		// Below, we explicitly pass information from state to other
		// internal objects, which are not allowed to have dependencies.
		// While this could be made more compact by allowing internal
		// objects to access state for themselves, disallowing it ensures
		// their scope is limited and keeps the information flow visible.

		// Choose a campaign or no campaign for this user.
		campaign = chooser.chooseCampaign(
			cn.choiceData,
			state.getData().country,
			state.getData().anonymous,
			state.getData().device,
			state.getData().randomcampaign
		);

		// Was a campaign was chosen? We might have no campaign even though
		// ChoiceData had choices, since all choices could have been
		// eliminated based on data here on the client... also, we might
		// have fallen on an unallocated block.
		if ( campaign === null ) {
			return;
		}

		// Now that we have a campaign, send some info to other objects
		state.setCampaign( campaign );
		bucketer.setCampaign( campaign );
		hide.setCategory( state.getData().campaignCategory );

		if ( cn.kvStore ) {
			cn.kvStore.setCampaignName( state.getData().campaign );
			cn.kvStore.setCategory( state.getData().campaignCategory );
		}

		// Get a bucket
		bucketer.process();
		state.setBucket( bucketer.getBucket() );
		state.setReducedBucket( bucketer.getReducedBucket() );

		// Check the hide cookie and possibly cancel the banner.
		// We do this before running pre-banner hooks so that these can count
		// stuff differently if there was a hide cookie.
		hide.processCookie();
		if ( hide.shouldHide() ) {
			state.cancelBanner( hide.getReason() );
			runPreBannerMixinHooks();
			runPostBannerMixinHooks();
			recordImpression();
			return;
		}

		runPreBannerMixinHooks();

		// Cancel banner, if that was requested by code in a pre-banner hook
		if ( state.isBannerCanceled() ) {
			runPostBannerMixinHooks();
			recordImpression();
			return;
		}

		// Choose a banner. Because of how campaign and banner settings are organized, we
		// need to check logged-in status and device again. (This seems to indicate a
		// problem with our domain model.)

		// If a specific banner has been requested from a pre-banner hook, try to choose
		// it.
		if ( requestedBannerName ) {

			banner = chooser.requestBanner(
				campaign,
				state.getData().reducedBucket,
				state.getData().anonymous,
				state.getData().device,
				requestedBannerName
			);

			if ( !banner ) {
				state.setRequestedBannerNotAvailable( requestedBannerName );
			}

		} else {
			// Otherwise, use a random number and banner weights to choose from among
			// banners available to the user in this campaign, in this bucket. (Most
			// of the time, there's only one.)
			banner = chooser.chooseBanner(
				campaign,
				state.getData().reducedBucket,
				state.getData().anonymous,
				state.getData().device,
				state.getData().randombanner
			);

			if ( !banner ) {
				state.setNoBannerAvailable();
			}
		}

		// In either of the above cases, if no banner was selected, bow out.
		if ( !banner ) {
			runPostBannerMixinHooks();
			recordImpression();
			return;
		}

		// Pass more info following banner selection
		state.setBanner( banner );

		if ( cn.kvStore ) {
			cn.kvStore.setBannerName( banner.name );
		}

		// TODO From legacy; not sure it's useful
		cn.bannerData.bannerName = banner.name;

		setUpBannerLoadedPromise();

		// Get the banner
		// The ajax response will call mw.centralNotice.insertBanner()
		fetchBanner();
	}

	/**
	 * Stuff we have to do following the call to fetch a banner (successful
	 * or not)
	 */
	function processAfterBannerFetch() {

		// If we're testing a banner, don't call Special:RecordImpression or
		// run mixin hooks.
		if ( !cn.internal.state.getData().testingBanner ) {
			runPostBannerMixinHooks();
			recordImpression();
		}
	}

	/**
	 * CentralNotice base public object, exposed as mw.centralNotice. Note:
	 * other CN modules may add properties to this object, and we add some
	 * dynamically. These additional properties are:
	 *
	 *     choiceData: An array of campaigns possibly available to this user,
	 *         along with data needed to chose one (or none). This contains
	 *         everything the server can determine ahead-of-time about campaigns
	 *         for this user. Added by ext.centralNotice.choiceData (see
	 *         the PHP class CNChoiceDataResourceLoaderModule).
	 *
	 *     data: An object with more data for campaign/banner selection and
	 *         display, and for recording what happened. Properties of this
	 *         object should be easily serializable to URL parameters (i.e.,
	 *         not objects or arrays). Note: this should be seen as read-only
	 *         for any code outside this module. No properties that code in this
	 *         module reacts to are available on mw.centralNotice.data. Also,
	 *         it will be deprecated soon. Use getDataProperty( prop ) instead.
	 *
	 *     bannerLoadedPromise: A promise that resolves when a banner is loaded.
	 *         This property is only set after a banner has been chosen.
	 *         Campaign mixins can use a postBannerMixinHook instead. Following
	 *         legacy code, we call the promise with an object containing
	 *         (almost all) the same data that is sent to
	 *         Special:RecordImpression (though this data is also now available
	 *         via mw.centralNotice.data).
	 *
	 *     events.bannerLoaded: Legacy location of bannerLoadedPromise.
	 *
	 *     kvStore: Key-value store object, added by ext.centralNotice.kvStore,
	 *         if that module has been loaded.
	 *
	 *     bannerHistoryLogger: Banner history logging feature, added by
	 *         ext.centralNotice.bannerHistoryLogger, if that module has been
	 *         loaded.
	 */
	cn = {

		/**
		 * Really insert the banner (without waiting for the DOM to be ready).
		 * Only exposed for use in tests.
		 *
		 * @private
		 */
		reallyInsertBanner: function ( bannerJson ) {

			var state = cn.internal.state,
				shownAfterLoadingBanner = true,
				bannerLoadedButHiddenReason,
				tmpData;

			// Inject the banner HTML into the DOM
			injectBannerHTML( bannerJson.bannerHtml );

			bannerLoadedDeferredObj.resolve( cn.internal.state.getData() );

			// Process legacy hook for in-banner JS that hides banners after
			// they're loaded and/or adds data to send to
			// Special:RecordImpression. Only do this if
			// bannersNotGuaranteedToDisplay is set.
			if ( state.getData().bannersNotGuaranteedToDisplay ) {
				if ( typeof cn.bannerData.alterImpressionData === 'function' ) {

					// Data from state is considered read-only. This legacy hook
					// may add a 'reason' property to the object it receives.
					// So we send only a copy of the data and check the added
					// 'reason' property.
					tmpData = state.getDataCopy();

					shownAfterLoadingBanner =
						cn.bannerData.alterImpressionData( tmpData );

					if ( !shownAfterLoadingBanner ) {
						bannerLoadedButHiddenReason = tmpData.reason || '';
						state.setBannerLoadedButHidden(
							bannerLoadedButHiddenReason
						);
					}

					// eslint-disable-next-line camelcase
					if ( tmpData.banner_count ) {
						state.setBannerCount( tmpData.banner_count );
					}

				} else {
					state.setAlterFunctionMissing();
				}
			}

			// Banner shown following load (normal scenario)
			if ( shownAfterLoadingBanner ) {
				state.setBannerShown();
			}

			processAfterBannerFetch();
		},

		/**
		 * Promises to delay the record impression call, if possible; see
		 * cn.requestRecordImpressionDelay(), below. Only exposed for use in tests.
		 *
		 * @private
		 */
		recordImpressionDelayPromises: [],

		/**
		 * For providing a jQuery.Promise to signal when the record impression call is
		 * about to be sent. (Value will be set to a new deferred object only as needed.)
		 * @private
		 */
		recordImpressionDeferredObj: null,

		/**
		 * Attachment point for other objects in this module that are not meant
		 * for outside use.
		 */
		internal: {},

		/**
		 * Call this to indicate that banners in a campaign may not always
		 * display to a user even if they're loaded, that is, that they may
		 * contain logic that prevents them from showing after they're loaded.
		 */
		setBannersNotGuaranteedToDisplay: function () {
			cn.internal.state.setBannersNotGuaranteedToDisplay();
		},

		/**
		 * Call this from the preBannerMixinHook to prevent a banner from
		 * being chosen and loaded.
		 *
		 * @param {string} reason An explanation of why the banner was canceled.
		 */
		cancelBanner: function ( reason ) {
			cn.internal.state.cancelBanner( reason );
		},

		isBannerCanceled: function () {
			return cn.internal.state.isBannerCanceled();
		},

		isBannerShown: function () {
			return cn.internal.state.isBannerShown();
		},

		/**
		 * Indicate that a banner was hidden after being loaded, and provide
		 * a reason.
		 */
		setBannerLoadedButHidden: function ( reason ) {
			cn.internal.state.setBannerLoadedButHidden( reason );
		},

		/**
		 * Set the sample rate for calling Special:RecordImpression. Default is
		 * wgCentralNoticeSampleRate. Note that Special:RecordImpression will
		 * not be called at all if a campaign was not chosen for this user.
		 */
		setRecordImpressionSampleRate: function ( rate ) {
			cn.internal.state.setRecordImpressionSampleRate( rate );
		},

		/**
		 * Set the sample rate for the logging of impression events. Default is
		 * wgCentralNoticeImpressionEventSampleRate.
		 */
		setImpressionEventSampleRate: function( rate ) {
			cn.internal.state.setImpressionEventSampleRate( rate );
		},

		/**
		 * Legacy object used by in-banner scripts to store and pass data about.
		 * Also, if a function is set on the alterImpressionData property, that
		 * function will be called after the banner HTML has been injected.
		 * Returning false from that function indicates that the banner was
		 * not actually shown. Note: this may be deprecated. If possible, use
		 * setBannerLoadedButHidden() instead.
		 */
		bannerData: {},

		/**
		 * Base class for campaign-associated mixins (defined above).
		 */
		Mixin: Mixin,

		/**
		 * Register a campaign-associated mixin to make it available for campaigns
		 * to use it. Should be called for every campaign-associated mixin.
		 *
		 * @param {mw.centralNotice.Mixin} mixin
		 */
		registerCampaignMixin: function ( mixin ) {
			campaignMixins[ mixin.name ] = mixin;
		},

		/**
		 * Select a campaign and a banner, run hooks, and maybe display a
		 * banner.
		 * Note: cn.choiceData must be set before this is called
		 */
		chooseAndMaybeDisplay: function () {

			// Make sure GeoIP info is available before processing

			// geoIP usually doesn't make background requests; however, it may
			// make one if location data wasn't retrievable from a cookie. We
			// use a callback just in case, even though most of the time, the
			// callback executes without delay.

			// TODO Take GeoIP out of CentralNotice.
			// See https://phabricator.wikimedia.org/T102848.

			mw.geoIP.getPromise()
				.fail( cn.internal.state.setInvalidGeoData )
				.done( cn.internal.state.setGeoData )
				.always( reallyChooseAndMaybeDisplay );
		},

		displayTestingBanner: function () {

			// We gather the same data as for normal banner display, plus
			// campaign and banner.
			mw.geoIP.getPromise()
				.fail( cn.internal.state.setInvalidGeoData )
				.done( cn.internal.state.setGeoData )
				.always( function () {
					cn.internal.state.setUpForTestingBanner();
					setUpDataProperty();
					setUpBannerLoadedPromise();
					fetchBanner();
				} );
		},

		insertBanner: function ( bannerJson ) {

			// Insert the banner only after the DOM is ready
			$( function () {
				cn.reallyInsertBanner( bannerJson );
			} );
		},

		/**
		 * Handle a banner loader error, with an optional message
		 * @param {string} [msg]
		 */
		handleBannerLoaderError: function ( msg ) {
			cn.internal.state.setBannerLoaderError( msg );
			bannerLoadedDeferredObj.reject( cn.internal.state.getData() );
			processAfterBannerFetch();
		},

		hideBannerWithCloseButton: function () {
			// Hide the banner element
			$( '#centralNotice' ).hide();
			cn.internal.hide.setHideWithCloseButtonCookies();
		},

		customHideBanner: function ( reason, duration ) {
			// Hide the banner element
			$( '#centralNotice' ).hide();
			cn.internal.hide.setHideCookies( reason, duration );
		},

		hideBanner: function () {
			cn.hideBannerWithCloseButton();
		},

		/**
		 * Set and store this user's bucket for the current campaign. The
		 * bucketer must be initialized first. However, code in campaign mixin
		 * hook handlers and banners can safely assume that's the case.
		 *
		 * The current bucket can be read using
		 * mw.centralNotice.getDataProperty( 'bucket' )
		 */
		setBucket: function ( bucket ) {
			cn.internal.bucketer.setBucket( bucket );
			cn.internal.state.setBucket( bucket );
			cn.internal.state.setReducedBucket( cn.internal.bucketer.getReducedBucket() );
		},

		/**
		 * Request a specific banner be displayed. This may be called before a banner
		 * has been selected (for example, from a pre-banner hook). To be shown, the
		 * banner must be among the banners assigned to the user's bucket for the
		 * selected campaign, and must be available for the user's logged in status
		 * and device. If the requested banner can't be displayed, no banner will be
		 * shown.
		 *
		 * @param {string} banner The name of the banner to request
		 */
		requestBanner: function ( banner ) {
			requestedBannerName = banner;
		},

		/**
		 * Register that the current page view is included in a test.
		 *
		 * @param {string} identifier A string to identify the test. Should not contain
		 *   commas.
		 */
		registerTest: function ( identifier ) {
			cn.internal.state.registerTest( identifier );
		},

		/**
		 * Set a string with information for debugging. (All strings set here will be
		 * sent to the server via the debugInfo parameter on the record impression call).
		 *
		 * @param {string} str A string with the debugging information; should not
		 *   contain pipe characters ('|').
		 */
		setDebugInfo: function ( str ) {
			cn.internal.state.setDebugInfo( str );
		},

		/**
		 * Request that, if possible, the record impression call be delayed until a
		 * promise is resolved. If the promise does not resolve before
		 * MAX_RECORD_IMPRESSION_DELAY milliseconds after the banner is injected,
		 * the call will be made in any case.
		 *
		 * Returns another promise that will resolve immediately before the record
		 * impression call is made.
		 *
		 * @param {jquery.Promise} promise
		 * @returns {jquery.Promise}
		 */
		requestRecordImpressionDelay: function ( promise ) {
			cn.recordImpressionDelayPromises.push( promise );
			cn.recordImpressionDeferredObj = cn.recordImpressionDeferredObj || $.Deferred();
			return cn.recordImpressionDeferredObj.promise();
		},

		/**
		 * Get the value of a property used in campaign/banner selection and
		 * display, and for recording the results of that process.
		 */
		getDataProperty: function ( prop ) {
			return cn.internal.state.getData()[ prop ];
		}
	};

	// Expose cn. Note that there are situations in which a base
	// mw.centralNotice object may already have been created by another
	// CentralNotice module. (Other CN modules sometimes have to load before
	// this one.)
	if ( mw.centralNotice === undefined ) {
		mw.centralNotice = cn;
	} else {
		$.extend( mw.centralNotice, cn );
		cn = mw.centralNotice; // Update the closured-in local variable
	}

	// Set up deprecated access points and warnings
	mw.log.deprecate(
		window, 'insertBanner', cn.insertBanner,
		'Use mw.centralNotice method instead'
	);

	mw.log.deprecate(
		window, 'hideBanner', cn.hideBanner,
		'Use mw.centralNotice method instead'
	);

	mw.log.deprecate(
		window, 'toggleNotice', cn.hideBanner,
		'Use mw.centralNotice method instead'
	);

}( jQuery, mediaWiki ) );
