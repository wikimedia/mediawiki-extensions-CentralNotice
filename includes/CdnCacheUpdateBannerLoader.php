<?php

use MediaWiki\Deferred\CdnCacheUpdate;
use MediaWiki\Deferred\DeferrableUpdate;

class CdnCacheUpdateBannerLoader implements DeferrableUpdate {

	/**
	 * Create a DeferrableUpdate to purge CDN content for a given banner and language
	 */
	public function __construct(
		private readonly string $langCode,
		private readonly Banner $banner,
	) {
	}

	/**
	 * Purges the banner loader URls for the language and banner passed to the constructor.
	 */
	public function doUpdate() {
		global $wgCentralSelectedBannerDispatcher;

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

		// Create the full URLs
		$urlsToPurge = [];
		foreach ( $paramPerms as $params ) {
			$urlsToPurge[] = wfAppendQuery(
				$wgCentralSelectedBannerDispatcher,
				$params
			);
		}

		( new CdnCacheUpdate( $urlsToPurge ) )->doUpdate();
	}
}
