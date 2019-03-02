<?php

class CdnCacheUpdateBannerLoader implements DeferrableUpdate {

	/**
	 * @var string
	 */
	private $langCode;

	/**
	 * @var Banner
	 */
	private $banner;

	/**
	 * Create a DeferrableUpdate to purge CDN content for a given banner and language
	 *
	 * @param string $langCode Language code
	 * @param Banner $banner
	 */
	public function __construct( $langCode, Banner $banner ) {
		$this->langCode = $langCode;
		$this->banner = $banner;
	}

	/**
	 * Purges the banner loader URls for the language and banner passed to the constructor.
	 */
	public function doUpdate() {
		global $wgCentralSelectedBannerDispatcher,
		$wgCentralSelectedMobileBannerDispatcher;

		$paramPerms = [];
		$bannerName = $this->banner->getName();

		// Note: URL parameter order must be: title (if used in configured
		// URLs, for ugly URL format), campaign (omitted for preview),
		// banner, uselang, debug. See ext.centralNotice.display.js.

		// Include URLs for banner preview (no campaign, users's language)
		$paramPerms[] = [
			'banner' => $bannerName,
			'uselang' => $this->langCode,
			'debug' => 'true'
		];

		$paramPerms[] = [
			'banner' => $bannerName,
			'uselang' => $this->langCode,
			'debug' => 'false'
		];

		// Include expected permutations of non-preview URLs
		$campaignNames = $this->banner->getCampaignNames();

		foreach ( $campaignNames as $campaignName ) {
			$paramPerms[] = [
				'campaign' => $campaignName,
				'banner' => $bannerName,
				'uselang' => $this->langCode,
				'debug' => 'true'
			];

			$paramPerms[] = [
				'campaign' => $campaignName,
				'banner' => $bannerName,
				'uselang' => $this->langCode,
				'debug' => 'false'
			];
		}

		// Determine if we should add mobile URLs, too
		$addMobile = ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			( $wgCentralSelectedBannerDispatcher !==
				$wgCentralSelectedMobileBannerDispatcher )
			);

		// Create the full URLs
		$urlsToPurge = [];
		foreach ( $paramPerms as $params ) {
			$urlsToPurge[] = wfAppendQuery(
				$wgCentralSelectedBannerDispatcher,
				$params
			);

			if ( $addMobile ) {
				$urlsToPurge[] = wfAppendQuery(
					$wgCentralSelectedMobileBannerDispatcher,
					$params
				);
			}
		}

		( new CdnCacheUpdate( $urlsToPurge ) )->doUpdate();
	}
}
