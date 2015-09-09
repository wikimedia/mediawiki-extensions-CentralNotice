/*
 * Banner history logger mixin. Records an event every time this campaign is
 * selected for the user (even if the banner is hidden). The log is kept in
 * LocalStorage (via CentralNotice's kvStore). A sample of logs are sent to the
 * server via EventLogging. Also allows triggering a call to EventLogging via
 * cn.internal.bannerHistoryLogger.sendLog().
 */
( function ( $, mw ) {

	var cn = mw.centralNotice,
		mixin = new cn.Mixin( 'bannerHistoryLogger' ),
		now = Math.round( ( new Date() ).getTime() / 1000 ),
		log,
		readyToLogPromise,

		BANNER_HISTORY_KV_STORE_KEY = 'banner_history',
		EVENT_LOGGING_SCHEMA = 'CentralNoticeBannerHistory',

		// The maximum random shift applied to timestamps, for user privacy
		TIMESTAMP_RANDOM_SHIFT_MAX = 60;

	/**
	 * Load the banner history log from KV storage
	 */
	function loadLog() {

		log = cn.getKVStorageItem(
			BANNER_HISTORY_KV_STORE_KEY,
			cn.getKVStorageContexts().GLOBAL
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
		cn.setKVStorageItem(
			BANNER_HISTORY_KV_STORE_KEY,
			log,
			cn.getKVStorageContexts().GLOBAL
		);
	}

	/**
	 * Return an object with data for EventLogging.
	 *
	 * We scrunch the data as small as possible due to the WMF infrastructure's
	 * EventLogging payload limit.
	 *
	 * @param {number} rate The sampling rate used
	 * @param {boolean} logId A unique identifier for this log. Note: this
	 *    should not be persisted anywhere on the client (see below).
	 * @returns {Object}
	 */
	function makeEventLoggingData( rate, logId ) {

		var elData = {},
			kvError = cn.getKVStorageError(),
			i, logEntry, elLogEntry;

		// sample rate
		if ( rate ) {
			elData.r = rate;
		}

		// log ID
		if ( logId ) {
			elData.i = logId;
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

			elLogEntry = {
				t: logEntry.time,
				s: logEntry.statusCode
			};

			if ( logEntry.banner ) {
				elLogEntry.b = logEntry.banner;
			} else {
				elLogEntry.c = logEntry.campaign;
			}

			elData.l.unshift ( elLogEntry );

			if ( !checkEventLoggingURLSize( elData ) ) {
				elData.l.shift();
				break;
			}

			i--;
		}

		return elData;
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

		var fullElData = {
				event    : elData,
				revision : 13172419, // Coordinate with CentralNotice.hooks.php
				schema   : EVENT_LOGGING_SCHEMA,
				webHost  : location.hostname,
				wiki     : mw.config.get( 'wgDBname' )
			},

			url = mw.eventLog.makeBeaconUrl( fullElData );

		return ( url.length <= mw.eventLog.maxUrlSize );
	}

	// Set a function to run after a campaign is chosen and after a banner for
	// that campaign is chosen or not
	mixin.setPostBannerHandler( function( mixinParams ) {

		// Nothing here needs to happen right away. At least, be sure we're not
		// doing anything until the DOM is ready.
		$( function() {

			// Load needed resources in a leisurely manner, but ahead of a
			// possible sendLog() call (expected to be called when the user
			// navigates away from the page).

			// Note: we don't set the following up as RL dependencies because a
			// lot of campaign filtering happens on the client, so many users
			// see campaigns in choiceData that don't target them. If any of
			// those campaigns were to use this mixin, all those users would
			// needlessly get these dependencies.

			readyToLogPromise = mw.loader.using( [
				'ext.eventLogging',
				'mediawiki.util',
				'mediawiki.user',
				'schema.' + EVENT_LOGGING_SCHEMA
			] );

			if ( !cn.isKVStorageAvailable() ) {
				cn.setKVStorageNotAvailableError();

			} else {
				// If KV storage works here, do our stuff
				loadLog();
				log.push( makeLogEntry() );

				purgeOldLogEntries( mixinParams.maxEntryAge,
					mixinParams.maxEntries );

				storeLog();
			}

			readyToLogPromise.done( function() {

				// URL param bannerHistoryLogRate can override rate, for debugging
				var rateParam = mw.util.getParamValue( 'bannerHistoryLogRate' ),

					rate = rateParam !== null ?
						parseFloat( rateParam ) : mixinParams.rate;

				// Send a sample to the server
				if ( Math.random() < rate ) {

					mw.eventLog.logEvent(
						EVENT_LOGGING_SCHEMA,
						makeEventLoggingData( rate )
					);
				}
			} );
		} );
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

	// Object for access by other CentralNotice RL modules
	cn.internal.bannerHistoryLogger = {

		/**
		 * Send the banner history log to the server, with a generated unique
		 * log ID. Return a promise that resolves with the logId.
		 *
		 * Note: this unique ID must not be stored anywhere on the client. It
		 * should be used only within the current browsing session to flag when
		 * a banner history is associated with a donation. If a user clicks on a
		 * banner to donate, it may be passed on to the WMF's donation sites via
		 * a URL parameter. Those sites should never store it on the client.
		 *
		 * @returns {jQuery.Promise}
		 */
		sendLog: function() {

			var deferred = $.Deferred();

			// With luck, this promise will be resoved by the time we get here
			readyToLogPromise.done( function() {

				var logId = mw.user.generateRandomSessionId();

				mw.eventLog.logEvent(
					EVENT_LOGGING_SCHEMA,
					makeEventLoggingData( null, logId )
				);

				deferred.resolve( logId );
			} );

			return deferred.promise();
		}
	};

} )( jQuery, mediaWiki );
