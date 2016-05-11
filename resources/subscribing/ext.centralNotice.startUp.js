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

	var cn = mw.centralNotice,
		cookiesToDelete = mw.config.get( 'wgCentralNoticeCookiesToDelete' );

	// Schedule the slurping of old defunct cookies
	if ( cookiesToDelete && cookiesToDelete.length > 0 ) {
		mw.requestIdleCallback( deleteOldCookies );
	}

	// Note: In legacy code, CentralNotice initialization was done after the DOM
	// finished loading (via $( function() {...} )). Now, we only delay logic
	// that accesses DOM elements in that way, and run other code sooner.

	// Nothing to do if we're on a special page.
	if ( mw.config.get( 'wgNamespaceNumber' ) == -1 ) {
		return;
	}

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
		mw.log.warn( 'No choice data set for CentralNotice campaign ' +
			'and banner selection.' );
		return;
	}

	// Maintenance: This schedules the removal of old KV keys.
	// The schedule action itself is deferred, too, as it accesses localStorage.
	mw.requestIdleCallback( cn.kvStoreMaintenance.doMaintenance );

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

	/**
	 * Run through cookiesToDelete to check for and delete old cookies
	 */
	function deleteOldCookies() {

		// Ensure up our cookie library is present
		mw.loader.using( 'mediawiki.cookie' ).done( function() {

			// Wait for more idle time
			mw.requestIdleCallback( function ( deadline ) {

				// Stop if there are no more cookies to check or if there's too
				// little idle time left.
				while ( cookiesToDelete.length > 0 && deadline.timeRemaining() > 3) {
					mw.cookie.set( cookiesToDelete.shift(), null, {
						path: '/',
						prefix: ''
					} );
				}
			} );
		} );
	}

} )(  jQuery, mediaWiki );
