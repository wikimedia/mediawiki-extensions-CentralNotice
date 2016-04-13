<?php

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 * This is typically used on donation thank-you pages so that users who have donated will no longer
 * see fundrasing banners.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	// Cache this blank response for a day or so (60 * 60 * 24 s.)
	const CACHE_EXPIRY = 86400;
	const P3P_SUBPAGE = 'P3P';

	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgNoticeCookieDurations, $wgCentralNoticeHideBannersP3P,
			$wgCentralNoticeFallbackHideCookieDuration;

		// Handle /P3P subpage with explanation of invalid P3P header
		if ( ( strval( $par ) === SpecialHideBanners::P3P_SUBPAGE ) &&
			!$wgCentralNoticeHideBannersP3P ){

			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'centralnotice-specialhidebanners-p3p' );
			return;
		}

		$reason = $this->getRequest()->getText( 'reason', 'donate' );

		// No duration parameter for a custom reason is not expected; we have a
		// fallback value, but we log that this happened.
		$duration = $this->getRequest()->getInt( 'duration', 0 );
		if ( !$duration ) {
			if ( isset( $wgNoticeCookieDurations[$reason] ) ) {
				$duration = $wgNoticeCookieDurations[$reason];
			} else {
				$duration = $wgCentralNoticeFallbackHideCookieDuration;
				wfLogWarning( 'Missing or 0 duration for hide cookie reason '
					. $reason . '.' );
			}
		}

		$category = $this->getRequest()->getText( 'category', 'fundraising' );
		$category = Banner::sanitizeRenderedCategory( $category );
		$this->setHideCookie( $category, $duration, $reason );
		$this->setP3P();

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

	/**
	 * Set an invalid P3P policy header to make IE accept third-party hide cookies.
	 */
	protected function setP3P() {
		global $wgCentralNoticeHideBannersP3P;

		if ( !$wgCentralNoticeHideBannersP3P ) {

			$url = SpecialPage::getTitleFor(
				'HideBanners', SpecialHideBanners::P3P_SUBPAGE )
				->getCanonicalURL();

			$p3p = "CP=\"This is not a P3P policy! See $url for more info.\"";

		} else {
			$p3p = $wgCentralNoticeHideBannersP3P;
		}

		header( "P3P: $p3p", true );
	}
}
