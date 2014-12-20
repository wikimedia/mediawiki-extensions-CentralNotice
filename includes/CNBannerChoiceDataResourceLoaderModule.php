<?php

/***
 * ResourceLoader module for sending banner choices to the client.
 *
 * Note: this module does nothing if $wgCentralNoticeChooseBannerOnClient
 * is false.
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
		global $wgNoticeProject,
			$wgUser,
			$wgCentralNoticeApiUrl,
			$wgCentralDBname,
			$wgCentralNoticeChooseBannerOnClient;

		if ( !$wgCentralNoticeChooseBannerOnClient ) {
			return null;
		}

		$project = $wgNoticeProject;
		$language = $context->getLanguage();

		// Fetch the data via the DB or the API. Decide which to use based
		// on whether the appropriate global variables are set.
		// If something's amiss, we warn and return an empty array, but don't
		// bring everything to a standstill.

		if ( $wgCentralDBname ) {
			 $choices = $this->getFromDb( $project, $language );

		} else if ( $wgCentralNoticeApiUrl ) {

			$choices = $this->getFromApi( $project, $language );

			if ( !$choices ) {
				wfLogWarning( 'Couldn\'t fetch banner choice data via API. ' .
					'$$wgCentralNoticeApiUrl = ' . $wgCentralNoticeApiUrl );

				return array();
			}

		} else {
			// No way to get the choices?
			wfLogWarning( 'No route to fetch banner choice data configured.' );
			return array();
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
			'format' => 'json'
		);

		$url = wfAppendQuery( $wgCentralNoticeApiUrl, $q );
		$apiResult = Http::get( $url, self::API_REQUEST_TIMEOUT * 0.8 );

		if ( !$apiResult ) {
			wfLogWarning( 'Couldn\'t get banner choice data via API.');
			return false;
		}

		$parsedApiResult = FormatJson::parse( $apiResult );

		if ( !$parsedApiResult->isGood() ) {
			wfLogWarning( 'Couldn\'t parse banner choice data from API.');
			return false;
		}

		$result = $parsedApiResult->getValue();

		if ( isset( $result->error ) ) {
			wfLogWarning( 'Error fetching banner choice data via API: ' .
				$result->error->info . ': ' . $result->error->code );

			return false;
		}

		return $result->choices;
	}

	/**
	 * This is a no-op if $wgCentralNoticeChooseBannerOnClient is false
	 *
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		global $wgCentralNoticeChooseBannerOnClient;

		// If we don't choose banners on the client, this is a no-op
		if ( !$wgCentralNoticeChooseBannerOnClient ) {
			return '';
		}

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
	public function getDependencies() {
		return array( 'ext.centralNotice.bannerController.lib' );
	}

	/**
	 * @see ResourceLoaderModule::getModifiedTime()
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return max( 1, $this->getHashMtime( $context ) );
	}

	/**
	 * @see ResourceLoaderModule::getModifiedHash()
	 */
	public function getModifiedHash( ResourceLoaderContext $context ) {
		return md5( serialize( $this->getChoices( $context ) ) );
	}
}
