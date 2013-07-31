<?php
/**
 * CentralNotice extension
 * For more info see https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * This file loads everything needed for the CentralNotice extension to function.
 *
 * @file
 * @ingroup Extensions
 * @license GNU General Public Licence 2.0 or later
 */

$wgExtensionCredits[ 'other' ][] = array(
	'path'           => __FILE__,
	'name'           => 'CentralNotice',
	'author'         => array(
		'Brion Vibber',
		'Tomasz Finc',
		'Trevor Parscal',
		'Ryan Kaldari',
		'Matthew Walker',
		'Adam Roses Wight',
	),
	'version'        => '2.3',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:CentralNotice',
	'descriptionmsg' => 'centralnotice-desc',
	'license-name' => 'GPLv2',
);


/* Setup */

$dir = __DIR__;

// Register message files
$wgExtensionMessagesFiles[ 'CentralNotice' ] = $dir . '/CentralNotice.i18n.php';
$wgExtensionMessagesFiles[ 'CentralNoticeAliases' ] = $dir . '/CentralNotice.alias.php';

// Register user rights
$wgAvailableRights[] = 'centralnotice-admin';
$wgGroupPermissions[ 'sysop' ][ 'centralnotice-admin' ] = true; // Only sysops can make change

// Functions to be called after MediaWiki initialization is complete
$wgExtensionFunctions[] = 'efCentralNoticeSetup';

// Register ResourceLoader modules
$wgResourceModules[ 'jquery.ui.multiselect' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies'  => array(
		'jquery.ui.core',
		'jquery.ui.sortable',
		'jquery.ui.draggable',
		'jquery.ui.droppable',
		'mediawiki.jqueryMsg'
	),
	'scripts'       => 'jquery.ui.multiselect/ui.multiselect.js',
	'styles'        => 'jquery.ui.multiselect/ui.multiselect.css',
);
$wgResourceModules[ 'ext.centralNotice.adminUi' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies' => array(
		'jquery.ui.datepicker',
		'jquery.ui.multiselect'
	),
	'scripts'       => 'ext.centralNotice.adminUi/centralnotice.js',
	'styles'        => array(
		'ext.centralNotice.adminUi/centralnotice.css',
		'ext.centralNotice.adminUi/adminui.common.css'
	),
	'messages'      => array(
		'centralnotice-documentwrite-error',
		'centralnotice-close-title',
		'centralnotice-select-all',
		'centralnotice-remove-all',
		'centralnotice-items-selected'
	)
);
$wgResourceModules[ 'ext.centralNotice.adminUi.bannerManager' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog'
	),
	'scripts'       => 'ext.centralNotice.adminUi.bannerManager/bannermanager.js',
	'styles'        => 'ext.centralNotice.adminUi.bannerManager/bannermanager.css',
	'messages'      => array(
		'centralnotice-add-notice-button',
		'centralnotice-add-notice-cancel-button',
		'centralnotice-archive-banner',
		'centralnotice-archive-banner-title',
		'centralnotice-archive-banner-confirm',
		'centralnotice-archive-banner-cancel',
		'centralnotice-delete-banner',
		'centralnotice-delete-banner-title',
		'centralnotice-delete-banner-confirm',
		'centralnotice-delete-banner-cancel',
	)
);
$wgResourceModules[ 'ext.centralNotice.adminUi.bannerEditor' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog'
	),
	'scripts'       => 'ext.centralNotice.adminUi.bannerEditor/bannereditor.js',
	'styles'        => 'ext.centralNotice.adminUi.bannerEditor/bannereditor.css',
	'messages'      => array(
		'centralnotice-clone',
		'centralnotice-clone-notice',
		'centralnotice-clone-cancel',
		'centralnotice-archive-banner',
		'centralnotice-archive-banner-title',
		'centralnotice-archive-banner-confirm',
		'centralnotice-archive-banner-cancel',
		'centralnotice-delete-banner',
		'centralnotice-delete-banner-title',
		'centralnotice-delete-banner-confirm',
		'centralnotice-delete-banner-cancel',
	)
);
$wgResourceModules[ 'ext.centralNotice.adminUi.bannerPreview' ] = array(
	'localBasePath' => $dir . '/modules/ext.centralNotice.adminUi.bannerPreview',
	'remoteExtPath' => 'CentralNotice/modules/ext.centralNotice.adminUi.bannerPreview',
	'styles'        => 'bannerPreview.css',
	'scripts'       => 'bannerPreview.js',
	'dependencies'  => 'ext.centralNotice.bannerController',
);
$wgResourceModules[ 'ext.centralNotice.bannerStats' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'scripts'       => 'ext.centralNotice.bannerStats/bannerStats.js',
);
$wgResourceModules[ 'ext.centralNotice.bannerController' ] = array(
	'localBasePath' => $dir . '/modules/ext.centralNotice.bannerController',
	'remoteExtPath' => 'CentralNotice/modules/ext.centralNotice.bannerController',
	'styles'        => 'bannerController.css',
	'scripts'       => 'bannerController.js',
	'position'      => 'top',
	'dependencies'  => array(
		'jquery.cookie',
	),
);

