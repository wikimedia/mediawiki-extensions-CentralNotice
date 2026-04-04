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
 * - Cycle counters and daily limit may be reset via URL parameter reset=1.
 *
 * Flow chart: https://commons.wikimedia.org/wiki/File:CentralNotice_-_wait_cookie_code_flow.png
 * (FIXME: update ^^ with new parameter names)
 */
( function () {
	'use strict';

	const cn = mw.centralNotice;
	const mixin = new cn.Mixin( 'impressionDiet' );
	const STORAGE_KEY = 'impression_diet';
	// Time to store impression-counting data, in days
	const COUNTS_STORAGE_TTL = 365;

	let identifier;
	let multiStorageOption;

	mixin.setPreBannerHandler( impressionDietHandler );
	function impressionDietHandler( mixinParams ) {
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

		const now = Date.now();

		/**
		 * Object with data used to determine whether to hide the banner
		 * Properties:
		 *   seenCount:        Total number of impressions seen by this user
		 *   skippedThisCycle: Number of initial impressions we've skipped this cycle
		 *   nextCycleStart:   Unix timestamp after which we can show more banners
		 *   seenThisCycle:    Number of impressions seen this cycle
		 *   nextDailyStart:   Unit timestamp when the daily limit resets
		 *   seenThisDay:      Number of impressions seen this day
		 */
		let counts;

		// Reset counts if requested (for testing)
		if ( mw.util.getParamValue( 'reset' ) === '1' ) {
			counts = getZeroedCounts();
		} else {
			// Otherwise get counts from storage
			counts = getCounts();
		}

		if (
			mixinParams.restartCycleDelay > 0 &&
			now > counts.nextCycleStart &&
			counts.seenThisCycle >= mixinParams.maximumSeen
		) {
			// We're beyond the wait period, and have nothing to do except
			// begin a new cycle by clearing counters.
			counts.skippedThisCycle = 0;
			counts.seenThisCycle = 0;
		}

		if ( now > counts.nextDailyStart ) {
			counts.seenThisDay = 0;
			counts.nextDailyStart = makeNextDailyStart( now );
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
			} else if (
				mixinParams.dailyLimit > 0 &&
				counts.seenThisDay >= mixinParams.dailyLimit
			) {
				// Wait for the next day to begin.
				hide = 'waitdaily';
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
			counts.seenThisDay += 1;
			counts.seenCount += 1;

			// Reset the wait timer on every impression.  The configured delay
			// is the minimum amount of time allowed between the final impression
			// and the start of the next cycle.

			counts.nextCycleStart = now +
				( mixinParams.restartCycleDelay * 1000 );
		}

		// Bookkeeping.
		storeCounts( counts );
	}

	/**
	 * @param {number} now
	 * @return {number} UNIX timestamp when the next window starts for the daily limit
	 */
	function makeNextDailyStart( now ) {
		// Calculate the next 04:00 in local time, i.e. "tomorrow"
		const d = new Date( now );
		d.setMilliseconds( 0 );
		d.setSeconds( 0 );
		d.setMinutes( 0 );
		if ( d.getHours() >= 4 ) {
			d.setDate( d.getDate() + 1 );
		}
		d.setHours( 4 );
		return d.getTime();
	}

	function getZeroedCounts() {
		return {
			seenCount: 0,
			skippedThisCycle: 0,
			nextCycleStart: 0,
			seenThisCycle: 0,
			nextDailyStart: 0,
			seenThisDay: 0
		};
	}

	/**
	 * Migrate or discard old or invalid data
	 *
	 * @param {Object|undefined} kvStoreCounts Possibly using previous schemas
	 * @return {Object|undefined} Counts object using current names or undefined
	 *  if data was invalid or too old.
	 */
	function fixCountNames( kvStoreCounts ) {
		if ( !kvStoreCounts || kvStoreCounts.skippedThisCycle === undefined ) {
			// * undefined
			// * T121178: March 2018 schema change (rename waitCount to skippedThisCycle)
			return undefined;
		}

		// T421662: April 2026 schema change (add seenThisDay)
		if ( kvStoreCounts.seenThisDay === undefined ) {
			kvStoreCounts.nextDailyStart = 0;
			kvStoreCounts.seenThisDay = 0;
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

		return fixCountNames( c ) || getZeroedCounts();
	}

	/**
	 * Store updated counts
	 *
	 * @param c
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

	// For use in QUnit tests
	module.exports.private = {
		impressionDietHandler
	};

}() );
