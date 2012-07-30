<?php

/**
 * Generates JSON files listing all the banners for a particular site
 */
class SpecialBannerListLoader extends UnlistedSpecialPage {
	public $project; // Project name
	public $language; // Project language
	public $location; // User country

	const LANG_FILTER = '/[a-zA-Z0-9\-]+/';         // alphanum w/ -
	const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';     // alphanum w/ _ & -
	const LOCATION_FILTER = '/[a-zA-Z][a-zA-Z]/';   // 2 alphas

	protected $sharedMaxAge = 300; // Cache for 5 minutes on the server side
	protected $maxAge = 300; // Cache for 5 minutes on the client side

	function __construct() {
		// Register special page
		parent::__construct( "BannerListLoader" );
	}

	function execute( $par ) {
		global $wgOut;

		$wgOut->disable();
		$this->sendHeaders();

		// Get project language from the query string; valid is alphanum w/ -
		$this->language = $this->getTextAndSanitize(
			'language',
			SpecialBannerListLoader::LANG_FILTER,
			'en');

		// Get project name from the query string; valid is alphanum w/ _ and -
		$this->project = $this->getTextAndSanitize(
			'project',
			SpecialBannerListLoader::PROJECT_FILTER,
			'wikipedia');

		// Get location from the query string (should be an uppercase 2-letter country code)
		$this->location = $this->getTextAndSanitize(
			'country',
			SpecialBannerListLoader::LOCATION_FILTER,
			null);

		// Now create the JSON list of all banners
		$content = $this->getJsonList();
		if ( strlen( $content ) == 0 ) {
			// Hack for IE/Mac 0-length keepalive problem, see RawPage.php
			echo "/* Empty */";
		} else {
			echo $content;
		}
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	function sendHeaders() {
		global $wgJsMimeType;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}

	/**
	 * Generate JSON for the specified site
	 */
	function getJsonList() {
		$banners = array();

		// Pull all campaigns that match the following filter
		$campaigns = CentralNoticeDB::getCampaigns( $this->project, $this->language, $this->location );

		// Pull banners
		$banners = CentralNoticeDB::getCampaignBanners( $campaigns );

		return FormatJson::encode( $banners );
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 * @return The sanitized value
	 */
	static function getTextAndSanitize( $param, $regex, $default = null ) {
		global $wgRequest;

		if ( preg_match( $regex, $wgRequest->getText( $param ), $matches ) ) {
			return $matches[0];
		} else {
			return $default;
		}
	}
}