$wgResourceModules[ 'ext.centralNotice.bannerController.mobiledevice' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'position'      => 'top',
	'targets'       => 'mobile',
	'scripts'       => array( 'ext.centralNotice.bannerController/mobile/device.js' )
);
$wgResourceModules[ 'ext.centralNotice.bannerController.mobile' ] = array_merge_recursive(
	array(
		'targets' => 'mobile',
		'dependencies' => 'ext.centralNotice.bannerController.mobiledevice'
	),
	$wgResourceModules[ 'ext.centralNotice.bannerController' ]
);

function efEnableMobileModules( $out, $mode ) {
	$names = array(
		'ext.centralNotice.bannerController.mobiledevice',
		'ext.centralNotice.bannerController.mobile',
	);
	$out->addModules( $names );
	return true;
}

/* Configuration */

// $wgNoticeLang and $wgNoticeProject are used for targeting campaigns to specific wikis. These
// should be overridden on each wiki with the appropriate values.
// Actual user language (wgUserLanguage) is used for banner localisation.
$wgNoticeLang = $wgLanguageCode;
$wgNoticeProject = 'wikipedia';

// List of available projects
$wgNoticeProjects = array(
	'wikipedia',
	'wiktionary',
	'wikiquote',
	'wikibooks',
	'wikidata',
	'wikinews',
	'wikisource',
	'wikiversity',
	'wikivoyage',
	'wikimedia',
	'commons',
	'meta',
	'wikispecies',
	'test',
);

// Enable the campaign hosting infrastructure on this wiki...
// Set to false for wikis that only use a sister site for the control.
$wgNoticeInfrastructure = true;

// The name of the database which hosts the centralized campaign data
$wgCentralDBname = false;

// URL where BannerRandom is hosted, where FALSE will default to the
// Special:BannerRandom on the machine serving ResourceLoader requests (meta
// in the case of WMF).  To use our reverse proxy, for example, set this
// variable to 'http://banners.wikimedia.org/banner_load'.
$wgCentralBannerDispatcher = false;

// URL which is hit after a banner is loaded, for compatibility with analytics.
$wgCentralBannerRecorder = false;

// Protocol and host name of the wiki that hosts the CentralNotice infrastructure,
// for example '//meta.wikimedia.org'. This is used for DNS prefetching.
// NOTE: this should be the same host as wgCentralBannerDispatcher, above,
// when on a different subdomain than the wiki.
$wgCentralHost = false;

// The script path on the wiki that hosts the CentralNotice infrastructure
// For example 'http://meta.wikimedia.org/w/index.php'
$wgCentralPagePath = false;

// Enable the loader itself
// Allows to control the loader visibility, without destroying infrastructure
// for cached content
$wgCentralNoticeLoader = true;

// Flag for turning on fundraising specific features
$wgNoticeEnableFundraising = true;

// Base URL for default fundraiser landing page (without query string)
$wgNoticeFundraisingUrl = 'https://donate.wikimedia.org/wiki/Special:LandingCheck';

