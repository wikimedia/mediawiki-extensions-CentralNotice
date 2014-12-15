<?php
/**
 * Renders banner contents as jsonp.
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	/** @var string Name of the chosen banner */
	public $bannerName;

	/** @var string Name of the campaign that the banner belongs to.*/
	public $campaignName;

	protected $debug;

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

		// FIXME: Don't allow a default language.
		$language = $this->getLanguage()->getCode();

		$this->campaignName = $request->getText( 'campaign' );
		$this->bannerName = $request->getText( 'banner' );
		$this->debug = $request->getFuzzyBool( 'debug' );

		$required_values = array(
			$this->campaignName, $this->bannerName, $language
		);
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}
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

		if ( !$this->getUser()->isLoggedIn() ) {
			// Public users get cached
			header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
		} else {
			// Private users do not (we have to emit this because we've disabled output)
			header( "Cache-Control: private, s-maxage=0, max-age=0" );
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
		if ( $bannerName === null || $bannerName === '' ) {
			throw new EmptyBannerException( $bannerName );
		}
		$banner = Banner::fromName( $bannerName );
		if ( !$banner->exists() ) {
			throw new EmptyBannerException( $bannerName );
		}
		$bannerRenderer = new BannerRenderer( $this->getContext(), $banner, $this->campaignName, $this->debug );

		$bannerHtml = $bannerRenderer->toHtml();

		if ( !$bannerHtml ) {
			throw new EmptyBannerException( $bannerName );
		}

		// TODO: these are BannerRenderer duties:
		$settings = Banner::getBannerSettings( $bannerName, false );

		$category = $bannerRenderer->substituteMagicWords( $settings['category'] );
		$category = Banner::sanitizeRenderedCategory( $category );

		$bannerArray = array(
			'bannerName' => $bannerName,
			'bannerHtml' => $bannerHtml,
			'campaign' => $this->campaignName,
			'category' => $category,
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
