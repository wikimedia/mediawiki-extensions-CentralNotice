<?php
/**
 * Renders banner contents as jsonp.
 */
class SpecialBannerLoader extends UnlistedSpecialPage {
	/**
	 * Seconds leeway for checking stale choice data. Should be the same
	 * as CAMPAIGN_STALENESS_LEEWAY in ext.centralNotice.display.chooser.js.
	 */
	private const CAMPAIGN_STALENESS_LEEWAY = 900;

	/* Possible values for $this->cacheResponse */
	private const MAX_CACHE_NORMAL = 0;
	private const MAX_CACHE_REDUCED = 1;

	/* Possible values for $this->requestType */
	private const USER_DISPLAY_REQUEST = 0;
	private const TESTING_SAVED_REQUEST = 1;
	private const PREVIEW_UNSAVED_REQUEST = 2;

	/** @var string Name of the chosen banner */
	private $bannerName;

	/** @var string|null Name of the campaign that the banner belongs to. */
	private $campaignName;

	/** @var string|null Content of the banner to be previewed */
	private $previewContent;

	/** @var string[]|null Unsaved messages for substitution in preview banner content */
	private $previewMessages;

	/** @var string[]|null */
	private $editToken;

	/** @var bool */
	private $debug;

	/** @var int Type of caching to set (see constants, above) */
	private $cacheResponse;

	/** @var int Request type (see constants, above) */
	private $requestType;

	public function __construct() {
		// Register special page
		parent::__construct( "BannerLoader" );
	}

	public function execute( $par ) {
		$this->getOutput()->disable();

		try {
			$this->getParamsAndSetState();
			$out = $this->getJsNotice();

		} catch ( EmptyBannerException $e ) {
			$out = "mw.centralNotice.handleBannerLoaderError( 'Empty banner' );";

			// Force reduced cache time
			$this->cacheResponse = self::MAX_CACHE_REDUCED;

		} catch ( Exception $e ) {
			if ( $e instanceof ILocalizedException ) {
				$msg = $e->getMessageObject()->escaped();
			} else {
				$msg = $e->getMessage();
			}

			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$msgParamStr = $msg ? Xml::encodeJsVar( $msg ) : '';

			// For preview requests, a different error callback is needed.
			if ( $this->requestType === self::PREVIEW_UNSAVED_REQUEST ) {
				$callback = 'mw.centralNotice.adminUi.bannerEditor.handleBannerLoaderError';
			} else {
				$callback = 'mw.centralNotice.handleBannerLoaderError';
			}

			$out = "$callback({$msgParamStr});";

			// Force reduced cache time
			$this->cacheResponse = self::MAX_CACHE_REDUCED;

			wfDebugLog( 'CentralNotice', $msg );
		}

		// We have to call this since we've disabled output.
		// TODO See if there's a better way to do this, maybe OutputPage::setCdnMaxage()?
		$this->sendHeaders();
		echo $out;
	}

	public function getParamsAndSetState() {
		$request = $this->getRequest();

		$this->campaignName = $request->getText( 'campaign' );
		$this->bannerName = $request->getText( 'banner' );
		$this->debug = $request->getFuzzyBool( 'debug' );
		$this->previewContent = $request->getText( 'previewcontent' );
		$this->previewMessages = $request->getArray( 'previewmessages' );
		$this->editToken = $request->getVal( 'token' );

		// All request types should have at least a non-empty banner name
		if ( !$this->bannerName ) {
			throw new MissingRequiredParamsException();
		}

		// Only render preview content and messages for users with CN admin rights, on
		// requests that were POSTed, with the correct edit token. This is to prevent
		// malicious use of the reflection of unsanitized parameters.
		if ( $this->getRequest()->wasPosted() && $this->previewContent ) {

			$this->requestType = self::PREVIEW_UNSAVED_REQUEST;

			// Check credentials
			if (
				!$this->getUser()->isAllowed( 'centralnotice-admin' ) ||
				!$this->editToken ||
				!$this->getUser()->matchEditToken( $this->editToken )
			) {
				throw new BannerPreviewPermissionsException( $this->bannerName );
			}

			// Note: We don't set $this->cacheResponse since there's no caching for
			// logged-in users anyway.

		// Distinguish a testing request for a saved banner by the absence of a campaign
		} elseif ( !$this->campaignName ) {
			$this->requestType = self::TESTING_SAVED_REQUEST;
			$this->cacheResponse = self::MAX_CACHE_REDUCED;

		// We have at least a campaign name and a banner name, which means this is a
		// normal request for a banner to display to a user.
		} else {
			$this->requestType = self::USER_DISPLAY_REQUEST;
			$this->cacheResponse = self::MAX_CACHE_NORMAL;
		}
	}