// Source for live counter information
$wgNoticeCounterSource = 'http://wikimediafoundation.org/wiki/Special:ContributionTotal?action=raw';
$wgNoticeDailyCounterSource = 'http://wikimediafoundation.org/wiki/Special:DailyTotal?action=raw';

// URL for a banner close button
$wgNoticeCloseButton = '//upload.wikimedia.org/wikipedia/foundation/2/20/CloseWindow19x19.png';

// Domain to set global cookies for.
// Example: '.wikipedia.org'
$wgNoticeCookieDomain = '';

// The amount of time banners will be hidden by the close box.
// Defaults to two weeks.
$wgNoticeCookieShortExpiry = 1209600;

// Amount of time the banners will hide after a successful donation.
// Defaults to one year.
$wgNoticeCookieLongExpiry = 31536000;

// Server-side banner cache timeout in seconds
$wgNoticeBannerMaxAge = 600;

// Whether to use the Translation extension for banner message translation
$wgNoticeUseTranslateExtension = false;

// Whether to disable variant languages and use an automatically converted version of banners
// fetched from their parent language (zh for zh-cn, for example) instead.
$wgNoticeUseLanguageConversion = false;

// When using the group review feature of translate; this will be the namespace ID for the banner
// staging area -- ie: banners here are world editable and will not be moved to the MW namespace
// until they are in @ref $wgNoticeTranslateDeployStates
$wgNoticeBannerNSID = 866;

define( 'NS_CN_BANNER', $wgNoticeBannerNSID );
define( 'NS_CN_BANNER_TALK', $wgNoticeBannerNSID + 1 );

// When a banner is protected; what group is assigned. This is used for banners in the CNBanner
// namespace to protect origin messages.
$wgNoticeProtectGroup = 'sysop';

// When using the group review feature of the translate extension, only message groups with these
// group review states will be deployed -- e.g. copy from the CNBanners namespace to the MW
// namespace. This requires that anyone who can assign this state much have site-edit permissions
$wgNoticeTranslateDeployStates = array(
	'published',
);

