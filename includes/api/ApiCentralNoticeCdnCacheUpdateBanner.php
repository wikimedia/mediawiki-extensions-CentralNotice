<?php

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Languages\LanguageNameUtils;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Module for the centralnoticecdncacheupdatebanner Web API. Used by a background call
 * via JS from Special:CentralNoticeBanners, to purge banner content from the front-end
 * cache, for a user-specified language.
 */
class ApiCentralNoticeCdnCacheUpdateBanner extends ApiBase {

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly LanguageNameUtils $languageNameUtils,
	) {
		parent::__construct( $mainModule, $moduleName );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isAllowed( 'centralnotice-admin' ) ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-permissions-error' );
		}

		$params = $this->extractRequestParams();
		$langCode = $params[ 'language' ];

		if ( !$this->languageNameUtils->isValidCode( $langCode ) ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-lang-code-error' );
		}

		$bannerName = $params[ 'banner' ];

		if ( !Banner::isValidBannerName( $bannerName ) ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-banner-name-error' );
		}

		// Get the banner object
		$banner = Banner::fromName( $bannerName );
		if ( !$banner->exists() ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-banner-not-found' );
		}

		// Deferred update to purge CDN caches for banner content
		DeferredUpdates::addUpdate(
			new CdnCacheUpdateBannerLoader( $langCode, $banner ),
			DeferredUpdates::PRESEND
		);

		$this->getResult()->addValue( null, $this->getModuleName(), 'update_requested' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'banner' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'language' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=centralnoticecdncacheupdatebanner&token=ABC123&banner=Banner1&language=en'
			=> 'apihelp-centralnoticecdncacheupdatebanner-example-1'
		];
	}
}
