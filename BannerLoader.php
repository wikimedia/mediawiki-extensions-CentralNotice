<?php

/**
 * Generates banner HTML files
 */
class BannerLoader extends UnlistedSpecialPage {
	var $project = 'wikipedia'; // Project name
	var $language = 'en'; // User language
	protected $sharedMaxAge = 22; // Cache for ? hours on the server side
	protected $maxAge = 0; // No client-side banner caching so we get all impressions
	protected $contentType = 'text/html';
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}
	
	function execute( $par ) {
		global $wgOut, $wgRequest;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		// Get user language from the query string
		$this->language = htmlspecialchars( $wgRequest->getText( 'language', 'en' ) );
		
		// Get project name from the query string
		$this->project = htmlspecialchars( $wgRequest->getText( 'project', 'wikipedia' ) );
		
		if ( $wgRequest->getText( 'banner' ) ) {
			$bannerName = htmlspecialchars( $wgRequest->getText( 'banner' ) );
			$content = $this->getHtmlNotice( $bannerName );
			if ( strlen( $content ) == 0 ) {
				// Hack for IE/Mac 0-length keepalive problem, see RawPage.php
				echo "/* Empty */";
			} else {
				echo $content;
			}
		} else {
			echo "/* No banner specified */";
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
	 * Generate the HTML for the requested banner
	 */
	function getHtmlNotice( $bannerName ) {
		// Make sure the banner exists
		if ( SpecialNoticeTemplate::templateExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			return preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
		}
	}

	function getNoticeTemplate() {
		return $this->getMessage( "centralnotice-template-{$this->bannerName}" );
	}

	function getNoticeField( $matches ) {
		$field = $matches[1];
		$params = array();
		if ( $field == 'amount' ) {
			$params = array( $this->formatNum( $this->getDonationAmount() ) );
		}
		$message = "centralnotice-{$this->bannerName}-$field";
		$source = $this->getMessage( $message, $params );
		return $source;
	}
	
	private function formatNum( $num ) {
		$num = sprintf( "%.1f", $num / 1e6 );
		if ( substr( $num, - 2 ) == '.0' ) {
		$num = substr( $num, 0, - 2 );
		}
		$lang = Language::factory( $this->language );
		return $lang->formatNum( $num );
	}

	private function getMessage( $msg, $params = array() ) {
		// A god-damned dirty hack! :D
		$old = array();
		$old['wgSitename'] = $GLOBALS['wgSitename'];
		$old['wgLang'] = $GLOBALS['wgLang'];

		$GLOBALS['wgSitename'] = $this->projectName();
		$GLOBALS['wgLang'] = Language::factory( $this->language ); // hack for {{int:...}}

		$options = array(
			'language' => $this->language,
			'parsemag',
		);
		array_unshift( $params, $options );
		array_unshift( $params, $msg );
		$out = call_user_func_array( 'wfMsgExt', $params );

		// Restore globals
		$GLOBALS['wgSitename'] = $old['wgSitename'];
		$GLOBALS['wgLang'] = $old['wgLang'];

		return $out;
	}
	
	private function projectName() {
		global $wgConf;

		$wgConf->loadFullData();

		// Special cases for commons and meta who have no lang
		if ( $this->project == 'commons' )
			return "Commons";
		else if ( $this->project == 'meta' )
			return "Wikimedia";

		// Guess dbname since we don't have it atm
		$dbname = $this->language .
			( ( $this->project == 'wikipedia' ) ? "wiki" : $this->project );
		$name = $wgConf->get( 'wgSitename', $dbname, $this->project,
			array( 'lang' => $this->language, 'site' => $this->project ) );

		if ( $name ) {
			return $name;
		} else {
			global $wgLang;
			return $wgLang->ucfirst( $this->project );
		}
	}
	
	/**
	 * Pull the current amount raised during a fundraiser
	 */
	private function getDonationAmount() {
		global $wgNoticeCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( 'centralnotice:counter' ) );
		if ( !$count ) {
			// Pull from dynamic counter
			$count = intval( @file_get_contents( $wgNoticeCounterSource ) );
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get( 'centralnotice:counter:fallback' ) );
				if ( !$count ) {
					// Return hard-coded amount if all else fails
					return 100; // Update as needed during fundraiser
				}
			}
			$wgMemc->set( 'centralnotice:counter', $count, 60 ); // Expire in 60 seconds
			$wgMemc->set( 'centralnotice:counter:fallback', $count ); // No expiration
		}
		return $count;
	}
	
}
