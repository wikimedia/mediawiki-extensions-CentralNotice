/*
 * Banner history logger mixin. Records an event every time this campaign is
 * selected for the user (even if the banner is hidden). The log is kept in
 * LocalStorage (via CentralNotice's kvStore). A sample of logs are sent to the
 * server via EventLogging. Also allows forcing the log to be sent via
 * cn.bannerHistoryLogger.ensureLogSent().
 */
( function ( $, mw ) {

	var cn = mw.centralNotice, // Guaranteed to exist; we depend on display RL module
		bhLogger,
		mixin = new cn.Mixin( 'bannerHistoryLogger' ),
		doNotTrackEnabled = /1|yes/.test( navigator.doNotTrack ),
		waitLogNoSendBeacon,
		now = Math.round( ( new Date() ).getTime() / 1000 ),
		log,
		readyToLogDeferredObj = $.Deferred(),
		logSent = false,
		inSample,

		BANNER_HISTORY_KV_STORE_KEY = 'banner_history',

		// Maximum time (in days) that the banner history store KV store item
		// will persist if no entries are added to it.
		BANNER_HISTORY_KV_STORE_TTL = 365,
		BANNER_HISTORY_LOG_ENTRY_VERSION = 1, // Update when log format changes
		EVENT_LOGGING_SCHEMA = 'CentralNoticeBannerHistory',

		// The maximum random shift applied to timestamps, for user privacy
		TIMESTAMP_RANDOM_SHIFT_MAX = 60;

	/**
	 * Load the banner history log from KV storage
	 */
	function loadLog() {

		log = cn.kvStore.getItem(
			BANNER_HISTORY_KV_STORE_KEY,
			cn.kvStore.contexts.GLOBAL
		);

		if ( !log ) {
			log = [];
		}
	}

	/**
	 * Return a log entry about the current campaign selection event.
	 * @returns {Object}
	 */
	function makeLogEntry() {

		var data = cn.data,

			// Randomly shift timestamp +/- 0 to 10 seconds, so logs can't be
			// linked to specific Web requests. This is to strengthen user
			// privacy.
			randomTimeShift =
				Math.round( Math.random() * TIMESTAMP_RANDOM_SHIFT_MAX ) -
				( TIMESTAMP_RANDOM_SHIFT_MAX / 2 ),

			time = now + randomTimeShift,

			logEntry = {
				version: BANNER_HISTORY_LOG_ENTRY_VERSION,
				language: data.uselang,
				country: data.country,
				isAnon: data.anonymous,
				campaign: data.campaign,
				campaignCategory: data.campaignCategory,
				bucket: data.bucket,
				time: time,
				status: data.status,
				statusCode: data.statusCode,

				bannersNotGuaranteedToDisplay:
					data.bannersNotGuaranteedToDisplay ? true : false
			};

		if ( data.banner ) {
			logEntry.banner = data.banner;
		}

		if ( data.bannerCanceledReason ) {
			logEntry.bannerCanceledReason = data.bannerCanceledReason;
		}

		if ( data.bannerLoadedButHiddenReason ) {
			logEntry.bannerLoadedButHiddenReason = data.bannerLoadedButHiddenReason;
		}

		return logEntry;
	}

	/**
	 * Remove log entries older than maxEntryAge (in days) and, if necessary,
	 * remove entries to keep the total within maxEntries.
	 * @param {number} maxEntryAge
	 * @param {number} maxEntries
	 */
	function purgeOldLogEntries( maxEntryAge, maxEntries ) {
		var i = 0,
			cutoff = now - maxEntryAge * 86400;

		// If we're above the max number of entries, pare it down, starting
		// with older entries
		if ( log.length > maxEntries ) {
			log = log.slice( 0 - maxEntries );
		}

		// Remove any remaining entries that are older than maxEntryAge
		while ( i < log.length && log[i].time < cutoff ) {
			i++;
		}
		log = log.slice( i );
	}

	/**
	 * Store the contents of the log variable in kvStorage
	 */
	function storeLog() {
		cn.kvStore.setItem(
			BANNER_HISTORY_KV_STORE_KEY,
			log,
			cn.kvStore.contexts.GLOBAL,
			BANNER_HISTORY_KV_STORE_TTL
		);
	}

	/**
	 * Return an object with data for EventLogging.
	 *
	 * We scrunch the data as small as possible due to the WMF infrastructure's
	 * EventLogging payload limit.
	 *
	 * @param {number} rate The sampling rate used
	 * @returns {Object}
	 */
	function makeEventLoggingData( rate ) {

		var elData = {},
			kvError = cn.kvStore.getError(),
			i, logEntry, elLogEntry;

		// Log ID: should be generated before this is called, and should not be
		// persisted anywhere on the client (see below).
		elData.i = bhLogger.id;

		// sample rate
		if ( rate ) {
			elData.r = rate;
		}

		// if applicable, the message from any kv store error
		if ( kvError ) {
			elData.e = kvError.message;
			return elData;
		}

		// total log length
		elData.n = log.length;
		elData.l = [];

		// Add log entries, starting with the most recent ones, until the EL
		// URL is too big, or we reach the end of the log.
		i = log.length - 1;

		while ( i >= 0 ) {
			logEntry = log[i];

			elLogEntry = [
				logEntry.banner || '',
				logEntry.campaign,
				logEntry.time,
				logEntry.statusCode
			];

			elData.l.unshift ( elLogEntry.join( '|' ) );

			if ( !checkEventLoggingURLSize( elData ) ) {
				elData.l.shift();
				break;
			}

			i--;
		}

		return elData;
	}

	/**
	 * Send the log. If sendBeacon is available, send normally via EventLogging.
	 * If not, use $.ajax with a timeout as per waitLogNoSendBeacon. FIXME: In
	 * that case, we also construct the EventLogging URL ourselves.
	 *
	 * Returns a promise that resolves as soon as the log is sent for users
	 * with sendBeacon. For users without sendBeacon, if the log is sent before
	 * the timeout, the promise resolves, otherwise it rejects.
	 *
	 * Note: Do Not Track is already handled at this module's entry points, as
	 * well as by EventLogging.
	 *
	 * @param elData Event log data
	 * @returns {jQuery.Promise}
	 */
	function sendLog( elData ) {
		var deferred = $.Deferred(),
			elPromise;

		// If the browser has sendBeacon, use EventLogging and resolve however
		// it does.
		if ( navigator.sendBeacon ) {
			elPromise = mw.eventLog.logEvent( EVENT_LOGGING_SCHEMA, elData );

		} else {

			// No sendBeacon? Do an ajax call and set a time limit to wait
			// for it to return.
			setTimeout( function () {
				deferred.reject();
			}, waitLogNoSendBeacon );

			elPromise = $.ajax( {
				url: makeEventLoggingURL( elData ),
				timeout: waitLogNoSendBeacon,
				method: 'POST'
			} );
		}

		elPromise.then( function () {
			deferred.resolve();
		}, function () {
			deferred.reject();
		} );

		return deferred.promise();
	}

	/**
	 * Check the EventLogging URL we'd get from this data isn't too big. Here
	 * we copy some of the same processes done by ext.eventLogging.
	 *
	 * FIXME This is a temporary measure!
	 *
	 * @returns {boolean} true if the EL payload size is OK
	 */
	function checkEventLoggingURLSize( elData ) {
		return ( makeEventLoggingURL( elData ).length <= mw.eventLog.maxUrlSize );
	}

	/**
	 * Make an EventLogging URL ourselves.
	 * FIXME This is a temporary measure!
	 */
	function makeEventLoggingURL( elData ) {
		return mw.eventLog.makeBeaconUrl( {
			event    : elData,
			revision : 14321636, // Coordinate with CentralNotice.hooks.php
			schema   : EVENT_LOGGING_SCHEMA,
			webHost  : location.hostname,
			wiki     : mw.config.get( 'wgDBname' )
		} );
	}

	// Set a function to run after a campaign is chosen and after a banner for
	// that campaign is chosen or not.
	mixin.setPostBannerHandler( function( mixinParams ) {

		waitLogNoSendBeacon = mixinParams.waitLogNoSendBeacon;

		// Do this idly to avoid browser lock-ups
		mw.requestIdleCallback( function() {

			if ( !cn.kvStore.isAvailable() ) {
				cn.kvStore.setNotAvailableError();

			} else {
				// If KV storage works here, do our stuff
				loadLog();

				// Only don't accumulate log entries if DNT is enabled... But do
				// purge old entries.
				if ( !doNotTrackEnabled ) {
					log.push( makeLogEntry() );
				}

				purgeOldLogEntries( mixinParams.maxEntryAge,
					mixinParams.maxEntries );

				storeLog();
			}

			// Bow out now if DNT
			if ( doNotTrackEnabled ) {
				return;
			}

			// Load needed resources

			// Note: we don't set the following up as RL dependencies because a
			// lot of campaign filtering happens on the client, so many users
			// see campaigns in choiceData that don't target them. If any of
			// those campaigns were to use this mixin, all those users would
			// needlessly get these dependencies. Also, they're not needed right
			// away.

			mw.loader.using( [
				'ext.eventLogging',
				'mediawiki.util',
				'mediawiki.user',
				'schema.' + EVENT_LOGGING_SCHEMA
			] ).done( function() {

				// We send back the temporary ID for all logs.
				bhLogger.id = mw.user.generateRandomSessionId();

				// URL param bannerHistoryLogRate can override rate, for debugging
				var rateParam = mw.util.getParamValue( 'bannerHistoryLogRate' ),

					rate = rateParam !== null ?
						parseFloat( rateParam ) : mixinParams.rate;

				// Send a sample to the server
				if ( Math.random() < rate ) {

					sendLog( makeEventLoggingData( rate ) ).always( function () {

						inSample = true;
						logSent = true;

						// By resolving only after sampling and possibly
						// sending the log, we ensure that a sampled log
						// would be sent first. That simplifies the logic
						// for whether to send in other circumstances.
						readyToLogDeferredObj.resolve();
					} );

				} else {

					// If not in the sample, ready right away
					readyToLogDeferredObj.resolve();
				}

			} );
		} );
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

	// Object for public access
	cn.bannerHistoryLogger = bhLogger = {

		/**
		 * A client-generated unique ID for the log (on this pageview), not
		 * persisted in the log or anywhere else between pageviews.
		 *
		 * Note: this unique ID should not be stored anywhere on the client. It
		 * should be used only within the current browsing session to flag when
		 * a banner history is associated with a donation. If a user clicks on a
		 * banner to donate, it may be passed on to the WMF's donation sites via
		 * a URL parameter. Those sites should never store it on the client.
		 */
		id: null,

		/**
		 * Send the banner history log to the server, if it wasn't sent already.
		 * @returns {jQuery.Promise}
		 */
		ensureLogSent: function() {

			var deferred = $.Deferred();

			// Bow out if DNT
			if ( doNotTrackEnabled ) {
				deferred.resolve();
				return deferred.promise();
			}

			// It's likely that this will be resolved by the time we get here
			readyToLogDeferredObj.done( function() {

				// This is included in the done() function to ensure a sampled
				// log would be sent first (see above).
				if ( logSent ) {
					deferred.resolve();
				} else {
					sendLog( makeEventLoggingData() ).then(function() {
						deferred.resolve();
					}, function () {
						deferred.reject();
					} );
				}

			} );

			return deferred.promise();
		}
	};

} )( jQuery, mediaWiki );
