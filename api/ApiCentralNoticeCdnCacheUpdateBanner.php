<?php

/***
 * Module for the centralnoticecdncacheupdatebanner Web API. Used by a background call
 * via JS from Special:CentralNoticeBanners, to purge banner content from the front-end
 * cache, for a user-specified language.
 */
class ApiCentralNoticeCdnCacheUpdateBanner extends ApiBase {

	/**
	 * @see ApiBase::execute()
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$langCode = $params[ 'language' ];
		$bannerName = $params[ 'banner' ];

		if ( !$this->getUser()->isAllowed( 'centralnotice-admin' ) ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-permissions-error' );
		}

		if ( !Language::isValidCode( $langCode ) ) {
			$this->dieWithError( 'apierror-centralnotice-cdn-lang-code-error' );
		}

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
	 * @see ApiBase::getAllowedParams()
	 */
	public function getAllowedParams() {
		return [
			'banner' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'language' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @see ApiBase::needsToken()
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=centralnoticecdncacheupdatebanner&token=ABC123&banner=Banner1&language=en'
			=> 'apihelp-centralnoticecdncacheupdatebanner-example-1'
		];
	}
}
