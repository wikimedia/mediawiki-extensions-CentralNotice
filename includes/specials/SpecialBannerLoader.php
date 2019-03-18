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
	/** @var string|null Content of the banner to be previewed */
	public $bannerContent;
	/** @var string[] Message for substitution */
	public $bannerMessages;
	/** @var bool */
	protected $debug;

	public function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}

	public function execute( $par ) {
		$this->getOutput()->disable();

		try {
			$this->getParams();
			$out = $this->getJsNotice( $this->bannerName, $this->bannerContent );
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

	public function getParams() {
		$request = $this->getRequest();

		// FIXME: Don't allow a default language.
		$language = $this->getLanguage()->getCode();

		$this->campaignName = $request->getText( 'campaign' );
		$this->bannerName = $request->getText( 'banner' );
		$this->debug = $request->getFuzzyBool( 'debug' );

		// Respect the `bannercontent` and `bannermessages` parameters only if the viewer is a CN editor
		if ( $this->getUser()->isAllowed( 'centralnotice-admin' ) ) {
			$this->bannerContent = $request->getText( 'bannercontent', null );
			$this->bannerMessages = $request->getArray( 'bannermessages', null );
		}

		$required_values = [
			$this->campaignName, $this->bannerName, $language
		];
		foreach ( $required_values as $value ) {
			if ( is_null( $value ) ) {
				throw new MissingRequiredParamsException();
			}
		}
	}

	public function getSanitized( $param, $filter ) {
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
	private function sendHeaders( $cacheResponse = self::MAX_CACHE_NORMAL ) {
		global $wgNoticeBannerMaxAge, $wgNoticeBannerReducedMaxAge;

		header( "Content-type: text/javascript; charset=utf-8" );

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
	 * @param string|null $bannerContent
	 * @return string of JavaScript containing a call to insertBanner()
	 *   with JSON containing the banner content as the parameter
	 * @throws EmptyBannerException
	 * @throws StaleCampaignException
	 */
	public function getJsNotice( $bannerName, $bannerContent = null ) {
		// If this wasn't a test of a banner, check that this is from a campaign
		// that hasn't ended. We might get old campaigns due to forever-cached
		// JS somewhere. Note that we include some leeway and don't consider
		// archived or enabled status because the campaign might just have been
		// updated and there is a normal caching lag.

		$isPreview = !empty( $bannerContent ) && $this->getRequest()->wasPosted();

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
		if ( !$banner->exists() && !$isPreview ) {
			throw new EmptyBannerException( $bannerName );
		}

		if ( $isPreview ) {
			// Replace messages in preview content with values provided in params
			if ( $this->bannerMessages && count( $this->bannerMessages ) ) {
				$this->bannerContent = $this->replaceMessages(
					$this->bannerContent,
					$this->bannerMessages
				);
			}
		}

		$bannerRenderer = new BannerRenderer(
			$this->getContext(),
			$banner,
			$this->campaignName,
			$this->debug
		);
		$bannerHtml = $bannerRenderer->toHtml( $this->bannerContent );

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

		if ( $isPreview ) {
			$bannerJs = "{$preload}\n" .
						"mw.centralNotice.adminUi.bannerEditor.updateBannerPreview( {$bannerJson} );";
		} else {
			$bannerJs = "{$preload}\nmw.centralNotice.insertBanner( {$bannerJson} );";
		}

		return $bannerJs;
	}

	/**
	 * Replaces {{{x}}} with message strings,
	 * perhaps need to be reworked or replaced.
	 *
	 * @note the method is static to allow SpecialBannerLoader::getJsNotice() to call
	 * it during in-page previews, perhaps it would be better to organize things into a
	 * sepearte class or integrate into renderer/banner somehow
	 *
	 * @param string $text Base text
	 * @param string[] $messages Messages array
	 *
	 * @return string
	 */
	private function replaceMessages( $text, $messages ) {
		if ( count( $messages ) ) {
			foreach ( $messages as $message => $value ) {
				$text = str_replace(
					'{{{' . $message . '}}}',
					MessageCache::singleton()->transform( $value ),
					$text
				);
			}
		}
		return $text;
	}

}
