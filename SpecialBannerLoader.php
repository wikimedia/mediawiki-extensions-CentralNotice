<?php

/**
 * Generates banner HTML files
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	public $siteName = 'Wikipedia'; // Site name
	public $language = 'en'; // User language
	protected $sharedMaxAge = 600; // Cache for 10 minutes on the server side
	protected $maxAge = 0; // No client-side banner caching so we get all impressions
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}
	
	function execute( $par ) {
		global $wgOut, $wgRequest;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		// Get user language from the query string
		$this->language = $wgRequest->getText( 'userlang', 'en' );
		
		// Get site name from the query string
		$this->siteName = $wgRequest->getText( 'sitename', 'Wikipedia' );
		
		if ( $wgRequest->getText( 'banner' ) ) {
			$bannerName = $wgRequest->getText( 'banner' );
			$content = $this->getJsNotice( $bannerName );
			if ( preg_match( "/&lt;centralnotice-template-\w{1,}&gt;\z/", $content ) ) {
				echo "/* Failed cache lookup */";
			} elseif ( strlen( $content ) == 0 ) {
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
		global $wgJsMimeType;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}
	
	/**
	 * Generate the JS for the requested banner
	 * @return a string of Javascript containing a call to insertBanner() 
	 *   with JSON containing the banner content as the parameter
	 */
	function getJsNotice( $bannerName ) {
		// Make sure the banner exists
		if ( SpecialNoticeTemplate::templateExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			$bannerHtml = '';
			$bannerHtml .= preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
			$bannerArray = array( 'banner' => $bannerHtml );
			$bannerJs = 'insertBanner('.FormatJson::encode( $bannerArray ).');';
			return $bannerJs;
		}
	}
	
	/**
	 * Generate the HTML for the requested banner
	 */
	function getHtmlNotice( $bannerName ) {
		// Make sure the banner exists
		if ( SpecialNoticeTemplate::templateExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			$bannerHtml = '';
			$bannerHtml .= preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
			return $bannerHtml;
		}
	}

	/**
	 * Get the body of the banner with only {{int:...}} messages translated
	 */
	function getNoticeTemplate() {
		$out = $this->getMessage( "centralnotice-template-{$this->bannerName}" );
		return $out;
	}

	/**
	 * Extract a message name and send to getMessage() for translation
	 * @param $match A message array with 2 members: raw match, short name of message
	 * @return translated messsage string
	 */
	function getNoticeField( $match ) {
		$field = $match[1];
		$params = array();
		if ( $field == 'amount' ) {
			$params = array( $this->formatNum( $this->getDonationAmount() ) );
		}
		$message = "centralnotice-{$this->bannerName}-$field";
		$source = $this->getMessage( $message, $params );
		return $source;
	}

	/**
	 * Convert number of dollars to millions of dollars
	 */
	private function formatNum( $num ) {
		$num = sprintf( "%.1f", $num / 1e6 );
		if ( substr( $num, - 2 ) == '.0' ) {
			$num = substr( $num, 0, - 2 );
		}
		$lang = Language::factory( $this->language );
		return $lang->formatNum( $num );
	}

	/**
	 * Retrieve a translated message
	 * @param $msg The full name of the message
	 * @return translated messsage string
	 */
	private function getMessage( $msg, $params = array() ) {
		global $wgLang, $wgSitename;

		// A god-damned dirty hack! :D
		$oldLang = $wgLang;
		$oldSitename = $wgSitename;

		$wgSitename = $this->siteName; // hack for {{SITENAME}}
		$wgLang = Language::factory( $this->language ); // hack for {{int:...}}

		$options = array( 'language' => $this->language, 'parsemag' );
		array_unshift( $params, $options );
		array_unshift( $params, $msg );
		$out = call_user_func_array( 'wfMsgExt', $params );

		// Restore global variables
		$wgLang = $oldLang;
		$wgSitename = $oldSitename;

		return $out;
	}
	
	/**
	 * Pull the current amount raised during a fundraiser
	 */
	private function getDonationAmount() {
		global $wgNoticeCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( wfMemcKey( 'centralnotice', 'counter' ) ) );
		if ( !$count ) {
			// Pull from dynamic counter
			wfSuppressWarnings();
			$count = intval( file_get_contents( $wgNoticeCounterSource ) );
			wfRestoreWarnings();
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get( 
					wfMemcKey( 'centralnotice', 'counter', 'fallback' ) ) );
				if ( !$count ) {
					// Return hard-coded amount if all else fails
					return 1100000; // Update as needed during fundraiser
				}
			}
			// Expire in 60 seconds
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter' ), $count, 60 );
			// No expiration
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter', 'fallback' ), $count );
		}
		return $count;
	}
}
