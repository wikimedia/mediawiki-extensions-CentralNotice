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
			hide = null,
			pastDate, waitForHideImps, waitForShowImps;

		// Only use cookies for certain campaigns on the legacy track
		// Do this here since it's needed for storageAvailable() (below)
		useCookies = cn.getDataProperty( 'campaignCategoryUsesLegacy' );

		// URL forced a banner
		if ( forceFlag ) {
			return;
		}

		// Also just show a banner if we have no way to store counts
		if ( !storageAvailable() ) {
			return;
		}

		identifier = mixinParams.cookieName;
		counts = getCounts();

		// Compare counts against campaign settings and decide whether to
		// show a banner
		pastDate = counts.waitUntil < new Date().getTime();
		waitForHideImps = counts.waitCount < mixinParams.skipInitial;
		waitForShowImps = counts.waitSeenCount < mixinParams.maximumSeen;

		if ( !pastDate ) {
			// We're still waiting for the next cycle to begin.
			hide = 'waitdate';
			counts.waitCount += 1;
		} else if ( pastDate && waitForHideImps ) {
			// We're still skipping initial impressions.
			hide = 'waitimps';
			counts.waitCount += 1;
		} else if ( pastDate && !waitForHideImps && waitForShowImps ) {
			// Show a banner!
			hide = false;
			counts.waitSeenCount += 1;
			counts.seenCount += 1;

			// For restartCycleDelay, 0 is a magic number that means, never
			// restart
			if ( ( mixinParams.restartCycleDelay !== 0) &&
				( counts.waitSeenCount >= mixinParams.maximumSeen ) ) 	{

				// We just completed a cycle. Wait to restart.
				counts.waitCount = 0;
				counts.waitSeenCount = 0;
				counts.waitUntil = new Date().getTime() +
					( mixinParams.restartCycleDelay * 1000 );
			}

		} else if ( ( mixinParams.restartCycleDelay === 0 ) &&
			( pastDate && !waitForHideImps && !waitForShowImps ) ) {

			hide = 'waitnorestart';
		}

		if ( hide === null ) {
			// All bets are off!
			hide = 'waiterr';
			counts.waitCount = 0;
			counts.waitSeenCount = 0;
			counts.waitUntil = new Date().getTime() +
				( mixinParams.restartCycleDelay * 1000 );
		}

		// Bookkeeping.
		storeCounts( counts );

		// Hide based on the results.
		if ( hide ) {
			cn.internal.state.cancelBanner( hide );
		}
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

		var rawCookie, rawWaitCookie, waitData;

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

			// If the campaign doesn't use cookies, try the KV store
			if ( !useCookies ) {

				// Since there's an identifier, uses the global KV store context.
				// If there's no data in the KV store, return zeroed counts.
				return cn.kvStore.getItem(
					IMPRESSION_DIET_KV_STORE_KEY + '_' + identifier,
					cn.kvStore.contexts.GLOBAL
				) || getZeroedCounts();
			}

			// We have an identifier, use cookies, but have no cookie value, so
			// return zeroed counts.
			return getZeroedCounts();

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
