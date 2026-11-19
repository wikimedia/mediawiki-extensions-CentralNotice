<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Wikimedia\Http\HttpHeader;

/**
 * Unlisted Special Page which sets a cookie for hiding banners across all languages of a project.
 * This is typically used on donation thank-you pages so that users who have donated will no longer
 * see fundrasing banners.
 */
class SpecialHideBanners extends UnlistedSpecialPage {

	// maximum length for 'reason' or 'category' in cookies
	private const MAX_FIELD_LENGTH = 64;

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

		HttpHeader::send( 'Content-Type: image/png' );
		HttpHeader::send( 'access-control-allow-origin: *' );
	}

	/**
	 * Set the cookie for hiding fundraising banners.
	 * @param string $category
	 * @param int $duration
	 * @param string $reason
	 */
	private function setHideCookie( $category, $duration, $reason ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			// Cookies are no-ops in tests, avoid "Headers already sent" warnings.
			return;
		}
		$category = mb_substr( $category, 0, self::MAX_FIELD_LENGTH );
		$reason = mb_substr( $reason, 0, self::MAX_FIELD_LENGTH );
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
