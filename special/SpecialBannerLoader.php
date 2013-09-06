<?php
/**
 * Renders banner contents as jsonp.
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	/** @var string Name of the chosen banner */
	public $bannerName;

	/** @var string Name of the campaign that the banner belongs to.*/
	public $campaignName;

	public $allocContext = null;

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
		} catch ( EmptyBannerException $e ) {
			echo "mw.centralNotice.insertBanner( false );";
		} catch ( MWException $e ) {
			wfDebugLog( 'CentralNotice', $e->getMessage() );
			echo "mw.centralNotice.insertBanner( false /* due to internal exception */ );";
		}
	}

	function getParams() {
		$request = $this->getRequest();

		$language = $this->getLanguage()->getCode();

		$project = $this->getSanitized( 'project', ApiCentralNoticeAllocations::PROJECT_FILTER );
		$country = $this->getSanitized( 'country', ApiCentralNoticeAllocations::LOCATION_FILTER );
		$anonymous = ( $this->getSanitized( 'anonymous', ApiCentralNoticeAllocations::ANONYMOUS_FILTER ) === 'true' );
		$bucket = intval( $this->getSanitized( 'bucket', ApiCentralNoticeAllocations::BUCKET_FILTER ) );
		$device = $this->getSanitized( 'device', ApiCentralNoticeAllocations::DEVICE_NAME_FILTER );

		$this->siteName = $request->getText( 'sitename' );

		$required_values = array(
			$project, $language, $country, $anonymous, $bucket, $device,
			$this->siteName,
		);
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}

		$this->allocContext = new AllocationContext(
			$country, $language, $project,
			$anonymous, $device, $bucket
		);

		$this->campaignName = $request->getText( 'campaign' );
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

		// If we have a logged in user; do not cache (default for special pages)
		// lest we capture a set-cookie header. Otherwise cache so we don't have
		// too big of a DDoS hole.
		if ( !$this->getUser()->isLoggedIn() ) {
			header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
		}
	}

	/**
	 * Generate the JS for the requested banner
	 * @param $bannerName string
	 * @return string of Javascript containing a call to insertBanner()
	 *   with JSON containing the banner content as the parameter
	 * @throw SpecialBannerLoaderException
	 */
	public function getJsNotice( $bannerName ) {
		$banner = Banner::fromName( $bannerName );
		if ( !$banner->exists() ) {
			throw new EmptyBannerException( $bannerName );
		}
		$bannerRenderer = new BannerRenderer( $this->getContext(), $banner, $this->campaignName, $this->allocContext );

		$bannerHtml = $bannerRenderer->toHtml();

		if ( !$bannerHtml ) {
			throw new EmptyBannerException( $bannerName );
		}

		// TODO: these are BannerRenderer duties:
		$settings = Banner::getBannerSettings( $bannerName, false );

		$bannerArray = array(
			'bannerName' => $bannerName,
			'bannerHtml' => $bannerHtml,
			'campaign' => $this->campaignName,
			'fundraising' => $settings['fundraising'],
			'autolink' => $settings['autolink'],
			'landingPages' => explode( ", ", $settings['landingpages'] ),
		);

		try {
			$campaignObj = new Campaign( $this->campaignName );
			$priority = $campaignObj->getPriority();
		} catch ( CampaignExistenceException $ex ) {
			$priority = 0;
		}
		$bannerArray['priority'] = $priority;

		$bannerJson = FormatJson::encode( $bannerArray );

		$preload = $bannerRenderer->getPreloadJs();
		if ( $preload ) {
			$preload = "mw.centralNotice.bannerData.preload = function() { {$preload} };";
		}

		$bannerJs = $preload . "mw.centralNotice.insertBanner( {$bannerJson} );";

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
		$this->message = get_called_class() . " while loading banner: '{$bannerName}'";
	}
}

class EmptyBannerException extends BannerLoaderException {
}

class MissingRequiredParamsException extends BannerLoaderException {
}
