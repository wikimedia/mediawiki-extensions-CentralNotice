<?php
/**
 * Renders banner (jsonp) contents.
 *
 * 
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
        $this->getParams();
	}

	function execute( $par ) {
		$this->getOutput()->disable();

        $this->getParams();

		$this->sendHeaders();

		$content = false;
		try {
			if ( $this->bannerName ) {
				$content = $this->getJsNotice( $this->bannerName );
			}
		} catch ( SpecialBannerLoaderException $e ) {
			wfDebugLog( 'CentralNotice', "Exception while loading banner: " . $e->getMessage() );
		}

		if ( $content ) {
			echo $content;
		} else {
			wfDebugLog( 'CentralNotice', "No content retrieved for banner: {$this->bannerName}" );
			echo "/* Banner could not be generated */";
		}
	}

	function getParams() {
		$request = $this->getRequest();

		$this->project = $this->getSanitized( 'project', 'wikipedia', ApiCentralNoticeAllocations::PROJECT_FILTER );
		$this->country = $this->getSanitized( 'country', 'XX', ApiCentralNoticeAllocations::LOCATION_FILTER );
		$this->language = $this->getSanitized( 'userlang', 'en', ApiCentralNoticeAllocations::LANG_FILTER );
		$this->anonymous = ( $this->getSanitized( 'anonymous', 'true', ApiCentralNoticeAllocations::ANONYMOUS_FILTER ) === 'true' );
		$this->bucket = intval( $this->getSanitized( 'bucket', '0', ApiCentralNoticeAllocations::BUCKET_FILTER ) );

		$this->siteName = $request->getText( 'sitename', 'Wikipedia' );
		$this->campaign = $request->getText( 'campaign', 'undefined' );

		$this->bannerName = $request->getText( 'banner' );
	}

	function getSanitized( $param, $default, $filter ) {
		$matches = array();
		if ( preg_match( $filter, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		}
		return $default;
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		// No client-side banner caching so we get all impressions
		header( "Cache-Control: public, s-maxage=$wgNoticeBannerMaxAge, max-age=0" );
	}

	/**
	 * Generate the JS for the requested banner
	 * @param $bannerName string
	 * @return string of Javascript containing a call to insertBanner()
	 *   with JSON containing the banner content as the parameter
	 * @throw SpecialBannerLoaderException
	 */
	function getJsNotice( $bannerName ) {
		// Make sure the banner exists
		$cndb = new CentralNoticeDB();
		if ( $cndb->bannerExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			$bannerHtml = '';
			$bannerHtml .= preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
			$bannerArray = array(
				'bannerName' => $bannerName,
				'bannerHtml' => $bannerHtml,
				'campaign' => $this->campaign,
				'fundraising' => (int)$this->getFundraising( $bannerName ),
				'autolink' => (int)$this->getAutolink( $bannerName ),
				'landingPages' => $this->getLandingPages( $bannerName )
			);
			$bannerJs = 'insertBanner('.FormatJson::encode( $bannerArray ).');';
			return $bannerJs;
		}
		return '';
	}

	/**
	 * Generate the HTML for the requested banner
	 * @throws SpecialBannerLoaderException
	 */
	function getHtmlNotice( $bannerName ) {
		// Make sure the banner exists
		$cndb = new CentralNoticeDB();
		if ( $cndb->bannerExists( $bannerName ) ) {
			$this->bannerName = $bannerName;
			$bannerHtml = '';
			$bannerHtml .= preg_replace_callback(
				'/{{{(.*?)}}}/',
				array( $this, 'getNoticeField' ),
				$this->getNoticeTemplate()
			);
			return $bannerHtml;
		}
		return '';
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
	 * If the field is 'amount', get the current fundraiser donation amount and pass it as a
	 * parameter to the message.
	 * @param $match array A message array with 2 members: raw match, short name of message
	 * @return string translated messsage string
	 * @throw SpecialBannerLoaderException
	 */
	function getNoticeField( $match ) {
		$field = $match[1];
		$params = array();

		// Handle "magic messages"
		switch ( $field ) {
			case 'amount': // total fundraising amount
				$params = array( $this->toMillions( $this->getDonationAmount() ) );
				break;
			case 'daily-amount': // daily fundraising amount
				$params = array( $this->toThousands( $this->getDailyDonationAmount() ) );
				break;
			case 'campaign': // campaign name
				return( $this->campaign );
				break;
			case 'banner': // banner name
				return( $this->bannerName );
				break;
		}

		$message = "centralnotice-{$this->bannerName}-$field";
		$source = $this->getMessage( $message, $params );
		return $source;
	}

	/**
	 * Convert number of dollars to millions of dollars
	 */
	private function toMillions( $num ) {
		$num = sprintf( "%.1f", $num / 1e6 );
		if ( substr( $num, - 2 ) == '.0' ) {
			$num = substr( $num, 0, - 2 );
		}
		$lang = Language::factory( $this->language );
		return $lang->formatNum( $num );
	}

	/**
	 * Convert number of dollars to thousands of dollars
	 */
	private function toThousands( $num ) {
		$num = sprintf( "%d", $num / 1000 );
		$lang = Language::factory( $this->language );
		return $lang->formatNum( $num );
	}

	/**
	 * Retrieve a translated message
	 * @param $msg string The full name of the message
	 * @param $params array
	 * @return string translated messsage string
	 */
	private function getMessage( $msg, $params = array() ) {
		global $wgSitename;

		// A god-damned dirty hack! :D
		$oldSitename = $wgSitename;
		$wgSitename = $this->siteName; // hack for {{SITENAME}}

		array_unshift( $params, $msg );
		$out = call_user_func_array( 'wfMessage', $params )->inLanguage( $this->language )->text();

		$wgSitename = $oldSitename;

		return $out;
	}

	/**
	 * Pull the current amount raised during a fundraiser
	 * @throws SpecialBannerLoaderException
	 */
	private function getDonationAmount() {
		global $wgNoticeCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( wfMemcKey( 'centralnotice', 'counter' ) ) );
		if ( !$count ) {
			// Pull from dynamic counter
			$counter_value = Http::get( $wgNoticeCounterSource );
			if( !$counter_value ) {
				throw new RemoteServerProblemException();
			}
			$count = intval( $counter_value );
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get(
					wfMemcKey( 'centralnotice', 'counter', 'fallback' ) ) );
				if ( !$count ) {
					throw new DonationAmountUnknownException();
				}
			}
			// Expire in 60 seconds
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter' ), $count, 60 );
			// No expiration
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter', 'fallback' ), $count );
		}
		return $count;
	}

	/**
	 * Pull the amount raised so far today during a fundraiser
	 * @throws SpecialBannerLoaderException
	 */
	private function getDailyDonationAmount() {
		global $wgNoticeDailyCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( wfMemcKey( 'centralnotice', 'dailycounter' ) ) );
		if ( !$count ) {
			// Pull from dynamic counter
			$counter_value = Http::get( $wgNoticeDailyCounterSource );
			if( !$counter_value ) {
				throw new RemoteServerProblemException();
			}
			$count = intval( $counter_value );
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get(
					wfMemcKey( 'centralnotice', 'dailycounter', 'fallback' ) ) );
				if ( !$count ) {
					throw new DonationAmountUnknownException();
				}
			}
			// Expire in 60 seconds
			$wgMemc->set( wfMemcKey( 'centralnotice', 'dailycounter' ), $count, 60 );
			// No expiration
			$wgMemc->set( wfMemcKey( 'centralnotice', 'dailycounter', 'fallback' ), $count );
		}
		return $count;
	}

	function getFundraising( $bannerName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$eBannerName = htmlspecialchars( $bannerName );
		$row = $dbr->selectRow( 'cn_templates', 'tmp_fundraising', array( 'tmp_name' => $eBannerName ) );
		return $row->tmp_fundraising;
	}

	function getAutolink( $bannerName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$eBannerName = htmlspecialchars( $bannerName );
		$row = $dbr->selectRow( 'cn_templates', 'tmp_autolink', array( 'tmp_name' => $eBannerName ) );
		return $row->tmp_autolink;
	}

	function getLandingPages( $bannerName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$eBannerName = htmlspecialchars( $bannerName );
		$row = $dbr->selectRow( 'cn_templates', 'tmp_landing_pages', array( 'tmp_name' => $eBannerName ) );
		return $row->tmp_landing_pages;
	}
}
/**
 * @defgroup Exception Exception
 */

/**
 * SpecialBannerLoaderException exception
 *
 * This exception is being thrown whenever
 * some fatal error occurs that may affect
 * how the banner is presented.
 *
 * @ingroup Exception
 */

class SpecialBannerLoaderException extends Exception {
}

class RemoteServerProblemException extends SpecialBannerLoaderException {
}

class DonationAmountUnknownException extends SpecialBannerLoaderException {
}
