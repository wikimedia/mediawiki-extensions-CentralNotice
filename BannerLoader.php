<?php

/**
 * Generates banner HTML files
 */
class BannerLoader extends UnlistedSpecialPage {
	public $siteName = 'Wikipedia'; // Site name
	public $language = 'en'; // User language
	protected $sharedMaxAge = 22; // Cache for 2 hours on the server side
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
		$this->language = $wgRequest->getText( 'userlang', 'en' );
		
		// Get site name from the query string
		$this->siteName = $wgRequest->getText( 'sitename', 'Wikipedia' );
		
		// If we're not pulling the banner into another page, we'll need to add some extra HTML
		$standAlone = $wgRequest->getBool( 'standalone' );
		
		if ( $wgRequest->getText( 'banner' ) ) {
			$bannerName = $wgRequest->getText( 'banner' );
			$content = $this->getHtmlNotice( $bannerName, $standAlone );
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
	function getHtmlNotice( $bannerName, $standAlone = false ) {
		// Make sure the banner exists
		if ( SpecialNoticeTemplate::templateExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			$bannerHtml = '';
			if ( $standAlone ) {
				$bannerHtml .= <<<EOT
<html>
<head>
	<script type="text/javascript" src="http://bits.wikimedia.org/skins-1.5/common/jquery.min.js"></script>
</head>
<body>
EOT;
			}
			$bannerHtml .= preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
			if ( $standAlone ) {
				$bannerHtml .= <<<EOT
</body>
</html>
EOT;
			}
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

	function getNoticeField( $matches ) {
		$field = $matches[1];
		$message = "centralnotice-{$this->bannerName}-$field";
		$source = $this->getMessage( $message );
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
	
	private function getMessage( $msg ) {
		global $wgLang;
		
		// A god-damned dirty hack! :D
		$oldLang = $wgLang;

		$wgLang = Language::factory( $this->language ); // hack for {{int:...}}
		$out = wfMsgExt( $msg, array( 'language' => $this->language, 'parsemag' ) );

		// Restore global
		$wgLang = $oldLang;
		
		// Replace variables in banner with values
		$out = str_ireplace( '$amount', $this->formatNum( $this->getDonationAmount() ), $out );
		$out = str_ireplace( '$sitename', $this->siteName, $out );
		
		return $out;
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
			wfSuppressWarnings();
			$count = intval( file_get_contents( $wgNoticeCounterSource ) );
			wfRestoreWarnings();
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get( 'centralnotice:counter:fallback' ) );
				if ( !$count ) {
					// Return hard-coded amount if all else fails
					return 1100000; // Update as needed during fundraiser
				}
			}
			$wgMemc->set( 'centralnotice:counter', $count, 60 ); // Expire in 60 seconds
			$wgMemc->set( 'centralnotice:counter:fallback', $count ); // No expiration
		}
		return $count;
	}
	
}
