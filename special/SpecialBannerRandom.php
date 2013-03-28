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
		$chooser = new BannerChooser(
			$this->project,
			$this->language,
			$this->country,
			$this->anonymous,
			$this->device,
			$this->bucket
		);
		$banner = $chooser->chooseBanner( $this->slot );

		if ( $banner ) {
			$this->bannerName = $banner['name'];
			$this->campaign = $banner['campaign'];
		}
	}
}