// These are countries that MaxMind will give out when information is a bit fuzzy. However,
// fundraising code doesn't like non ISO countries so we map them to the fictional point case 'XX'
$wgNoticeXXCountries = array( 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' );

// String of space delimited domains that will be able to accept data via JS message event when
// calling Special:CNReporter
$wgNoticeReporterDomains = 'https://donate.wikimedia.org';

// Number of buckets that are provided to choose from -- this must be a power of two! It must not
// also be greater than 9 unless a schema change is performed. Right now this column is tinyint(1)
$wgNoticeNumberOfBuckets = 4;

// We can tell the controller to only assign buckets from 0 .. to this variable. This allows
// us to serve banners only to people who meet certain criteria (ie: banners place people in
// certain buckets after events happen.)
$wgNoticeNumberOfControllerBuckets = 2;

// How long, via the jQuery cookie expiry string, will the bucket last
$wgNoticeBucketExpiry = 7;

// When displaying a long list, display the complement "all except ~LIST" past a threshold,
// given as a proportion of the "all" list length.
$wgNoticeListComplementThreshold = 0.75;

/** @var $wgNoticeTabifyPages array Declare all pages that should be tabified as CN pages */
$wgNoticeTabifyPages = array(
	/* Left side 'namespace' tabs */
	'CentralNotice' => array(
		'type' => 'namespaces',
		'message' => 'centralnotice-notices',
	),
	'CentralNoticeBanners' => array(
		'type' => 'namespaces',
		'message' => 'centralnotice-templates',
	),

	/* Right side 'view' tabs */
	'BannerAllocation' => array(
		'type' => 'views',
		'message' => 'centralnotice-allocation',
	),
	'GlobalAllocation' => array(
		'type' => 'views',
		'message' => 'centralnotice-global-allocation',
	),
	'CentralNoticeLogs' => array(
		'type' => 'views',
		'message' => 'centralnotice-logs',
	),
);

// Available banner mixins, usually provided by separate extensions.
// See http://www.mediawiki.org/wiki/Extension:CentralNotice/Banner_mixins
$wgNoticeMixins = array(
	'BannerDiet' => array(
		'localBasePath' => __DIR__ . "/mixins/BannerDiet",

		'preloadJs' => "BannerDiet.js",
	),
);

/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages,
		$wgCentralNoticeLoader, $wgSpecialPageGroups, $wgCentralPagePath, $wgScript,
		$wgNoticeUseTranslateExtension, $wgAPIModules, $wgAPIListModules;

	// If $wgCentralPagePath hasn't been set, set it to the local script path.
	// We do this here since $wgScript isn't set until after LocalSettings.php loads.
	if ( $wgCentralPagePath === false ) {
		$wgCentralPagePath = $wgScript;
	}

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$apiDir = $dir . 'api/';
	$includeDir = $dir . 'includes/';
	$htmlFormDir = $includeDir . '/HtmlFormElements/';

	// Register files
	$wgAutoloadClasses[ 'CentralNotice' ] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses[ 'SpecialBannerLoader' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerPreview' ] = $specialDir . 'SpecialBannerPreview.php';
	$wgAutoloadClasses[ 'SpecialBannerRandom' ] = $specialDir . 'SpecialBannerRandom.php';
	$wgAutoloadClasses[ 'SpecialRecordImpression' ] = $specialDir . 'SpecialRecordImpression.php';
	$wgAutoloadClasses[ 'SpecialHideBanners' ] = $specialDir . 'SpecialHideBanners.php';
	$wgAutoloadClasses[ 'SpecialCNReporter' ] = $specialDir . 'SpecialCNReporter.php';

	$wgAutoloadClasses[ 'BannerLoaderException' ] = $specialDir . 'SpecialBannerLoader.php';

	$wgAutoloadClasses[ 'Banner' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerMessage' ] = $includeDir . 'BannerMessage.php';
	$wgAutoloadClasses[ 'BannerChooser' ] = $includeDir . 'BannerChooser.php';
	$wgAutoloadClasses[ 'BannerRenderer' ] = $includeDir . 'BannerRenderer.php';
	$wgAutoloadClasses[ 'Campaign' ] = $includeDir . 'Campaign.php';
	$wgAutoloadClasses[ 'CampaignLog' ] = $includeDir . 'CampaignLog.php';
	$wgAutoloadClasses[ 'GeoTarget' ] = $includeDir . 'GeoTarget.php';
	$wgAutoloadClasses[ 'IBannerMixin' ] = $includeDir . 'IBannerMixin.php';
	$wgAutoloadClasses[ 'AllocationContext' ] = $includeDir . 'AllocationContext.php';
	$wgAutoloadClasses[ 'MixinController' ] = $includeDir . 'MixinController.php';

	$wgAutoloadClasses[ 'HTMLCentralNoticeBanner' ] = $htmlFormDir . 'HTMLCentralNoticeBanner.php';
	$wgAutoloadClasses[ 'HTMLCentralNoticeBannerMessage' ] = $htmlFormDir . 'HTMLCentralNoticeBannerMessage.php';

	$wgAutoloadClasses[ 'CNDatabasePatcher' ] = $dir . 'patches/CNDatabasePatcher.php';

	$wgAutoloadClasses[ 'ApiCentralNoticeAllocations' ] = $apiDir . 'ApiCentralNoticeAllocations.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeQueryCampaign' ] = $apiDir . 'ApiCentralNoticeQueryCampaign.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeLogs' ] = $apiDir . 'ApiCentralNoticeLogs.php';

	$wgAutoloadClasses[ 'CNDatabase' ] = $includeDir . 'CNDatabase.php';
	$wgAPIModules[ 'centralnoticeallocations' ] = 'ApiCentralNoticeAllocations';
	$wgAPIModules[ 'centralnoticequerycampaign' ] = 'ApiCentralNoticeQueryCampaign';
	$wgAPIListModules[ 'centralnoticelogs' ] = 'ApiCentralNoticeLogs';

	// Register hooks
	$wgHooks[ 'UnitTestsList' ][ ] = 'efCentralNoticeUnitTests';

	// If CentralNotice banners should be shown on this wiki, load the components we need for
	// showing banners. For discussion of banner loading strategies, see
	// http://wikitech.wikimedia.org/view/CentralNotice/Optimizing_banner_loading
	if ( $wgCentralNoticeLoader ) {
		$wgHooks[ 'MakeGlobalVariablesScript' ][ ] = 'efCentralNoticeDefaults';
		$wgHooks[ 'BeforePageDisplay' ][ ] = 'efCentralNoticeLoader';
		$wgHooks[ 'SiteNoticeAfter' ][ ] = 'efCentralNoticeDisplay';
		$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'efResourceLoaderGetConfigVars';
	}
	// Register mobile modules
	$wgHooks['EnableMobileModules'][] = 'efEnableMobileModules';

	// Register special pages
	$wgSpecialPages[ 'BannerLoader' ] = 'SpecialBannerLoader';
	$wgSpecialPages[ 'BannerPreview' ] = 'SpecialBannerPreview';
	$wgSpecialPages[ 'BannerRandom' ] = 'SpecialBannerRandom';
	$wgSpecialPages[ 'RecordImpression' ] = 'SpecialRecordImpression';
	$wgSpecialPages[ 'HideBanners' ] = 'SpecialHideBanners';
	$wgSpecialPages[ 'CNReporter' ] = 'SpecialCNReporter';

	// If this is the wiki that hosts the management interface, load further components
	if ( $wgNoticeInfrastructure ) {
		$wgAutoloadClasses[ 'TemplatePager' ] = $dir . 'TemplatePager.php';
		$wgAutoloadClasses[ 'CentralNoticePager' ] = $dir . 'CentralNoticePager.php';
		$wgAutoloadClasses[ 'CentralNoticeCampaignLogPager' ] = $dir . 'CentralNoticeCampaignLogPager.php';
		$wgAutoloadClasses[ 'CentralNoticeBannerLogPager' ] = $dir . 'CentralNoticeBannerLogPager.php';
		$wgAutoloadClasses[ 'CentralNoticePageLogPager' ] = $dir . 'CentralNoticePageLogPager.php';
		$wgAutoloadClasses[ 'SpecialNoticeTemplate' ] = $specialDir . 'SpecialNoticeTemplate.php';
		$wgAutoloadClasses[ 'SpecialGlobalAllocation' ] = $specialDir . 'SpecialGlobalAllocation.php';
		$wgAutoloadClasses[ 'SpecialBannerAllocation' ] = $specialDir . 'SpecialBannerAllocation.php';
		$wgAutoloadClasses[ 'SpecialCentralNoticeLogs' ] = $specialDir . 'SpecialCentralNoticeLogs.php';
		$wgAutoloadClasses[ 'SpecialCentralNoticeBanners' ] = $specialDir . 'SpecialCentralNoticeBanners.php';
		$wgAutoloadClasses[ 'CNBannerPager' ] = $includeDir . 'CNBannerPager.php';
		$wgAutoloadClasses[ 'CNDeviceTarget' ] = $includeDir . 'CNDeviceTarget.php';

		if ( $wgNoticeUseTranslateExtension ) {
			$wgAutoloadClasses[ 'BannerMessageGroup' ] = $includeDir . 'BannerMessageGroup.php';
			$wgHooks[ 'TranslatePostInitGroups' ][ ] = 'BannerMessageGroup::registerGroupHook';
			$wgHooks[ 'TranslateEventMessageGroupStateChange' ][] = 'BannerMessageGroup::updateBannerGroupStateHook';
		}

		$wgSpecialPages[ 'CentralNotice' ] = 'CentralNotice';
		$wgSpecialPageGroups[ 'CentralNotice' ] = 'wiki'; // Wiki data and tools
		$wgSpecialPages[ 'NoticeTemplate' ] = 'SpecialNoticeTemplate';
		$wgSpecialPages[ 'GlobalAllocation' ] = 'SpecialGlobalAllocation';
		$wgSpecialPages[ 'BannerAllocation' ] = 'SpecialBannerAllocation';
		$wgSpecialPages[ 'CentralNoticeLogs' ] = 'SpecialCentralNoticeLogs';
		$wgSpecialPages[ 'CentralNoticeBanners'] = 'SpecialCentralNoticeBanners';
	}
}

