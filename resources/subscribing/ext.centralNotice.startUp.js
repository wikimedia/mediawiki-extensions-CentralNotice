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

	// Note: In legacy code, CentralNotice initialization was done after the DOM
	// finished loading (via $( function() {...} )). Now, we only delay logic
	// that accesses DOM elements in that way, and run other code sooner.

	// Nothing to do if we're on a special page.
	if ( mw.config.get( 'wgNamespaceNumber' ) == -1 ) {
		return;
	}

	/**
	 * Temporary utility for doing non-time-critical work. See T111456.
	 * Defined here 'cause it may be used by any CN subscribing modules.
	 */
	cn.doIdleWork = function ( func ) {
		$( window ).on( 'load', function() {
			setTimeout( function () { func(); }, 2000 );
		} );
	};

	// Legacy support:
	// Legacy code inserted the CN div everywhere (except on Special pages),
	// even when there were no campaigns. Let's do the same thing for now, in
	// case other code has grown up around it.
	// TODO Add this only if there's a banner one day?
	$( function() {
		$( '#siteNotice' ).prepend( '<div id="centralNotice"></div>' );
	} );

	// Testing banner
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

	// Maintenance: clean old KV store keys whenever.
	// If a campaign reads from the KV store before this is executed, the
	// cleaning will happen at that time, and the following call to
	// removeExpiredItems() will be a no-op.
	cn.doIdleWork( cn.kvStoreMaintenance.removeExpiredItems );

	// Nothing more to do if there are no possible campaigns for this user
	if ( cn.choiceData.length === 0 ) {
		return;
	}

	cn.chooseAndMaybeDisplay();

} )(  jQuery, mediaWiki );