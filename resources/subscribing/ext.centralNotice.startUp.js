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
( function ( $, mw ) {

	var cn = mw.centralNotice;

	// Nothing to do if we're on a special page.
	if ( mw.config.get( 'wgNamespaceNumber' ) == -1 ) {
		return;
	}

	// TODO Taken from legacy: In previous code, CentralNotice initialization
	// was done after the DOM finished loading (via $( function() {...} ) ).
	// We are keeping this functionality as is until CN processing is moved
	// from the top RL module queue. After it has moved, we may only delay logic
	// that accesses DOM elements (and banner test code) is in that way, and run
	// other code sooner. In theory, such a change could improve performance.
	$( function() {

		// Legacy support:
		// Legacy code inserts the CN div everywhere (except on Special pages),
		// even when there are no campaigns. Let's do the same thing for now, in
		// case other code has grown up around it.
		// TODO Remove this one day?
		$( '#siteNotice' ).prepend( '<div id="centralNotice"></div>' );

		// Testing banner?
		// Note that this must wait for the DOM in order to work in debug mode,
		// due to RL limitations.
		if ( mw.util.getParamValue( 'banner' ) ) {
			mw.loader.using( 'ext.centralNotice.display' ).done( function() {
				cn.displayTestingBanner();
			} );
			return;
		}

		// Sanity check
		if ( cn.choiceData === undefined ) {
			mw.log( 'No choice data set for CentralNotice campaign ' +
				'and banner selection.' );
			return;
		}

		// Nothing to do if there are no possible campaigns for this user
		if ( cn.choiceData.length === 0 ) {
			return;
		}

		cn.chooseAndMaybeDisplay();

	} );
} )(  jQuery, mediaWiki );