/**
 * CanonicalNamespaces hook; adds the CentralNotice namespaces if this is an infrastructure
 * wiki, and if CentralNotice is configured to use the Translate extension.
 *
 * We do this here because there are initialization problems wrt Translate and MW core if
 * the language object is initialized before all namespaces are registered -- which would
 * be the case if we just used the wgExtensionFunctions hook system.
 *
 * @param array $namespaces Modifiable list of namespaces -- similar to $wgExtraNamespaces
 *
 * @return bool True if the hook completed successfully.
 */
$wgHooks[ 'CanonicalNamespaces' ][ ] = function( &$namespaces ) {
	global $wgExtraNamespaces, $wgNamespacesWithSubpages, $wgTranslateMessageNamespaces;
	global $wgNoticeUseTranslateExtension, $wgNoticeInfrastructure;

	if ( $wgNoticeInfrastructure && $wgNoticeUseTranslateExtension ) {
		$wgExtraNamespaces[NS_CN_BANNER] = 'CNBanner';
		$wgTranslateMessageNamespaces[] = NS_CN_BANNER;

		$wgExtraNamespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
		$wgNamespacesWithSubpages[NS_CN_BANNER_TALK] = true;

		$namespaces[NS_CN_BANNER] = 'CNBanner';
		$namespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
	}

	return true;
};

