<?php

/***
 * ResourceLoader module for sending banner choices to the client.
 *
 * Note: this module does nothing if $wgCentralNoticeChooseBannerOnClient
 * is false.
 *
 * HTTP query and caching modeled after EventLogging's RemoteSchema class.
 * See https://github.com/wikimedia/mediawiki-extensions-EventLogging/blob/master/includes/RemoteSchema.php
 */
class CNBannerChoiceDataResourceLoaderModule extends ResourceLoaderModule {

	/**
	 * @see ResourceLoaderModule::targets
	 */
	protected $targets = array( 'desktop', 'mobile' );

	const API_REQUEST_TIMEOUT = 20;

	protected $choices;
	protected $key;

	public function __construct() {
		$this->http = new Http();
	}

	protected function getChoices( ResourceLoaderContext $context ) {
		global $wgNoticeProject,
			$wgUser,
			$wgCentralNoticeInfrastructureId,
			$wgCentralNoticeApiUrl,
			$wgCentralNoticeBannerChoiceDataCacheExpiry;

		$project = $wgNoticeProject;
		$language = $context->getLanguage();

		// TODO Find out what's up with $context->getUser()
		$status = ( $wgUser->isAnon() ) ? 'anonymous' : 'loggedin';
		$key = $wgNoticeProject . '|' . $language . '|' .  $status;

		// Get via state variable if it's there and the key is the same
		if ( ( $this->key === $key ) && $this->choices ) {
			return $this->choices;
		}

		$this->key = $key;

		// Hmmm, try the cache (if configured to)
		$useCache = ( $wgCentralNoticeBannerChoiceDataCacheExpiry !== 0 );

		if ( $useCache ) {

			$cache = wfGetCache( CACHE_ANYTHING );
			$this->choices = $cache->get( $key );

			if ( $this->choices ) {
				return $this->choices;
			}
		}

		// OK, fetch the data via the DB or the API. Decide which to use based
		// on whether the appropriate global variables are set.
		// If something's amiss, we warn and return an empty array, but don't
		// bring everything to a standstill.

		if ( $wgCentralNoticeInfrastructureId ) {
			 $choices = $this->getFromDb( $project, $language, $status );

		} else if ( $wgCentralNoticeApiUrl ) {

			$choices = $this->getFromApi( $project, $language, $status );

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

		if ( $useCache ) {
			$cache->set( $key, $choices,
				$wgCentralNoticeBannerChoiceDataCacheExpiry );
		}

		$this->choices = $choices;
		return $choices;
	}

	/**
	 * Get the banner choices data via a direct DB call using
	 * $wgCentralNoticeInfrastructureId.
	 *
	 * @param string $project
	 * @param string $language
	 * @param string $status Can be 'loggedin' or 'anonymous'
	 */
	protected function getFromDb( $project, $language, $status ) {

		$status = ( $status === 'loggedin' ) ?
			BannerChoiceDataProvider::LOGGED_IN :
			BannerChoiceDataProvider::ANONYMOUS;

		$choicesProvider = new BannerChoiceDataProvider(
			$project, $language, $status,
			BannerChoiceDataProvider::USE_INFRASTRUCTURE_DB );

		return $choicesProvider->getChoices();
	}

	/**
	 * Get the banner choices data via an API call to the infrastructure wiki.
	 * If the call fails, we return false.
	 *
	 * @param string $project
	 * @param string $language
	 * @param string $status Can be 'loggedin' or 'anonymous'
	 *
	 * @return array|boolean
	 */
	protected function getFromApi( $project, $language, $status ) {
		global $wgCentralNoticeApiUrl;

		// Make the URl
		$q = array(
			'action' => 'centralnoticebannerchoicedata',
			'project' => $project,
			'language' => $language,
			'status' => $status,
			'format' => 'json'
		);

		$url = wfAppendQuery( $wgCentralNoticeApiUrl, $q );
		$http = new Http();
		$apiResult = $http->get( $url, self::API_REQUEST_TIMEOUT * 0.8 );

		if ( !$apiResult ) {
			return false;
		}

		$parsedApiResult = FormatJson::parse( $apiResult ) ?: false;

		if ( !isset( $parsedApiResult->value ) ) {
			wfLogWarning( 'Error fetching banner choice data via API: ' .
				'no "value" property in result object.' );

			return false;
		}

		$result = $parsedApiResult->value;

		if ( isset( $result->error ) ) {
			wfLogWarning( 'Error fetching banner choice data via API: ' .
				$result->error->info . ': ' . $result->error->code );

			return false;
		}

		return $result; 
	}

	/**
	 * This is a no-op if $wgCentralNoticeChooseBannerOnClient is false
	 *
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		global $wgCentralNoticeChooseBannerOnClient;

		// Only send in data if we'll choose banners on the client
		if ( $wgCentralNoticeChooseBannerOnClient ) {

			return Xml::encodeJsCall( 'mw.cnBannerControllerLib.setChoiceData',
					array( $this->getChoices( $context ) ) );

		} else {
			// Otherwise, make this a no-op
			return '';
		}
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
}