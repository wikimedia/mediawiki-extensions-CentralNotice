<?php
/**
 * Renders banner contents as jsonp, making a random selection from a
 * predetermined number of slots.
 */
class SpecialBannerRandom extends SpecialBannerLoader {
	const SLOT_FILTER = '/[0-9]+/';

	function __construct() {
		// Register special page
		UnlistedSpecialPage::__construct( "BannerRandom" );
	}

	function getParams() {
		parent::getParams();

		$this->slot = $this->getSanitized( 'slot', self::SLOT_FILTER );

		if ( $this->slot === null ) {
			throw new MissingRequiredParamsException();
		}

		$this->chooseBanner();
	}

	/**
	 * This endpoint is deprecated.
	 */
	function execute( $par ) {
		// Find out what's up with unexpected requests
		wfDebugLog( 'T89258', 'Call to execute(). From backend: ' .
			$this->getRequest()->getHeader('X-Cache') . "\n" .
			'From URL: ' . $this->getRequest()->getRequestURL() . "\n" .
			'Backtrace: ' . json_encode( debug_backtrace() ) );

		$this->getOutput()->disable();
		$this->getRequest()->response()->header(
			'HTTP/1.1 410 ' . HttpStatus::getMessage( 410 ) );
	}

	/**
	 * This is also deprecated. Well, the whole class is.
	 * TODO Full removal coming soon.
	 */
	protected function chooseBanner() {
		// For debugging unexpected code execution
		wfDebugLog( 'T89258', 'Call to chooseBanner(). From backend: ' .
			$this->getRequest()->getHeader('X-Cache') . "\n" .
			'From URL: ' . $this->getRequest()->getRequestURL() . "\n" .
			'Backtrace: ' . json_encode( debug_backtrace() ) );
	}

	function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;

		header( "Content-type: $wgJsMimeType; charset=utf-8" );

		// If we have a logged in user; do not cache (default for special pages)
		// lest we capture a set-cookie header. Otherwise cache so we don't have
		// too big of a DDoS hole.
		if ( !$this->getUser()->isLoggedIn() ) {
			header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
		}
	}
}
