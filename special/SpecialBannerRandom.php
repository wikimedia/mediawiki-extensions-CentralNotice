<?php
/**
 * @deprecated
 *
 * Remove once we can be certain we're not breaking any ancient, cached JS.
 */
class SpecialBannerRandom extends SpecialBannerLoader {
	function __construct() {
		// Register special page
		UnlistedSpecialPage::__construct( "BannerRandom" );
	}

	/**
	 * This endpoint is deprecated.
	 * @param string|null $par
	 */
	function execute( $par ) {
		$this->getOutput()->disable();
		$this->getRequest()->response()->header(
			'HTTP/1.1 410 ' . HttpStatus::getMessage( 410 ) );
	}
}
