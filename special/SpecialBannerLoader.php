<?php
/**
 * Renders banner contents as jsonp.
 */
class SpecialBannerLoader extends UnlistedSpecialPage {

	/**
	 * Seconds leeway for checking stale choice data. Should be the same
	 * as mw.cnBannerControllerLib.CAMPAIGN_STALENESS_LEEWAY.
	 */
	const CAMPAIGN_STALENESS_LEEWAY = 900;

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
			$out = $this->getJsNotice( $this->bannerName );

		} catch ( EmptyBannerException $e ) {
			$out = "mw.centralNotice.handleBannerLoaderError( 'Empty banner' );";

		} catch ( Exception $e ) {
			$msg = $e->getMessage();
			$msgParamStr = $msg ? " '{$msg}' " : '';
			$out = "mw.centralNotice.handleBannerLoaderError({$msgParamStr});";

			wfDebugLog( 'CentralNotice', $msg );
		}

		echo $out;
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
	 * @return string of JavaScript containing a call to insertBanner()
	 *   with JSON containing the banner content as the parameter
	 * @throws EmptyBannerException
	 * @throws StaleCampaignException
	 */
	public function getJsNotice( $bannerName ) {

		// If this wasn't a test of a banner, check that this is from a campaign
		// that hasn't ended. We might get old campaigns due to forever-cached
		// JS somewhere. Note that we include some leeway and don't consider
		// archived or enabled status because the campaign might just have been
		// updated and there is a normal caching lag.

		// An empty campaign name is how bannerController indicates a test request.
		if ( $this->campaignName !== '' ) {

			// The following will throw a CampaignExistenceException if there's
			// no such campaign.
			$campaign = new Campaign( $this->campaignName );
			$endTimePlusLeeway = wfTimestamp(
				TS_UNIX,
				(int)$campaign->getEndTime()->getTimestamp() + self::CAMPAIGN_STALENESS_LEEWAY
			);
			$now = wfTimestamp();

			if ( $endTimePlusLeeway < $now ) {
				throw new StaleCampaignException(
					$this->bannerName, "Campaign: {$this->campaignName}" );
			}
		}

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
		);

		$bannerJson = FormatJson::encode( $bannerArray );

		$preload = $bannerRenderer->getPreloadJs();

		$bannerJs = "{$preload}\nmw.centralNotice.insertBanner( {$bannerJson} );";

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
class BannerLoaderException extends Exception {
	function __construct( $bannerName = '(none provided)', $extraMsg = null ) {

		$this->message = get_called_class() .
			" while loading banner: '{$bannerName}'";
		if ( $extraMsg ) {
			$this->message .= ". {$extraMsg}";
		}
	}
}

class EmptyBannerException extends BannerLoaderException {
}

class MissingRequiredParamsException extends BannerLoaderException {
}

class StaleCampaignException extends BannerLoaderException {
}