/**
 * BeforePageDisplay hook handler
 * This function adds the banner controller and geoIP lookup to the page
 *
 * @param $out  OutputPage
 * @param $skin Skin
 * @return bool
 */
function efCentralNoticeLoader( $out, $skin ) {
	global $wgCentralHost;
	// Insert the geoIP lookup
	// TODO: Make this url configurable
	$out->addHeadItem( 'geoip', '<script src="//bits.wikimedia.org/geoiplookup"></script>' );
	// Insert DNS prefetch for banner loading
	if ( $wgCentralHost ) {
		$out->addHeadItem( 'dns-prefetch', '<link rel="dns-prefetch" href="' . htmlspecialchars( $wgCentralHost ) . '" />' );
	}
	// Insert the banner controller
	$out->addModules( 'ext.centralNotice.bannerController' );
	return true;
}

/**
 * MakeGlobalVariablesScript hook handler
 * This function sets the psuedo-global Javascript variables that are used by CentralNotice
 *
 * @param $vars array
 * @return bool
 */
function efCentralNoticeDefaults( &$vars ) {
	// Using global $wgUser for compatibility with 1.18
	global $wgNoticeProject, $wgUser, $wgMemc;

	// Initialize global Javascript variables. We initialize Geo with empty values so if the geo
	// IP lookup fails we don't have any surprises.
	$geo = array( 'city' => '', 'country' => '' );
	$vars[ 'Geo' ] = $geo; // change this to wgGeo if Ops updates the variable name on their end
	$vars[ 'wgNoticeProject' ] = $wgNoticeProject;

	// Output the user's registration date, total edit count, and past year's edit count.
	// This is useful for banners that need to be targeted to specific types of users.
	// Only do this for logged-in users, keeping anonymous user output equal (for Squid-cache).
	if ( $wgUser->isLoggedIn() ) {

		$cacheKey = wfMemcKey( 'CentralNotice', 'UserData', $wgUser->getId() );
		$userData = $wgMemc->get( $cacheKey );

		// Cached?
		if ( !$userData ) {
			// Exclude bots
			if ( $wgUser->isAllowed( 'bot' ) ) {
				$userData = false;
			} else {
				$userData = array();

				// Add the user's registration date (MediaWiki timestamp)
				$registrationDate = $wgUser->getRegistration() ? $wgUser->getRegistration() : 0;
				$userData[ 'registration' ] = $registrationDate;

				// Make sure UserDailyContribs extension is installed.
				if ( function_exists( 'getUserEditCountSince' ) ) {

					// Add the user's total edit count
					if ( $wgUser->getEditCount() == null ) {
						$userData[ 'editcount' ] = 0;
					} else {
						$userData[ 'editcount' ] = intval( $wgUser->getEditCount() );
					}

					// Add the user's edit count for the past year
					$userData[ 'pastyearseditcount' ] = getUserEditCountSince(
						time() - ( 365 * 24 * 3600 ), // from a year ago
						$wgUser,
						time() // until now
					);
				}
			}

			// Cache the data for 7 days
			$wgMemc->set( $cacheKey, $userData, 7 * 86400 );
		}

		// Set the variable that will be output to the page
		$vars[ 'wgNoticeUserData' ] = $userData;

	}

	return true;
}

