/**
 * Impression diet mixin. Provides knobs to cap the number of impressions
 * each user will see.
 *
 * This mixin will migrate count data in cookies to kvStore, which will use
 * LocalStorage, or (if that's not available and the campaigns is in a
 * category using legacy mechanisms) fall back to a new sort of cookie.
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
 */
( function ( $, mw ) {
	'use strict';

	var identifier, multiStorageOption,
		cn = mw.centralNotice,
		mixin = new cn.Mixin( 'impressionDiet' ),

		/**
		 * Object with data used to determine whether to hide the banner
		 * Properties:
		 *   seenCount:     Total number of impressions seen by this user
		 *   waitCount:     Number of impressions we've waited for on this cycle
		 *   waitUntil:     Timestamp (ms) until we can show another banner
		 *   waitSeenCount: Number of impressions seen this cycle
		 */
		counts,

		STORAGE_KEY = 'impression_diet',

		// Suffix used with legacy cookies only
		WAIT_COOKIE_SUFFIX = '-wait',

		// Time to store impression-counting data, in days
		COUNTS_STORAGE_TTL = 365;

	mixin.setPreBannerHandler( function( mixinParams ) {
		var hide;

		// URL forced a banner
		if ( mw.util.getParamValue( 'force' ) ) {
			return;
		}

		identifier = mixinParams.cookieName; // TODO change param name

		// Check if and how we can store counts
		multiStorageOption = cn.kvStore.getMultiStorageOption(
			cn.getDataProperty( 'campaignCategoryUsesLegacy' ) );

		// In all cases, check for legacy cookies and try to migrate if
		// found
		possiblyMigrateLegacyCookies();

		// Banner was hidden already
		if ( cn.isBannerCanceled() ) {
			return;
		}

		// No options for storing stuff, so hide banner and bow out
		if ( multiStorageOption === cn.kvStore.multiStorageOptions.NO_STORAGE ) {
			cn.cancelBanner( 'waitnostorage' );
			return;
		}

		// Reset counts if requested (for testing)
		if ( mw.util.getParamValue( 'reset' ) === '1' ) {
			counts = getZeroedCounts();

		} else {

			// Otherwise get counts from storage
			counts = getCounts();
		}

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

		if ( hide ) {
			// Hide based on the results.
			cn.cancelBanner( hide );
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

		// Bookkeeping.
		storeCounts( counts );
	} );

	function getZeroedCounts() {
		return {
			seenCount: 0,
			waitCount: 0,
			waitUntil: 0,
			waitSeenCount: 0
		};
	}

	function possiblyMigrateLegacyCookies() {

		var rawCookie, rawWaitCookie, waitData, cookieCounts;

		// Legacy cookies required an identifier
		if ( !identifier ) {
			return;
		}

		rawCookie = $.cookie( identifier );

		if ( !rawCookie ) {
			return;
		}

		rawWaitCookie = $.cookie( identifier + WAIT_COOKIE_SUFFIX );
		waitData = ( rawWaitCookie || '' ) .split( /[|]/ );

		cookieCounts = {
			seenCount: parseInt( rawCookie, 10 ) || 0,
			waitCount: parseInt( waitData[0], 10 ) || 0,
			waitUntil: parseInt( waitData[1], 10 ) || 0,
			waitSeenCount: parseInt( waitData[2], 10 ) || 0
		};

		storeCounts( cookieCounts );

		$.removeCookie( identifier, { path: '/' } );
		$.removeCookie( identifier + WAIT_COOKIE_SUFFIX, { path: '/' } );
	}

	/**
	 * Get running impression counts from kvStore, if available, or return
	 * zeroed counts.
	 * @returns {Object} An object containing count data.
	 */
	function getCounts() {

		if ( identifier ) {
			return cn.kvStore.getItem(
				STORAGE_KEY + '_' + identifier,
				cn.kvStore.contexts.GLOBAL,
				multiStorageOption
			) || getZeroedCounts();
		}

		return cn.kvStore.getItem(
			STORAGE_KEY,
			cn.kvStore.contexts.CATEGORY,
			multiStorageOption
		) || getZeroedCounts();
	}

	/*
	 * Store updated counts
	 */
	function storeCounts( counts ) {

		if ( identifier ) {

			cn.kvStore.setItem(
				STORAGE_KEY + '_' + identifier,
				counts,
				cn.kvStore.contexts.GLOBAL,
				COUNTS_STORAGE_TTL,
				multiStorageOption
			);

		} else {

			cn.kvStore.setItem(
				STORAGE_KEY,
				counts,
				cn.kvStore.contexts.CATEGORY,
				COUNTS_STORAGE_TTL,
				multiStorageOption
			);
		}
	}

	// Register the mixin
	cn.registerCampaignMixin( mixin );

} )( jQuery, mediaWiki );
