<?php

use MediaWiki\Config\ConfigException;
use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

/**
 * ResourceLoader module for sending banner choices to the client.
 *
 * Note: This class has been intentionally left stateless, due to how
 * ResourceLoader works. This class has no expectation of having getScript() or
 * getModifiedHash() called in the same request.
 */
class CNChoiceDataResourceLoaderModule extends RL\Module {

	private const API_REQUEST_TIMEOUT = 20;

	/**
	 * @param RL\Context $context
	 * @return array
	 */
	protected function getChoices( RL\Context $context ) {
		$config = $this->getConfig();
		$project = $config->get( 'NoticeProject' );
		$language = $context->getLanguage();

		// Only fetch the data via the API if $wgCentralNoticeApiUrl is set.
		// Otherwise, use the DB.
		$apiUrl = $config->get( 'CentralNoticeApiUrl' );
		if ( $apiUrl ) {
			$choices = $this->getFromApi( $project, $language );

			if ( !$choices ) {
				wfLogWarning( 'Couldn\'t fetch banner choice data via API. ' .
					'wgCentralNoticeApiUrl = ' . $apiUrl );

				return [];
			}
		} else {
			$choices = ChoiceDataProvider::getChoices( $project, $language );
		}

		return $choices;
	}

	/**
	 * Get the banner choices data via an API call to the infrastructure wiki.
	 * If the call fails, we return false.
	 *
	 * @param string $project
	 * @param string $language
	 *
	 * @return array|bool
	 */
	private function getFromApi( $project, $language ) {
		$cnApiUrl = $this->getConfig()->get( 'CentralNoticeApiUrl' );

		// Make the URL
		$q = [
			'action' => 'centralnoticechoicedata',
			'project' => $project,
			'language' => $language,
			'format' => 'json',
			// Prevents stripping of false values 8p
			'formatversion' => 2
		];

		$url = wfAppendQuery( $cnApiUrl, $q );

		$apiResult = MediaWikiServices::getInstance()->getHttpRequestFactory()->get(
			$url,
			[ 'timeout' => self::API_REQUEST_TIMEOUT * 0.8 ],
			__METHOD__
		);

		if ( !$apiResult ) {
			wfLogWarning( 'Couldn\'t get banner choice data via API.' );
			return false;
		}

		$parsedApiResult = FormatJson::parse( $apiResult, FormatJson::FORCE_ASSOC );

		if ( !$parsedApiResult->isGood() ) {
			wfLogWarning( 'Couldn\'t parse banner choice data from API.' );
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
	 * @inheritDoc
	 */
	public function getScript( RL\Context $context ) {
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
				Html::encodeJsVar( $choices ) . ';';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getDependencies( ?RL\Context $context = null ) {
		$cnCampaignMixins = $this->getConfig()->get( 'CentralNoticeCampaignMixins' );

		// If this method is called with no context argument (the old method
		// signature) emit a warning, but don't stop the show.
		if ( !$context ) {
			wfLogWarning( '$context is required for campaign mixins.' );
			return [];
		}

		// Get the choices (possible campaigns and banners) for this user
		$choices = $this->getChoices( $context );
		if ( !$choices ) {
			// If there are no choices, no dependencies
			return [];
		}

		// Run through the choices to get all needed mixin RL modules
		$dependencies = [];
		foreach ( $choices as $choice ) {
			foreach ( $choice['mixins'] as $mixinName => $mixinParams ) {
				if ( !$cnCampaignMixins[$mixinName]['subscribingModule'] ) {
					throw new ConfigException(
						"No subscribing module for found campaign mixin {$mixinName}" );
				}

				$dependencies[] =
					$cnCampaignMixins[$mixinName]['subscribingModule'];
			}
		}

		// The display module is needed to process choices
		$dependencies[] = 'ext.centralNotice.display';

		// Since campaigns targeting the user could have the same mixin RL
		// modules, remove any duplicates.
		return array_unique( $dependencies );
	}

	/**
	 * @inheritDoc
	 */
	public function getDefinitionSummary( RL\Context $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'choices' => $this->getChoices( $context ),
		];
		return $summary;
	}
}
