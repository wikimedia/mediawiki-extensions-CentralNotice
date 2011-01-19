<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgOut;
		
		$this->setHideCookie();

		$wgOut->disable();
		wfResetOutputBuffers();

		header( 'Content-Type: image/png' );
		header( 'Cache-Control: no-cache' );

		readfile( dirname( __FILE__ ) . '/1x1.png' );
	}
	
	function setHideCookie() {
		global $wgNoticeCookieDomain, $wgCookieSecure;
		$exp = time() + 86400 * 14; // Cookie expires after 2 weeks
		if ( is_callable( 'CentralAuthUser', 'getCookieDomain' ) ) {
			$cookieDomain = CentralAuthUser::getCookieDomain();
		} else {
			$cookieDomain = $wgNoticeCookieDomain;
		}
		// Hide banners for this domain
		setcookie( 'hidesnmessage', '1', $exp, '/', $cookieDomain, $wgCookieSecure );
	}
}
