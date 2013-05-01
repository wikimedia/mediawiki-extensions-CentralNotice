<?php

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 * This is typically used on donation thank-you pages so that users who have donated will no longer
 * see fundrasing banners.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		$this->setHideCookie();

		$this->getOutput()->disable();
		wfResetOutputBuffers();

		header( 'Content-Type: image/png' );
		header( 'Cache-Control: no-cache' );
	}

	/**
	 * Set the cookie for hiding fundraising banners.
	 */
	function setHideCookie() {
		global $wgNoticeCookieDomain, $wgNoticeCookieLongExpiry;

		$exp = time() + $wgNoticeCookieLongExpiry;

		if ( is_callable( array( 'CentralAuthUser', 'getCookieDomain' ) ) ) {
			$cookieDomain = CentralAuthUser::getCookieDomain();
		} else {
			$cookieDomain = $wgNoticeCookieDomain;
		}
		// Hide fundraising banners for this domain
		setcookie( 'centralnotice_fundraising', 'hide', $exp, '/', $cookieDomain, false, false );
	}
}