/**
 * SiteNoticeAfter hook handler
 * This function outputs the siteNotice div that the banners are loaded into.
 *
 * @param $notice string
 * @return bool
 */
function efCentralNoticeDisplay( &$notice ) {
	// Ensure that the div including #siteNotice is actually included!
	$notice = "<!-- CentralNotice -->$notice";
	return true;
}

/**
 * ResourceLoaderGetConfigVars hook handler
 * Send php config vars to js via ResourceLoader
 *
 * @param &$vars: variables to be added to the output of the startup module
 * @return bool
 */
function efResourceLoaderGetConfigVars( &$vars ) {
	global $wgNoticeFundraisingUrl, $wgCentralPagePath, $wgContLang, $wgNoticeXXCountries,
		$wgNoticeInfrastructure, $wgNoticeCloseButton, $wgCentralBannerDispatcher,
		$wgCentralBannerRecorder, $wgNoticeNumberOfBuckets, $wgNoticeBucketExpiry,
		$wgNoticeNumberOfControllerBuckets, $wgNoticeCookieShortExpiry, $wgScript;

	// Making these calls too soon will causes issues with the namespace localisation cache. This seems
	// to be just right. We require them at all because MW will 302 page requests made to non localised
	// namespaces which results in wasteful extra calls.
	if ( !$wgCentralBannerDispatcher ) {
		$wgCentralBannerDispatcher = "{$wgScript}/{$wgContLang->specialPage( 'BannerRandom' )}";
	}
	if ( !$wgCentralBannerRecorder ) {
		$wgCentralBannerRecorder = "{$wgScript}/{$wgContLang->specialPage( 'RecordImpression' )}";
	}

	$vars[ 'wgNoticeFundraisingUrl' ] = $wgNoticeFundraisingUrl;
	$vars[ 'wgCentralPagePath' ] = $wgCentralPagePath;
	$vars[ 'wgCentralBannerDispatcher' ] = $wgCentralBannerDispatcher;
	$vars[ 'wgCentralBannerRecorder' ] = $wgCentralBannerRecorder;
	$vars[ 'wgNoticeXXCountries' ] = $wgNoticeXXCountries;
	$vars[ 'wgNoticeNumberOfBuckets' ] = $wgNoticeNumberOfBuckets;
	$vars[ 'wgNoticeBucketExpiry' ] = $wgNoticeBucketExpiry;
	$vars[ 'wgNoticeNumberOfControllerBuckets' ] = $wgNoticeNumberOfControllerBuckets;
	$vars[ 'wgNoticeCookieShortExpiry' ] = $wgNoticeCookieShortExpiry;

	if ( $wgNoticeInfrastructure ) {
		$vars[ 'wgNoticeCloseButton' ] = $wgNoticeCloseButton;
	}
	return true;
}

/**
 * UnitTestsList hook handler
 *
 * @param $files array
 * @return bool
 */
function efCentralNoticeUnitTests( &$files ) {
	$files[ ] = __DIR__ . '/tests/ApiAllocationsTest.php';
	$files[ ] = __DIR__ . '/tests/CentralNoticeTest.php';
	return true;
}

$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'CNDatabasePatcher::applyUpdates';
$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = array( 'CentralNotice::addNavigationTabs' );
