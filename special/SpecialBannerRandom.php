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

	function execute( $par ) {
		$this->getOutput()->disable();

		$this->getParams();

		$this->bannerName = false;

		$chooser = new BannerChooser(
			$this->project,
			$this->language,
			$this->country,
			$this->anonymous,
			$this->bucket
		);
		$banner = $chooser->chooseBanner( $this->slot );

		if ( $banner ) {
			$this->bannerName = $banner['name'];
			$this->campaign = $banner['campaign'];
		}

		$this->sendHeaders();

		$content = false;
		try {
			if ( $this->bannerName ) {
				$content = $this->getJsNotice( $this->bannerName );
			}
		} catch ( SpecialBannerLoaderException $e ) {
			wfDebugLog( 'CentralNotice', "Exception while loading banner: " . $e->getMessage() );
		}

		if ( $content ) {
			echo $content;
		} else {
			wfDebugLog( 'CentralNotice', "No content retrieved for banner: {$this->bannerName}" );
			echo "insertBanner( false );";
		}
	}

	function getParams() {
		parent::getParams();

		$this->slot = $this->getSanitized( 'slot', 0, self::SLOT_FILTER );
	}
}
