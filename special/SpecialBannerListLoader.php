<?php

class SpecialBannerListLoader extends UnlistedSpecialPage {
	public $project; // Project name
	public $language; // Project language
	public $location; // User country

	const LANG_FILTER = '/[a-zA-Z0-9\-]+/';         // alphanum w/ -
	const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';     // alphanum w/ _ & -
	const LOCATION_FILTER = '/[a-zA-Z][a-zA-Z]/';   // 2 alphas
	const ANONYMOUS_FILTER = '/true|false/';

	protected $sharedMaxAge = 300; // Cache for 5 minutes on the server side
	protected $maxAge = 300; // Cache for 5 minutes on the client side

	function __construct() {
		// Register special page
		parent::__construct( 'BannerListLoader' );
	}

	function execute( $par ) {
		$this->getOutput()->disable();
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

		// Get location from the query string (should be an uppercase 2-letter country code)
		$this->anonymous = $this->getTextAndSanitize(
			'anonymous',
			SpecialBannerListLoader::ANONYMOUS_FILTER,
			true);
		$this->anonymous = ( $this->anonymous === 'true' );

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
		// Pull all campaigns that match the following filter
		$centralNoticeDb = new CentralNoticeDB();
		$chooser = new BannerChooser( $this->project, $this->language, $this->location, $this->anonymous );

		return FormatJson::encode( $chooser->banners );
	}

	/**
	 * Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 * @return null|string The sanitized value
	 */
	protected function getTextAndSanitize( $param, $regex, $default = null ) {
		if ( preg_match( $regex, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		} else {
			return $default;
		}
	}
}
