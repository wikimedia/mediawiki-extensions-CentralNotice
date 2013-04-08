<?php
/**
 * Renders banner contents as jsonp.
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	/** @var string Name of the choosen banner */
	public $bannerName;

	/** @var string Name of the campaign that the banner belongs to. Will be 'undefined' if not attatched. */
	public $campaign;

	public $project;
	public $country;
	public $language;
	public $anonymous;
	public $device;
	public $bucket;

	function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}

	function execute( $par ) {
		$this->sendHeaders();
		$this->getOutput()->disable();

		try {
			$this->getParams();

			echo $this->getJsNotice( $this->bannerName );
		} catch ( BannerLoaderException $e ) {
			wfDebugLog( 'CentralNotice', $e->getMessage() );
			echo "insertBanner( false );";
		}
	}

	function getParams() {
		$request = $this->getRequest();

		// FIXME: s/userlang/uselang/g
		// $this->language = $this->getLanguage()->getCode();
		if ( $lang = $request->getVal( 'userlang' ) ) {
			$this->language = $lang;
			$request->setVal( 'uselang', $this->language );
			$this->getContext()->setLanguage( $this->language );
		}

		$this->project = $this->getSanitized( 'project', ApiCentralNoticeAllocations::PROJECT_FILTER );
		$this->country = $this->getSanitized( 'country', ApiCentralNoticeAllocations::LOCATION_FILTER );
		$this->anonymous = ( $this->getSanitized( 'anonymous', ApiCentralNoticeAllocations::ANONYMOUS_FILTER ) === 'true' );
		$this->bucket = intval( $this->getSanitized( 'bucket', ApiCentralNoticeAllocations::BUCKET_FILTER ) );
		$this->device = $this->getSanitized( 'device', ApiCentralNoticeAllocations::DEVICE_NAME_FILTER );

		$this->siteName = $request->getText( 'sitename' );

		$required_values = array(
			$this->project, $this->country, $this->anonymous, $this->bucket,
			$this->siteName, $this->device
		);
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}

		$this->campaign = $request->getText( 'campaign' );
		$this->bannerName = $request->getText( 'banner' );
	}

	function getSanitized( $param, $filter ) {
		$matches = array();
		if ( preg_match( $filter, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		}
		return null;
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
	public function getJsNotice( $bannerName ) {
		if ( !Banner::bannerExists( $bannerName ) ) {
			throw new EmptyBannerException( $bannerName );
		}
		$banner = new Banner( $bannerName );
		$bannerRenderer = new BannerRenderer( $this->getContext(), $banner, $this->campaign );

		$bannerHtml = $bannerRenderer->toHtml();

		if ( !$bannerHtml ) {
			throw new EmptyBannerException( $bannerName );
		}

		// TODO: these are BannerRenderer duties:
		$settings = Banner::getBannerSettings( $bannerName, false );

		$bannerArray = array(
			'bannerName' => $bannerName,
			'bannerHtml' => $bannerHtml,
			'campaign' => $this->campaign,
			'fundraising' => $settings['fundraising'],
			'autolink' => $settings['autolink'],
			'landingPages' => explode( ", ", $settings['landingpages'] ),
		);

		$bannerJs = 'insertBanner('.FormatJson::encode( $bannerArray ).');';
		return $bannerJs;
	}
}

/**
 * @defgroup Exception Exception
 */

/**
 * These exceptions are thrown whenever an error occurs, which is fatal to
 * rendering the banner, but can be fairly expected.
 *
 * @ingroup Exception
 */
class BannerLoaderException extends MWException {
	function __construct( $bannerName = '(none provided)' ) {
		$this->message = get_class() . " while loading banner: '{$bannerName}'";
	}
}

class EmptyBannerException extends BannerLoaderException {
}

class MissingRequiredParamsException extends BannerLoaderException {
}
