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

	const MAX_CACHE_NORMAL = 0;
	const MAX_CACHE_REDUCED = 1;

	/** @var string Name of the chosen banner */
	public $bannerName;
	/** @var string Name of the campaign that the banner belongs to.*/
	public $campaignName;
	/** @var bool */
	protected $debug;

	function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}

	function execute( $par ) {
		$this->getOutput()->disable();

		try {
			$this->getParams();
			$out = $this->getJsNotice( $this->bannerName );
			$cacheResponse = self::MAX_CACHE_NORMAL;

		} catch ( EmptyBannerException $e ) {
			$out = "mw.centralNotice.handleBannerLoaderError( 'Empty banner' );";
			$cacheResponse = self::MAX_CACHE_REDUCED;

		} catch ( Exception $e ) {
			$msg = $e->getMessage();
			$msgParamStr = $msg ? Xml::encodeJsVar( $msg ) : '';
			$out = "mw.centralNotice.handleBannerLoaderError({$msgParamStr});";
			$cacheResponse = self::MAX_CACHE_REDUCED;

			wfDebugLog( 'CentralNotice', $msg );
		}

		$this->sendHeaders( $cacheResponse );
		echo $out;
	}

	function getParams() {
		$request = $this->getRequest();

		// FIXME: Don't allow a default language.
		$language = $this->getLanguage()->getCode();

		$this->campaignName = $request->getText( 'campaign' );
		$this->bannerName = $request->getText( 'banner' );
		$this->debug = $request->getFuzzyBool( 'debug' );

		$required_values = [
			$this->campaignName, $this->bannerName, $language
		];
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}
	}

	function getSanitized( $param, $filter ) {
		$matches = [];
		if ( preg_match( $filter, $this->getRequest()->getText( $param ), $matches ) ) {
			return $matches[0];
		}
		return null;
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 * @param int $cacheResponse If the response will be cached, use the normal
	 *   cache time ($wgNoticeBannerMaxAge) or the reduced time
	 *   ($wgNoticeBannerReducedMaxAge).
	 */
	function sendHeaders( $cacheResponse = self::MAX_CACHE_NORMAL ) {
		global $wgJsMimeType, $wgNoticeBannerMaxAge, $wgNoticeBannerReducedMaxAge;

		header( "Content-type: $wgJsMimeType; charset=utf-8" );

		if ( !$this->getUser()->isLoggedIn() ) {
			// This header tells our front-end caches to retain the content for
			// $sMaxAge seconds.
			$sMaxAge = ( $cacheResponse === self::MAX_CACHE_NORMAL ) ?
				$wgNoticeBannerMaxAge : $wgNoticeBannerReducedMaxAge;

			header( "Cache-Control: public, s-maxage={$sMaxAge}, max-age=0" );
		} else {
			// Private users do not get cached (we have to emit this because
			// we've disabled output)
			// TODO Couldn't we cache for theses users? See T149873
			header( "Cache-Control: private, s-maxage=0, max-age=0" );
		}
	}

	/**
	 * Generate the JS for the requested banner
	 * @param string $bannerName
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
		$bannerRenderer = new BannerRenderer(
			$this->getContext(), $banner, $this->campaignName, $this->debug );

		$bannerHtml = $bannerRenderer->toHtml();

		if ( !$bannerHtml ) {
			throw new EmptyBannerException( $bannerName );
		}

		// TODO: these are BannerRenderer duties:
		$settings = Banner::getBannerSettings( $bannerName, false );

		$category = $bannerRenderer->substituteMagicWords( $settings['category'] );
		$category = Banner::sanitizeRenderedCategory( $category );

		$bannerArray = [
			'bannerName' => $bannerName,
			'bannerHtml' => $bannerHtml,
			'campaign' => $this->campaignName,
			'category' => $category,
		];

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
