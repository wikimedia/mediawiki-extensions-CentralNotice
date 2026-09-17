<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 * This is typically used on donation thank-you pages so that users who have donated will no longer
 * see fundrasing banners.
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	// Cache this blank response for a day or so (60 * 60 * 24 s.)
	private const CACHE_EXPIRY = 86400;

	public function __construct() {
		parent::__construct( 'HideBanners' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$config = $this->getConfig();

		$reason = $this->getRequest()->getText( 'reason', 'donate' );
		$noticeCookieDurations = $config->get( 'NoticeCookieDurations' );

		if ( isset( $noticeCookieDurations[$reason] ) ) {
			$duration = $noticeCookieDurations[$reason];
		} else {
			$duration = $config->get( 'CentralNoticeFallbackHideCookieDuration' );
		}

		$category = $this->getRequest()->getText( 'category', 'fundraising' );
		$category = Banner::sanitizeRenderedCategory( $category );
		$this->setHideCookie( $category, $duration, $reason );

		$this->getOutput()->disable();
		wfResetOutputBuffers();

		header( 'Content-Type: image/png' );
		header( 'access-control-allow-origin: *' );

		if ( !$this->getUser()->isRegistered() ) {
			$expiry = self::CACHE_EXPIRY;
			header( "Cache-Control: public, s-maxage={$expiry}, max-age=0" );
		}
	}

	/**
	 * Set the cookie for hiding fundraising banners.
	 * @param string $category
	 * @param int $duration
	 * @param string $reason
	 */
	private function setHideCookie( $category, $duration, $reason ) {
		$created = time();
		$exp = $created + $duration;
		$value = [
			'v' => 1,
			'created' => $created,
			'reason' => $reason
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			$cookieDomain = CentralAuthUser::getCookieDomain();
		} else {
			$cookieDomain = $this->getConfig()->get( 'NoticeCookieDomain' );
		}
		setcookie(
			"centralnotice_hide_{$category}",
			json_encode( $value ),
			[
				'expires' => $exp,
				'path' => '/',
				'domain' => $cookieDomain,
				'secure' => true,
				'httponly' => false,
				'samesite' => 'None',
			]
		);
	}
}
