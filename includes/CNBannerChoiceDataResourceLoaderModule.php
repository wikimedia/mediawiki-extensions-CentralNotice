<?php

/***
 * ResourceLoader module for sending banner choices to the client.
 *
 * Note: This class has been intentionally left stateless, due to how
 * ResourceLoader works. This class has no expectation of having getScript() or
 * getModifiedHash() called in the same request.
 */
class CNBannerChoiceDataResourceLoaderModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::targets
	 */
	protected $targets = array( 'desktop', 'mobile' );

	const API_REQUEST_TIMEOUT = 20;

	protected function getChoices( ResourceLoaderContext $context ) {
		global $wgNoticeProject, $wgCentralNoticeApiUrl;

		$project = $wgNoticeProject;
		$language = $context->getLanguage();

		// Only fetch the data via the API if $wgCentralNoticeApiUrl is set.
		// Otherwise, use the DB.
		if ( $wgCentralNoticeApiUrl ) {
			$choices = $this->getFromApi( $project, $language );

			if ( !$choices ) {
				wfLogWarning( 'Couldn\'t fetch banner choice data via API. ' .
					'$$wgCentralNoticeApiUrl = ' . $wgCentralNoticeApiUrl );

				return array();
			}
		} else {
			 $choices = $this->getFromDb( $project, $language );
		}

		return $choices;
	}

	/**
	 * Get the banner choices data via a direct DB call to the infrastructure wiki
	 *
	 * @param string $project
	 * @param string $language
	 */
	protected function getFromDb( $project, $language ) {

		$choicesProvider = new BannerChoiceDataProvider( $project, $language );

		return $choicesProvider->getChoices();
	}

	/**
	 * Get the banner choices data via an API call to the infrastructure wiki.
	 * If the call fails, we return false.
	 *
	 * @param string $project
	 * @param string $language
	 *
	 * @return array|boolean
	 */
	protected function getFromApi( $project, $language ) {
		global $wgCentralNoticeApiUrl;

		// Make the URl
		$q = array(
			'action' => 'centralnoticebannerchoicedata',
			'project' => $project,
			'language' => $language,
			'format' => 'json',
			'formatversion' => 2 // Prevents stripping of false values 8p
		);

		$url = wfAppendQuery( $wgCentralNoticeApiUrl, $q );

		$apiResult = Http::get( $url,
			array( 'timeout' => self::API_REQUEST_TIMEOUT * 0.8 ) );

		if ( !$apiResult ) {
			wfLogWarning( 'Couldn\'t get banner choice data via API.');
			return false;
		}

		$parsedApiResult = FormatJson::parse( $apiResult, FormatJson::FORCE_ASSOC );

		if ( !$parsedApiResult->isGood() ) {
			wfLogWarning( 'Couldn\'t parse banner choice data from API.');
			return false;
		}

		$result = $parsedApiResult->getValue();

		if ( isset( $result['error'] ) ) {
			wfLogWarning( 'Error fetching banner choice data via API: ' .
				$result['error']['info'] . ': ' . $result['error']['code'] );

			return false;
		}

		return $result['choices'];
	}

	/**
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return Xml::encodeJsCall( 'mw.cnBannerControllerLib.setChoiceData',
				array( $this->getChoices( $context ) ) );
	}

	/**
	 * @see ResourceLoaderModule::getPosition()
	 */
	public function getPosition() {
		return 'top';
	}

	/**
	 * @see ResourceLoaderModule::getDependencies()
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return array( 'ext.centralNotice.bannerController.lib' );
	}

	/**
	 * @see ResourceLoaderModule::getModifiedHash()
	 */
	public function getModifiedHash( ResourceLoaderContext $context ) {
		return md5( serialize( $this->getChoices( $context ) ) );
	}
}
