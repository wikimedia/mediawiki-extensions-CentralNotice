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

	protected function chooseBanner() {
		$chooser = new BannerChooser( $this->allocContext );
		$banner = $chooser->chooseBanner( $this->slot );

		if ( $banner ) {
			$this->bannerName = $banner['name'];
			$this->campaign = $banner['campaign'];
		}
	}

	function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		// No client-side banner caching so we get all impressions
		header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
	}
}
