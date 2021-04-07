/**
 * Start-up script for CentralNotice.
 *
 * Here's what it does:
 * - Check if we're on a Special page; quick bow-out if so.
 * - For legacy support, ensure that the centralNotice div is available, as it
 *   was in legacy code.
 * - If a banner was requested for testing, load that.
 * - Otherwise, if there are campaigns in choiceData, filter and process that,
 *   and possibly display a banner.
 *
 * This module depends on ext.centralNotice.geoIP and
 * ext.centralNotice.choiceData. If there are campaigns in choiceData,
 * that module will depend on any other modules needed for further processing.
 */
( function () {

	var cn = mw.centralNotice,
		testingBannerName = mw.util.getParamValue( 'banner' ),
		kvStoreMaintenance = require( './kvStoreMaintenance.js' ),
		NULL_BANNER_NAME = 'null';

	// For back-compat and debugging, export globally.
	cn.kvStoreMaintenance = kvStoreMaintenance;

	// Note: In legacy code, CentralNotice initialization was done after the DOM
	// finished loading (via $( function() {...} )). Now, we only delay logic
	// that accesses DOM elements in that way, and run other code sooner.

	// Legacy support:
	// Legacy code inserted the CN div everywhere (except on Special pages),
	// even when there were no campaigns. Let's do the same thing for now, in
	// case other code has grown up around it.
	// TODO Add this only if there's a banner one day?
	$( function () {
		$( '#siteNotice' ).prepend( '<div id="centralNotice"></div>' );
	} );

	// Testing banner or forced no banner
	if ( testingBannerName ) {
		if ( testingBannerName === NULL_BANNER_NAME ) {
			return;
		}
		mw.loader.using( 'ext.centralNotice.display' ).done( function () {
			cn.displayTestingBanner();
		} );
		return;
	}

	// Sanity check
	if ( cn.choiceData === undefined ) {
		mw.log.warn( 'No choice data set for CentralNotice campaign ' +
			'and banner selection.' );
		return;
	}

	// Maintenance: This schedules the removal of old KV keys.
	// The schedule action itself is deferred, too, as it accesses localStorage.
	// - FIXME: Consider doing this behind a random sample instead of every page view
	//   (e.g. 50% or 20%). Or, instead of sampling:
	// - FIXME: Use sessionStorage (mw.storage.session) to not schedule multiple
	//   maintenance windows simultaneously and to not schedule maintenance too
	//   often (e.g. no more once every 6 hours). It may be attractive to do
	//   "No more than once per session" but a time-bound is still needed as
	//   sessions often never end due to save-on-quit and restore-on-reopen features
	//   in many products.
	mw.requestIdleCallback( kvStoreMaintenance.doMaintenance );

	// Nothing more to do if there are no possible campaigns for this user
	if ( cn.choiceData.length === 0 ) {
		return;
	}

	// If there's some issue with RL causing ext.centralNotice.display not
	// to load, don't fail hard
	if ( !cn.chooseAndMaybeDisplay ) {
		mw.log.warn( 'Possible campaign(s) received in choiceData, but ' +
			'mw.centralNotice.chooseAndMaybeDisplay() is not available' );

		return;
	}

	cn.chooseAndMaybeDisplay();

}() );
