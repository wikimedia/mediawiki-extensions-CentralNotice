<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

/**
 * Unlisted Special page to set cookies for hiding banners across all wikis.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgRequest, $wgOut;

		$this->setGlobalCookie();

		$wgOut->disable();
		wfResetOutputBuffers();
		
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: no-cache' );
		
		readfile( dirname( __FILE__ ) . '/1x1.png' );
	}
	
	function setGlobalCookie() {
		$exp = time() + 86400 * 14; // cookie expires after 2 weeks
		setcookie( 'hidesnmessage', '0', $exp, '/' );
	}
}
