<?php

/**
 * Implementation of the query=centralnoticeallocations API call. This call returns the expected banner
 * allocation for the given project, country, and language.
 *
 * @todo: This needs some major cleanup to work more like the rest of the API,
 * starting with "this is a JSON only call".
 */
class ApiCentralNoticeAllocations extends ApiCentralNoticeAllocationBase {

	const DEFAULT_PROJECT = 'wikipedia';
	const DEFAULT_COUNTRY = 'XX';
	const DEFAULT_LANGUAGE = 'en';
	const DEFAULT_ANONYMOUS = 'true';
	const DEFAULT_DEVICE_NAME = 'desktop';
	const DEFAULT_BUCKET = null;

	/**
	 * @var string Pattern for 2 alphas
	 */
	const LOCATION_FILTER = '/[a-zA-Z][a-zA-Z0-9]/';
	/**
	 * @var string Pattern for a the requesting device type name, ie: desktop, iphone
	 */
	const DEVICE_NAME_FILTER = '/[a-zA-Z0-9\-_]+/';
	/**
	 * @var string Pattern for int
	 */
	const BUCKET_FILTER = '/[0-9]+/';

	// TODO make the anon param a boolean and remove this?
	/**
	 * @var string Pattern for bool
	 */
	const ANONYMOUS_FILTER = '/true|false/';

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
		global $wgNoticeNumberOfBuckets;

		$params = array();

		$params['project']  = ApiCentralNoticeAllocations::DEFAULT_PROJECT;
		$params['country']  = ApiCentralNoticeAllocations::DEFAULT_COUNTRY;
		$params['language'] = ApiCentralNoticeAllocations::DEFAULT_LANGUAGE;
		$params['anonymous']= ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS;
		$params['device'] = ApiCentralNoticeAllocations::DEFAULT_DEVICE_NAME;
		$params['bucket']   = ApiCentralNoticeAllocations::DEFAULT_BUCKET;
		if ( defined( 'ApiBase::PARAM_HELP_MSG' ) ) {
			$params['bucket'] = array(
				ApiBase::PARAM_DFLT => ApiCentralNoticeAllocations::DEFAULT_BUCKET,
				ApiBase::PARAM_HELP_MSG => array(
					'apihelp-centralnoticeallocations-param-bucket', $wgNoticeNumberOfBuckets
				),
			);
		}

		return $params;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
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

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Obtain the banner allocations for banners served by CentralNotice for all user types under the parametric filter. This is a JSON only call.';
	}

	/**
	 * Example API calls.
	 *
	 * @deprecated since MediaWiki core 1.25
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=centralnoticeallocations&format=json&project=wikipedia&country=US&anonymous=true&bucket=1&language=en";
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=centralnoticeallocations&project=wikipedia&country=US&anonymous=true&bucket=1&language=en'
				=> 'apihelp-centralnoticeallocations-example-1',
		);
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
		$project = parent::sanitizeText(
			$project,
			parent::PROJECT_FILTER,
			self::DEFAULT_PROJECT
		);

		$country = parent::sanitizeText(
			$country,
			self::LOCATION_FILTER,
			self::DEFAULT_COUNTRY
		);

		$language = parent::sanitizeText(
			$language,
			parent::LANG_FILTER,
			self::DEFAULT_LANGUAGE
		);

		$anonymous = parent::sanitizeText(
			$anonymous,
			self::ANONYMOUS_FILTER,
			self::DEFAULT_ANONYMOUS
		);
		$anonymous = ( $anonymous == 'true' );

		$device = parent::sanitizeText(
			$device,
			self::DEVICE_NAME_FILTER,
			self::DEFAULT_DEVICE_NAME
		);

		$bucket = parent::sanitizeText(
			$bucket,
			self::BUCKET_FILTER,
			self::DEFAULT_BUCKET
		);

		$allocContext = new AllocationContext( $country, $language, $project, $anonymous, $device, $bucket );

		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		return $banners;
	}
}
