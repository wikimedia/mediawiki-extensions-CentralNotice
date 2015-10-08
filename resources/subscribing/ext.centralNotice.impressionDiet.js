/*
 * Impression diet mixin.  Provides knobs to cap the number of impressions
 * each user will see.

 * Compatible with MediaWiki:CentralNotice/Resources/BannerShowHideCountDate.js,
 * for the moment.  Campaigns using the onwiki showhide code can be migrated
 * without donors seeing any extra impressions.
 *
 * Determine if a banner should be hidden or displayed based on the
 * contents of the cookie named mixinParams.cookieName and
 * mixinParams.cookieName + `-wait`.
 *
 * Showing a banner entails that a certain number of impressions have
 * already occured in a time period.
 *
 * Parameter documentation is provided in the CentralNotice campaign management UI.
 *
 * Banner may be forced if URL parameter force = 1
 * Counters may be reset if URL parameter reset = 1
 *
 * Flow chart: https://commons.wikimedia.org/wiki/File:CentralNotice_-_wait_cookie_code_flow.png
 * (FIXME: update ^^ with new parameter names)
 */
( function ( $, mw ) {
	'use strict';

	var cn = mw.centralNotice,
		mixin = new cn.Mixin( 'impressionDiet' );

	mixin.setPreBannerHandler( function( mixinParams ) {
		var counts = getCounts( mixinParams.cookieName ),
			forceFlag = mw.util.getParamValue( 'force' ),
			hide = null;

		// Compare counts against campaign settings and decide whether to show a banner

		var pastDate = counts.waitUntil < new Date().getTime(),
			waitForHideImps = counts.waitCount < mixinParams.skipInitial,
			waitForShowImps = counts.waitSeenCount < mixinParams.maximumSeen;

		if ( forceFlag ) {
			// URL forced a banner.
			hide = false;
		} else if ( !pastDate ) {
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

			if ( counts.waitSeenCount >= mixinParams.maximumSeen ) {
				// We just completed a cycle.  Wait to restart.
				counts.waitCount = 0;
				counts.waitSeenCount = 0;
				counts.waitUntil = new Date().getTime() + ( mixinParams.restartCycleDelay * 1000 );
			}
		}

		if ( hide === null ) {
			// All bets are off!
			hide = 'waiterr';
			counts.waitCount = 0;
			counts.waitSeenCount = 0;
			counts.waitUntil = new Date().getTime() + ( mixinParams.restartCycleDelay * 1000 );
		}

		// Hide based on the results.
		if ( hide ) {
			cn.internal.state.cancelBanner( hide );
		}

		// Bookkeeping.
		storeCounts( mixinParams.cookieName, counts );
	} );

	/*
	 * Get running impression counts from cookie storage
	 *
	 * TODO: Deprecate cookies and replace with campaign-linked KV storage.
	 */
	function getCounts( cookieName ) {
		// Reset counters on demand
		if ( mw.util.getParamValue( 'reset' ) === '1' ) {
			return {
				seenCount: 0,
				waitCount: 0,
				waitUntil: 0,
				waitSeenCount: 0
			};
		}

		// Parse the count cookies, if available.
		var cookieCount = parseInt( $.cookie( cookieName ) ) || 0,
			waitData = ($.cookie( cookieName + '-wait' ) || '' ).split( /[|]/ );

		return {
			/** Total number of impressions seen by this user */
			seenCount: cookieCount,
			/** This cycle's count of how many impressions we've waited for */
			waitCount: parseInt( waitData[0] ) || 0,
			/** Timestamp (ms) until we can show another banner */
			waitUntil: parseInt( waitData[1] ) || 0,
			/** Number of impressions seen this cycle */
			waitSeenCount: parseInt( waitData[2] ) || 0
		};
	}

	/*
	 * Store updated counts
	 *
	 * TODO: Deprecate legacy storage in favor of campaign-linked KV storage.
	 */
	function storeCounts( cookieName, counts ) {
		var waitData = counts.waitCount + '|' + counts.waitUntil + '|' + counts.waitSeenCount;

		// Finish up and store results
		$.cookie( cookieName, counts.seenCount, { expires: 365, path: '/' } );
		$.cookie( cookieName + '-wait', waitData, { expires: 365, path: '/' } );
	}

	// Register the mixin
	cn.registerCampaignMixin( mixin );
} )( jQuery, mediaWiki );
