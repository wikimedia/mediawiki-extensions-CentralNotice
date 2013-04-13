<?php

/**
 * Implementation of the query=centralnoticeallocations API call. This call returns the expected banner
 * allocation for the given project, country, and language.
 */
class ApiCentralNoticeAllocations extends ApiBase {

	const DEFAULT_PROJECT = 'wikipedia';
	const DEFAULT_COUNTRY = 'XX';
	const DEFAULT_LANGUAGE = 'en';
	const DEFAULT_ANONYMOUS = 'true';
	const DEFAULT_DEVICE_NAME = 'desktop';
	const DEFAULT_BUCKET = null;

	/**
	 * @var string Pattern for alphanum w/ -
	 */
	const LANG_FILTER = '/[a-zA-Z0-9\-]+/';
	/**
	 * @var string Pattern for alphanum w/ _ & -
	 */
	const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';
	/**
	 * @var string Pattern for 2 alphas
	 */
	const LOCATION_FILTER = '/[a-zA-Z][a-zA-Z0-9]/';
	/**
	 * @var string Pattern for bool
	 */
	const ANONYMOUS_FILTER = '/true|false/';
	/**
	 * @var string Pattern for a the requesting device type name, ie: desktop, iphone
	 */
	const DEVICE_NAME_FILTER = '/[a-zA-Z0-9\-_]+/';
	/**
	 * @var string Pattern for int
	 */
	const BUCKET_FILTER = '/[0-9]+/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		$bannerList = static::getBannerAllocation(
			$params['project'],
			$params['country'],
			$params['language'],
			$params['anonymous'],
			$params['device'],
			$params['bucket']
		);

		$result->setIndexedTagName( $bannerList, 'BannerAllocation' );
		$result->addValue( $this->getModuleName(), 'banners', $bannerList );
	}

	public function getAllowedParams() {
		$params = array();

		$params['project']  = ApiCentralNoticeAllocations::DEFAULT_PROJECT;
		$params['country']  = ApiCentralNoticeAllocations::DEFAULT_COUNTRY;
		$params['language'] = ApiCentralNoticeAllocations::DEFAULT_LANGUAGE;
		$params['anonymous']= ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS;
		$params['device'] = ApiCentralNoticeAllocations::DEFAULT_DEVICE_NAME;
		$params['bucket']   = ApiCentralNoticeAllocations::DEFAULT_BUCKET;

		return $params;
	}

	public function getParamDescription() {
		global $wgNoticeNumberOfBuckets;

		$params = array();

		$params['project']  = "The project to obtain allocations under";
		$params['country']  = "The country to filter on";
		$params['language'] = "The language to filter on";
		$params['anonymous']= "The logged-in status to filter on (true|false)";
		$params['device']   = "Device name to filter on";
		$params['bucket']   = "The bucket to filter on, by number (0 .. $wgNoticeNumberOfBuckets, optional)";

		return $params;
	}

	public function getDescription() {
		return 'Obtain the banner allocations for banners served by CentralNotice for all user types under the parametric filter. This is a JSON only call.';
	}

	public function getVersion() {
		return 'CentralNoticeAllocations: 1.0';
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=centralnoticeallocations&format=json&project=wikipedia&country=US&anonymous=true&bucket=1&language=en";
	}

	/**
	 * MediaWiki interface to this API call -- obtains banner allocation information; ie how many
	 * buckets there are in a campaign, and what banners should be displayed for a given filter.
	 *
	 * Returns results as an array of banners
	 *  - banners
	 *
	 *              - name          The name of the banner
	 *              - allocation    What the allocation proportion (0 to 1) should be
	 *              - campaign      The name of the associated campaign
	 *              - fundraising   1 if this is a fundraising banner
	 *              - bucket        The bucket this is assigned to in the campaign
	 *              - weight            The assigned weight in the campaign
	 *              - display_anon      1 if should be displayed to anonymous users
	 *              - display_account   1 if should be displayed to logged in users
	 *              - autolink          1 if landing page links should be auto created
	 *              - landing_pags      String collection of fundraising landing pages for onclick
	 *              - campaign_z_index  Priority of the associated campaign
	 *
	 * @param string $project   - Project name, ie 'wikipedia'
	 * @param string $country   - ISO country name, ie 'US'
	 * @param string $language  - ISO language name, ie 'en'
	 * @param string $anonymous - Is user anonymous, eg 'true'
	 * @param string $device    - What device to filter on, eg 'desktop' or 'mobile.device.ie'
	 * @param string $bucket    - Which A/B bucket the user is in
	 *
	 * @return array
	 */
	public static function getBannerAllocation( $project, $country, $language, $anonymous, $device, $bucket = null ) {
		$project = ApiCentralNoticeAllocations::sanitizeText(
			$project,
			self::PROJECT_FILTER,
			self::DEFAULT_PROJECT
		);

		$country = ApiCentralNoticeAllocations::sanitizeText(
			$country,
			self::LOCATION_FILTER,
			self::DEFAULT_COUNTRY
		);

		$language = ApiCentralNoticeAllocations::sanitizeText(
			$language,
			self::LANG_FILTER,
			self::DEFAULT_LANGUAGE
		);

		$anonymous = ApiCentralNoticeAllocations::sanitizeText(
			$anonymous,
			self::ANONYMOUS_FILTER,
			self::DEFAULT_ANONYMOUS
		);
		$anonymous = ( $anonymous == 'true' );

		$device = ApiCentralNoticeAllocations::sanitizeText(
			$device,
			static::DEVICE_NAME_FILTER,
			static::DEFAULT_DEVICE_NAME
		);

		$bucket = ApiCentralNoticeAllocations::sanitizeText(
			$bucket,
			self::BUCKET_FILTER,
			self::DEFAULT_BUCKET
		);

		$allocContext = new AllocationContext( $country, $language, $project, $anonymous, $device, $bucket );

		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		return $banners;
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
	private static function sanitizeText( $param, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
