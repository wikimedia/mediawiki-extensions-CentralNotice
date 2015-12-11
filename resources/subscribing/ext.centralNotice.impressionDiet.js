/*
 * Impression diet mixin. Provides knobs to cap the number of impressions
 * each user will see.
 *
 * For campaigns in a campaign category using legacy mechanisms, this is
 * compatible with MediaWiki:CentralNotice/Resources/BannerShowHideCountDate.js.
 *
 * Such campaigns using the onwiki showhide code can be migrated without donors
 * seeing any extra impressions.
 *
 * For such campaigns, determine if a banner should be hidden or displayed based
 * on the contents of the cookie named mixinParams.cookieName and
 * mixinParams.cookieName + `-wait`.
 *
 * For other campaigns, store data using ext.centralNotice.kvStore.
 *
 * In general, showing a banner entails that a certain number of impressions
 * have already occurred in a time period. Parameter documentation is provided
 * in the CentralNotice campaign management UI.
 *
 * Banner may be forced if URL parameter force = 1
 * Counters may be reset if URL parameter reset = 1
 *
 * Flow chart: https://commons.wikimedia.org/wiki/File:CentralNotice_-_wait_cookie_code_flow.png
 * (FIXME: update ^^ with new parameter names)
 *
 * TODO: Deprecate cookies and switch everything to KV storage.
 */
