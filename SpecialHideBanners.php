<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

/**
 * Unlisted Special Page to set cookies for hiding banners across all wikis.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgAllowHideBanners, $wgRequest, $wgOut;

		if ( $wgAllowHideBanners ) {
			$this->setHideCookie();

			$wgOut->disable();
			wfResetOutputBuffers();

			header( 'Content-Type: image/png' );
			header( 'Cache-Control: no-cache' );

			readfile( dirname( __FILE__ ) . '/1x1.png' );
		}
	}
	
	function setHideCookie() {
		global $wgCentralAuthCookieDomain, $wgCookieSecure, $wgCookieHttpOnly;
		$exp = time() + 86400 * 14; // Cookie expires after 2 weeks
		// Hide banners for this domain
		setcookie( 'hidesnmessage', '1', $exp, '/', $wgCentralAuthCookieDomain, $wgCookieSecure, $wgCookieHttpOnly );
	}
}
