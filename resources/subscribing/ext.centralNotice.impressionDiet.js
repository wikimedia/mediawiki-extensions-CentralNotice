/**
 * Impression diet mixin. Provides knobs to cap the number of impressions
 * each user will see.
 *
 * This mixin stores count data in the kvStore (LocalStorage).
 *
 * In general, showing a banner entails that a certain number of impressions
 * have already occurred in a time period. Parameter documentation is provided
 * in the CentralNotice campaign management UI.
 *
 * - Banner may be forced via URL parameter force=1.
 * - Cycle counters may be reset via URL parameter reset=1.
 *
 * Flow chart: https://commons.wikimedia.org/wiki/File:CentralNotice_-_wait_cookie_code_flow.png
 * (FIXME: update ^^ with new parameter names)
 */
( function () {
	'use strict';

	const cn = mw.centralNotice;
	const mixin = new cn.Mixin( 'impressionDiet' );
	const now = Date.now();
	const STORAGE_KEY = 'impression_diet';
	// Time to store impression-counting data, in days
	const COUNTS_STORAGE_TTL = 365;

	let identifier;
	let multiStorageOption;
	/**
	 * Object with data used to determine whether to hide the banner
	 * Properties:
	 *   seenCount:        Total number of impressions seen by this user
	 *   skippedThisCycle: Number of initial impressions we've skipped this cycle
	 *   nextCycleStart:   Unix timestamp after which we can show more banners
	 *   seenThisCycle:    Number of impressions seen this cycle
	 */
	let counts;

	mixin.setPreBannerHandler( ( mixinParams ) => {
		// URL forced a banner
		if ( mw.util.getParamValue( 'force' ) ) {
			return;
		}

		identifier = mixinParams.cookieName; // TODO change param name

		// Check if and how we can store counts
		multiStorageOption = cn.kvStore.getMultiStorageOption(
			cn.getDataProperty( 'campaignCategoryUsesLegacy' )
		);

		// Banner was hidden already
		if ( cn.isCampaignFailed() ) {
			return;
		}

		// No options for storing stuff, so hide banner and bow out
		if ( multiStorageOption === cn.kvStore.multiStorageOptions.NO_STORAGE ) {
			cn.failCampaign( 'waitnostorage' );
			return;
		}

		// Reset counts if requested (for testing)
		if ( mw.util.getParamValue( 'reset' ) === '1' ) {
			counts = getZeroedCounts();
		} else {
			// Otherwise get counts from storage
			counts = getCounts();
		}

		if ( now > counts.nextCycleStart &&
			counts.seenThisCycle >= mixinParams.maximumSeen
		) {
			// We're beyond the wait period, and have nothing to do except
			// maybe start a new cycle.
			if ( mixinParams.restartCycleDelay !== 0 ) {
				// Begin a new cycle by clearing counters.
				counts.skippedThisCycle = 0;
				counts.seenThisCycle = 0;
			}
		}

		// Compare counts against campaign settings and decide whether to
		// show a banner

		let hide;
		if ( counts.seenThisCycle < mixinParams.maximumSeen ) {
			// You haven't seen the maximum count of banners per cycle!
			if ( counts.skippedThisCycle < mixinParams.skipInitial ) {
				// Skip initial impressions.
				hide = 'waitimps';
				counts.skippedThisCycle += 1;
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
			cn.failCampaign( hide );
		} else {
			// Count shown impression.
			counts.seenThisCycle += 1;
			counts.seenCount += 1;

			// Reset the wait timer on every impression.  The configured delay
			// is the minimum amount of time allowed between the final impression
			// and the start of the next cycle.

			counts.nextCycleStart = now +
				( mixinParams.restartCycleDelay * 1000 );
		}

		// Bookkeeping.
		storeCounts( counts );
	} );

	function getZeroedCounts() {
		return {
			seenCount: 0,
			skippedThisCycle: 0,
			nextCycleStart: 0,
			seenThisCycle: 0
		};
	}

	/**
	 * Migrate older data to current format
	 *
	 * @param {Object} kvStoreCounts Possibly using legacy names
	 * @return {Object} Counts object using current names
	 */
	function fixCountNames( kvStoreCounts ) {
		if ( kvStoreCounts.skippedThisCycle !== undefined ) {
			// Return current version unchanged
			return kvStoreCounts;
		}

		// T121178: March 2018
		if ( kvStoreCounts.waitSeenCount !== undefined ) {
			kvStoreCounts = {
				seenCount: kvStoreCounts.seenCount,
				skippedThisCycle: kvStoreCounts.waitCount,
				nextCycleStart: kvStoreCounts.waitUntil,
				seenThisCycle: kvStoreCounts.waitSeenCount
			};
		}

		return kvStoreCounts;
	}

	/**
	 * Get running impression counts from kvStore, if available, or return
	 * zeroed counts.
	 *
	 * @return {Object} An object containing count data.
	 */
	function getCounts() {
		let c;

		if ( identifier ) {
			c = cn.kvStore.getItem(
				STORAGE_KEY + '_' + identifier,
				cn.kvStore.contexts.GLOBAL,
				multiStorageOption
			);
		} else {
			c = cn.kvStore.getItem(
				STORAGE_KEY,
				cn.kvStore.contexts.CATEGORY,
				multiStorageOption
			);
		}
		c = c || getZeroedCounts();

		return fixCountNames( c );
	}

	/**
	 * Store updated counts
	 */
	function storeCounts( c ) {
		if ( identifier ) {
			cn.kvStore.setItem(
				STORAGE_KEY + '_' + identifier,
				c,
				cn.kvStore.contexts.GLOBAL,
				COUNTS_STORAGE_TTL,
				multiStorageOption
			);
		} else {
			cn.kvStore.setItem(
				STORAGE_KEY,
				c,
				cn.kvStore.contexts.CATEGORY,
				COUNTS_STORAGE_TTL,
				multiStorageOption
			);
		}
	}

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}() );
