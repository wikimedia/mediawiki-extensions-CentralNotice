<?php

/***
 * ResourceLoader module for sending banner choices to the client.
 *
 * Note: This class has been intentionally left stateless, due to how
 * ResourceLoader works. This class has no expectation of having getScript() or
 * getModifiedHash() called in the same request.
 */
class CNChoiceDataResourceLoaderModule extends ResourceLoaderModule {

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
		$choicesProvider = new ChoiceDataProvider( $project, $language );
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
			'action' => 'centralnoticechoicedata',
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

		$choices = $this->getChoices( $context );
		if ( !$choices ) {
			// If there are no choices, this module will have no dependencies,
			// but other modules that create mw.centralNotice may be brought
			// in elsewhere. Let's the check for its existence here, too, for
			// robustness.
			return 'mw.centralNotice = ( mw.centralNotice || {} );' .
				'mw.centralNotice.choiceData = [];';
		} else {

			// If there are choices, this module should depend on (at least)
			// ext.centralNotice.display, which will create mw.centralNotice.
			// However, RL may experience errors that cause these dynamic
			// dependencies to not be set as expected; so we check, just in case.
			// In such an error state, ext.centralNotice.startUp.js logs to the
			// console.
			return 'mw.centralNotice = ( mw.centralNotice || {} );' .
				'mw.centralNotice.choiceData = ' .
				Xml::encodeJsVar( $choices ) . ';';
		}
	}

	/**
	 * @see ResourceLoaderModule::getDependencies()
	 * Note: requires mediawiki-core change-id @Iee61e5b52
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		global $wgCentralNoticeCampaignMixins;

		// If this method is called with no context argument (the old method
		// signature) emit a warning, but don't stop the show.
		if ( !$context ) {
			 wfLogWarning( '$context is required for campaign mixins.' );
			 return array();
		}

		// Get the choices (possible campaigns and banners) for this user
		$choices = $this->getChoices( $context );
		if ( !$choices ) {
			// If there are no choices, no dependencies
			return array();
		}

		// Run through the choices to get all needed mixin RL modules
		$dependencies = array();
		foreach ( $choices as $choice ) {
			foreach ( $choice['mixins'] as $mixinName => $mixinParams ) {

				if ( !$wgCentralNoticeCampaignMixins[$mixinName]['module'] ) {
					throw new MWException(
						"No module for found campaign mixin {$mixinName}" );
				}

				$dependencies[] =
					$wgCentralNoticeCampaignMixins[$mixinName]['module'];
			}
		}

		// The display module is needed to process choices
		$dependencies[] = 'ext.centralNotice.display';

		// Since campaigns targeting the user could have the same mixin RL
		// modules, remove any duplicates.
		return array_unique( $dependencies );
	}

	/**
	 * @see ResourceLoaderModule::getModifiedHash()
	 */
	public function getModifiedHash( ResourceLoaderContext $context ) {
		return md5( serialize( $this->getChoices( $context ) ) );
	}
}
