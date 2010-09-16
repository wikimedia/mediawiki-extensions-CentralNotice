<?php

/**
 * Generates JSON files listing all the banners for a particular site
 */
class SpecialBannerListLoader extends UnlistedSpecialPage {
	public $project; // Project name
	public $language; // Project language
	public $location; // User country
	public $centralNoticeDB;
	protected $sharedMaxAge = 900; // Cache for 15 minutes on the server side
	protected $maxAge = 900; // Cache for 15 minutes on the client side
	protected $contentType = 'text/javascript';
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerListLoader" );
	}
	
	function execute( $par ) {
		global $wgOut, $wgRequest, $wgDBname, $wgCentralDBname;
		
		// Temporarily switch to central wiki database
		$localDBname = $wgDBname;
		$wgDBname = $wgCentralDBname;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		// Get project language from the query string
		$this->language = $wgRequest->getText( 'language', 'en' );
		
		// Get project name from the query string
		$this->project = $wgRequest->getText( 'project', 'wikipedia' );
		
		// Get location from the query string
		$this->location = $wgRequest->getText( 'location' );
		
		if ( $this->project && $this->language ) {
			$content = $this->getJsonList();
			if ( strlen( $content ) == 0 ) {
				// Hack for IE/Mac 0-length keepalive problem, see RawPage.php
				echo "/* Empty */";
			} else {
				echo $content;
			}
		} else {
			echo "/* No site specified */";
		}
		
		// Switch back to local wiki database
		$wgDBname = $localDBname;
	}
	
	/**
	 * Generate the HTTP response headers for the banner file
	 */
	function sendHeaders() {
		header( "Content-type: $this->contentType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}
	
	/**
	 * Generate JSON for the specified site
	 */
	function getJsonList() {
		
		// Quick short circuit to be able to show preferred notices
		$templates = array();

		if ( $this->language == 'en' && $this->project != null ) {
			// See if we have any preferred notices for all of en
			$notices = CentralNoticeDB::getNotices( null, 'en', null, 1, 1, $this->location );

			if ( $notices ) {
				// Pull banners
				$templates = CentralNoticeDB::selectTemplatesAssigned( $notices );
			}
		}

		if ( !$templates && $this->project == 'wikipedia' ) {
			// See if we have any preferred notices for this language wikipedia
			$notices = CentralNoticeDB::getNotices( 'wikipedia', $this->language, null, 1, 1, $this->location );
			
			if ( $notices ) {
				// Pull banners
				$templates = CentralNoticeDB::selectTemplatesAssigned( $notices );
			}
		}

		// Didn't find any preferred matches so do an old style lookup
		if ( !$templates )  {
			$templates = CentralNotice::selectNoticeTemplates( $this->project, $this->language, $this->location );
		}
		
		return FormatJson::encode( $templates );
	}
	
}
