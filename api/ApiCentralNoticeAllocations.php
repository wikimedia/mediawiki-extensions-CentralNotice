<?php

/**
 * Implementation of the query=centralnoticeallocations API call. This call returns the expected banner
 * allocation for the given project, country, and language.
 */
class ApiCentralNoticeAllocations extends ApiBase {

	const DEFAULT_PROJECT = 'wikipedia';
	const DEFAULT_COUNTRY = null;
	const DEFAULT_LANGUAGE = 'en';

	public function execute() {

		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		//TODO typo
		$project = ApiCentralNoticeAllocations::sanatizeText(
			$params[ 'project' ],
			SpecialBannerListLoader::PROJECT_FILTER,
			ApiCentralNoticeAllocations::DEFAULT_PROJECT
		);

		$country = ApiCentralNoticeAllocations::sanatizeText(
			$params[ 'country' ],
			SpecialBannerListLoader::LOCATION_FILTER,
			ApiCentralNoticeAllocations::DEFAULT_COUNTRY
		);

		$language = ApiCentralNoticeAllocations::sanatizeText(
			$params[ 'language' ],
			SpecialBannerListLoader::LANG_FILTER,
			ApiCentralNoticeAllocations::DEFAULT_LANGUAGE
		);

		// TODO api handles single or plural criteria
		$anonChooser = new BannerChooser( $project, $language, $country, true );
		$anonBanners = $anonChooser->banners;
		$accountChooser = new BannerChooser( $project, $language, $country, false );
		$accountBanners = $accountChooser->banners;

		$result->addValue( array( 'cn-banner-allocations' ), 'anon', $anonBanners );
		$result->addValue( array( 'cn-banner-allocations' ), 'account', $accountBanners );
	}

	public function getAllowedParams() {
		$params = array();

		$params[ 'project' ] = ApiCentralNoticeAllocations::DEFAULT_PROJECT;
		$params[ 'country' ] = ApiCentralNoticeAllocations::DEFAULT_COUNTRY;
		$params[ 'language' ] = ApiCentralNoticeAllocations::DEFAULT_LANGUAGE;

		return $params;
	}

	public function getParamDescription() {
		$params = array();

		$params[ 'project' ] = "The project to obtain allocations under";
		$params[ 'country' ] = "The country to filter on";
		$params[ 'language' ] = "The language to filter on";

		return $params;
	}

	public function getDescription() {
		return 'Obtain the banner allocations for banners served by CentralNotice for all user types under the parametric filter. This is a JSON only call.';
	}

	public function getVersion() {
		return 'CentralNoticeAllocations: 1.0';
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @params array    $params   Array of parameters to extract data from
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	static function sanatizeText( $param, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=centralnoticeallocations&format=json&project=wikipedia&country=US&language=en";
	}
}