( function ( $, mw ) {
	'use strict';

	var cn = mw.centralNotice,
		mixin = new cn.Mixin( 'impressionDiet' ),
		useCookies,
		identifier,

		/**
		 * Object with data used to determine whether to hide the banner
		 * Properties:
		 *   seenCount:     Total number of impressions seen by this user
		 *   waitCount:     Number of impressions we've waited for on this cycle
		 *   waitUntil:     Timestamp (ms) until we can show another banner
		 *   waitSeenCount: Number of impressions seen this cycle
		 */
		counts,

		IMPRESSION_DIET_KV_STORE_KEY = 'impression_diet',
		WAIT_COOKIE_SUFFIX = '-wait',

		// Time to store impression-counting data, in days
		COUNTS_STORAGE_TTL = 365;

	mixin.setPreBannerHandler( function( mixinParams ) {
		var forceFlag = mw.util.getParamValue( 'force' ),
			hide;

		// Only use cookies for certain campaigns on the legacy track
		// Do this here since it's needed for storageAvailable() (below)
		useCookies = cn.getDataProperty( 'campaignCategoryUsesLegacy' );

		// URL forced a banner, or banner was hidden already
		if ( forceFlag || cn.isBannerCanceled() ) {
			return;
		}

		if ( !storageAvailable() ) {
			// Hide the banner if we have no way to store counts.
			hide = 'waitnostorage';

		} else {

			// Normal code path: a means of storing counts is available

			identifier = mixinParams.cookieName;
			counts = getCounts();

			if ( counts.waitUntil < new Date().getTime()
				&& counts.waitSeenCount >= mixinParams.maximumSeen
			) {
				// We're beyond the wait period, and have nothing to do except
				// maybe start a new cycle.

				if ( mixinParams.restartCycleDelay !== 0 ) {
					// Begin a new cycle by clearing counters.
					counts.waitCount = 0;
					counts.waitSeenCount = 0;
				}
			}

			// Compare counts against campaign settings and decide whether to
			// show a banner

			if ( counts.waitSeenCount < mixinParams.maximumSeen ) {
				// You haven't seen the maximum count of banners per cycle!

				if ( counts.waitCount < mixinParams.skipInitial ) {
					// Skip initial impressions.
					hide = 'waitimps';
					// TODO: rename skippedThisCycle
					counts.waitCount += 1;
				} else {
					// Show a banner--you win!
					hide = false;
				}
			} else {
				// Wait for the next cycle to begin.
				hide = 'waitdate';
			}
		}

		if ( hide ) {
			// Hide based on the results.
			cn.internal.state.cancelBanner( hide );
		} else {
			// Count shown impression.
			// TODO: rename seenThisCycle
			counts.waitSeenCount += 1;
			counts.seenCount += 1;

			// Reset the wait timer on every impression.  The configured delay
			// is the minumum amount of time allowed between the final impression
			// and the start of the next cycle.
			//
			// TODO: rename: nextCycleAt

			counts.waitUntil = new Date().getTime() +
				( mixinParams.restartCycleDelay * 1000 );
		}

		// More bookkeeping.
		storeCounts( counts );
	} );

	function storageAvailable() {
		return useCookies ? cn.cookiesEnabled() : cn.kvStore.isAvailable();
	}

	function getZeroedCounts() {
		return {
			seenCount: 0,
			waitCount: 0,
			waitUntil: 0,
			waitSeenCount: 0
		};
	}

	/**
	 * Get running impression counts from cookie storage
	 * @returns {Object} An object containing count data.
	 */
	function getCounts() {

		var rawCookie, rawWaitCookie, waitData, kvStoreCounts;

		// Reset counters on demand
		if ( mw.util.getParamValue( 'reset' ) === '1' ) {
			return getZeroedCounts();
		}

		// All cases in which an identifier is set in the config
		if ( identifier ) {

			// When there's an identifier, we try to get a value from a cookie
			// no matter what. Even if we're not using cookies, we still need to
			// check for an existing cookie using this identifier, to transition
			// the user to KV storage.
			rawCookie = $.cookie( identifier );
			rawWaitCookie = $.cookie( identifier + WAIT_COOKIE_SUFFIX );

			// If there was a cookie value, and we're not using cookies, remove
			// both cookies. In this case, counts will be stored in the KV store
			// later on.
			if ( rawCookie && !useCookies ) {
				$.removeCookie( identifier, { path: '/' } );
				$.removeCookie( identifier + WAIT_COOKIE_SUFFIX, { path: '/' } );
			}

			// If there was a cookie, no matter what, we get counts from there
			if ( rawCookie ) {

				// Parse count data from the raw cookie. This should provide
				// zeroed counts if the cookies are malformed or the second one
				// isn't there.
				waitData = ( rawWaitCookie || '' ) .split( /[|]/ );

				return {
					seenCount: parseInt( rawCookie ) || 0,
					waitCount: parseInt( waitData[0] ) || 0,
					waitUntil: parseInt( waitData[1] ) || 0,
					waitSeenCount: parseInt( waitData[2] ) || 0
				};
			}

			// Handling cases where there was an identifier but no cookie

			// Because of a previous error deployed to WMF production, there
			// may be clients that are supposed to use cookies but are using
			// the KV store. For only those clients, we'll keep using the
			// KV store, rather than attempting a reverse migration. So, no
			// matter what, if there's an identifier, we check the KV store.
			kvStoreCounts = cn.kvStore.getItem(
				IMPRESSION_DIET_KV_STORE_KEY + '_' + identifier,
				cn.kvStore.contexts.GLOBAL
			);

			// If we are supposed to use cookies but have a KV store value,
			// keep using that (in storeCounts(), below).
			if ( kvStoreCounts && useCookies ) {
				useCookies = false;
			}

			// If we have an identifier, didn't get a cookie value (above),
			// then return either what was in the KV store, or zeroed counts,
			// regardless of whether we are supposed to use cookies (see
			// comment above about error deployed to WMF production).
			return kvStoreCounts || getZeroedCounts();
		}

		// Handling all cases where there was no identifier

		// For campaigns that use cookies, we actually needed an identifier, so
		// this is an error state.
		if ( useCookies ) {
			mw.log( 'impressionDiet mixin requires custom identifier for ' +
				'campaigns using legacy cookies.');

			return getZeroedCounts();
		}

		// Campaigns using the KV store, on the other hand, don't need an
		// identifier. In that case, we use a standard key in category context.
		// If there's no item, just return zeroed counts.
		return cn.kvStore.getItem(
			IMPRESSION_DIET_KV_STORE_KEY,
			cn.kvStore.contexts.CATEGORY
		) || getZeroedCounts();
	}

	/*
	 * Store updated counts
	 *
	 * TODO: Deprecate legacy cookie-based storage
	 */
	function storeCounts( counts ) {

		var waitData;

		if ( useCookies ) {

			// We can only store cookies if we have an identifier
			if ( identifier ) {
				waitData = counts.waitCount + '|' + counts.waitUntil + '|'
					+ counts.waitSeenCount;

				// Finish up and store results
				$.cookie( identifier, counts.seenCount,
					{ expires: COUNTS_STORAGE_TTL, path: '/' } );

				$.cookie( identifier + WAIT_COOKIE_SUFFIX, waitData,
					{ expires: COUNTS_STORAGE_TTL, path: '/' } );
			}

			// If we don't have an identifier and are supposed to use cookies,
			// we can't store the data. This is an error state, but we should
			// have already logged about it above, so do nothing.
			return;
		}

		// Campaigns using KV storage store data differently depending on
		// whether or not an identifier is set.
		// With an identifier, we use the global context
		if ( identifier ) {
			cn.kvStore.setItem(
				IMPRESSION_DIET_KV_STORE_KEY + '_' + identifier,
				counts, cn.kvStore.contexts.GLOBAL, COUNTS_STORAGE_TTL );

			return;
		}

		// No identifier? Use a standard key in category context
		cn.kvStore.setItem( IMPRESSION_DIET_KV_STORE_KEY,
			counts, cn.kvStore.contexts.CATEGORY, COUNTS_STORAGE_TTL );
	}

	// Register the mixin
	cn.registerCampaignMixin( mixin );

} )( jQuery, mediaWiki );
