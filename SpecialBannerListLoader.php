<?php

/**
 * Generates JSON files listing all the banners for a particular site
 */
class SpecialBannerListLoader extends UnlistedSpecialPage {
	public $project; // Project name
	public $language; // Project language
	public $centralNoticeDB;
	protected $sharedMaxAge = 22; // Cache for ? minutes on the server side
	protected $maxAge = 10; // Cache for ? minutes on the client side
	protected $contentType = 'text/javascript';
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerListLoader" );
		$this->centralNoticeDB = new CentralNoticeDB();
	}
	
	function execute( $par ) {
		global $wgOut, $wgRequest;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		// Get project language from the query string
		$this->language = htmlspecialchars( $wgRequest->getText( 'language', 'en' ) );
		
		// Get project name from the query string
		$this->project = htmlspecialchars( $wgRequest->getText( 'project', 'wikipedia' ) );
		
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
			$notices = $this->centralNoticeDB->getNotices( '', 'en', '', '', 1 );

			if ( $notices ) {
				// Pull out values
				foreach ( $notices as $notice => $val ) {
					// Either match against ALL project or a specific project
					if ( $val['project'] == '' || $val['project'] == $this->project ) {
						$templates = $this->centralNoticeDB->selectTemplatesAssigned( $notice );
						break;
					}
				}
			}
		}

		if ( !$templates && $this->project == 'wikipedia' ) {
			$notices = $this->centralNoticeDB->getNotices( 'wikipedia', $this->language, '', '', 1 );
			if ( $notices && is_array( $notices ) ) {
				foreach ( $notices as $notice => $val ) {
					$templates = $this->centralNoticeDB->selectTemplatesAssigned( $notice );
					break;
				}
			}
		}

		// Didn't find any preferred matches so do an old style lookup
		if ( !$templates )  {
			$templates = CentralNotice::selectNoticeTemplates( $this->project, $this->language );
		}
		
		return json_encode( $templates );
	}
	
}
