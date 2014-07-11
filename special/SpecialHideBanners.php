<?php

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 * This is typically used on donation thank-you pages so that users who have donated will no longer
 * see fundrasing banners.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	// Cache this blank response for a day or so (60 * 60 * 24 s.)
	const CACHE_EXPIRY = 86400;

	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgNoticeCookieDurations;

		$reason = $this->getRequest()->getText( 'reason', 'donate' );
		$duration = $this->getRequest()->getInt( 'duration', $wgNoticeCookieDurations[$reason] );
		$category = $this->getRequest()->getText( 'category', 'fundraising' );
		$category = Banner::sanitizeRenderedCategory( $category );
		$this->setHideCookie( $category, $duration, $reason );

		$this->getOutput()->disable();
		wfResetOutputBuffers();

		header( 'Content-Type: image/png' );
		if ( !$this->getUser()->isLoggedIn() ) {
			$expiry = SpecialHideBanners::CACHE_EXPIRY;
			header( "Cache-Control: public, s-maxage={$expiry}, max-age=0" );
		}
	}

	/**
	 * Set the cookie for hiding fundraising banners.
	 */
	function setHideCookie( $category, $duration, $reason ) {
		global $wgNoticeCookieDomain;

		$created = time();
		$exp = $created + $duration;
		$value = array(
			'v' => 1,
			'created' => $created,
			'reason' => $reason
		);

		if ( is_callable( array( 'CentralAuthUser', 'getCookieDomain' ) ) ) {
			$cookieDomain = CentralAuthUser::getCookieDomain();
		} else {
			$cookieDomain = $wgNoticeCookieDomain;
		}
		setcookie( "centralnotice_hide_{$category}", json_encode( $value ), $exp, '/', $cookieDomain, false, false );
	}
}