	/**
	 * Generate the HTTP response headers for the banner file, setting maxage cache time
	 * for front-send cache as appropriate.
	 *
	 * For anonymous users, set cache as per $this->cacheResponse, $wgNoticeBannerMaxAge
	 * and $wgNoticeBannerReducedMaxAge. Never cache for logged-in users.
	 *
	 * TODO Couldn't we cache for logged-in users? See T149873
	 */
	private function sendHeaders() {
		global $wgNoticeBannerMaxAge, $wgNoticeBannerReducedMaxAge;

		header( "Content-type: text/javascript; charset=utf-8" );

		if ( !$this->getUser()->isRegistered() ) {
			// Header tells front-end caches to retain the content for $sMaxAge seconds.
			$sMaxAge = ( $this->cacheResponse === self::MAX_CACHE_NORMAL ) ?
				$wgNoticeBannerMaxAge : $wgNoticeBannerReducedMaxAge;

		} else {
			$sMaxAge = 0;
		}

		header( "Cache-Control: public, s-maxage={$sMaxAge}, max-age=0" );
	}

	/**
	 * Generate the JS for the requested banner
	 * @return string of JavaScript containing a call to insertBanner()
	 *   with JSON containing the banner content as the parameter
	 * @throws EmptyBannerException
	 * @throws StaleCampaignException
	 */
	public function getJsNotice() {
		$banner = Banner::fromName( $this->bannerName );

		if ( $this->requestType === self::USER_DISPLAY_REQUEST ) {

			// The following will throw a CampaignExistenceException if there's
			// no such campaign.
			$campaign = new Campaign( $this->campaignName );

			// Check that this is from a campaign that hasn't ended. We might get old
			// campaigns due to forever-cached JS somewhere. Note that we include some
			// leeway and don't consider archived or enabled status because the
			// campaign might just have been updated and there is a normal caching lag for
			// the data about campaigns sent to browsers.
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

		if ( $this->requestType === self::PREVIEW_UNSAVED_REQUEST ) {

			$bannerRenderer = new BannerRenderer(
				$this->getContext(),
				$banner,
				$this->campaignName,
				$this->previewContent,
				$this->previewMessages,
				$this->debug
			);

			$jsCallbackFn = 'mw.centralNotice.adminUi.bannerEditor.updateBannerPreview';

		} else {
			if ( !$banner->exists() ) {
				throw new EmptyBannerException( $this->bannerName );
			}

			$bannerRenderer = new BannerRenderer(
				$this->getContext(),
				$banner,
				$this->campaignName,
				null,
				null,
				$this->debug
			);

			$jsCallbackFn = 'mw.centralNotice.insertBanner';
		}

		$bannerHtml = $bannerRenderer->toHtml();
		$bannerArray = [ 'bannerHtml' => $bannerHtml ];
		$bannerJson = FormatJson::encode( $bannerArray );
		$preload = $bannerRenderer->getPreloadJs();

		return "{$preload}\n{$jsCallbackFn}( {$bannerJson} );";
	}
}
