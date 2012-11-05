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
		'Adam Wight',
	),
	'version'        => '2.2',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:CentralNotice',
	'descriptionmsg' => 'centralnotice-desc',
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
$wgResourceModules[ 'ext.centralNotice.interface' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies' => array(
		'jquery.ui.datepicker',
	),
	'scripts'       => 'ext.centralNotice.interface/centralnotice.js',
	'styles'        => 'ext.centralNotice.interface/centralnotice.css',
	'messages'      => array(
		'centralnotice-documentwrite-error',
		'centralnotice-close-title',
	)
);
$wgResourceModules[ 'ext.centralNotice.bannerStats' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'scripts'       => 'ext.centralNotice.bannerStats/bannerStats.js',
);
$wgResourceModules[ 'ext.centralNotice.bannerController' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'scripts'       => 'ext.centralNotice.bannerController/bannerController.js',
	'position'      => 'top',
);

/* Configuration */

// $wgNoticeLang and $wgNoticeProject are used for targeting campaigns to specific wikis. These
// should be overridden on each wiki with the appropriate values.
// Actual user language (wgUserLanguage) is used for banner localization.
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

// Server-side banner cache timeout in seconds
$wgNoticeBannerMaxAge = 600;

// When the cookie set in SpecialHideBanners.php should expire
// This would typically be the end date for a fundraiser
// NOTE: This must be in UNIX timestamp format, for example, '1325462400'
$wgNoticeHideBannersExpiration = '';

// Whether to use the Translation extension for banner message translation
$wgNoticeUseTranslateExtension = false;

// These are countries that MaxMind will give out when information is a bit fuzzy. However,
// fundraising code doesn't like non ISO countries so we map them to the fictional point case 'XX'
$wgNoticeXXCountries = array( 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' );


/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages,
		$wgCentralNoticeLoader, $wgSpecialPageGroups, $wgCentralPagePath, $wgScript,
		$wgNoticeUseTranslateExtension, $wgAPIModules, $wgCentralBannerDispatcher,
		$wgContLang, $wgCentralBannerRecorder;

	// If $wgCentralPagePath hasn't been set, set it to the local script path.
	// We do this here since $wgScript isn't set until after LocalSettings.php loads.
	if ( $wgCentralPagePath === false ) {
		$wgCentralPagePath = $wgScript;
	}

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$apiDir = $dir . 'api/';
	$includeDir = $dir . 'includes/';

	// Register files
	$wgAutoloadClasses[ 'CentralNoticeDB' ] = $dir . 'CentralNotice.db.php';
	$wgAutoloadClasses[ 'CentralNotice' ] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses[ 'SpecialBannerLoader' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerListLoader' ] = $specialDir . 'SpecialBannerListLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerRandom' ] = $specialDir . 'SpecialBannerRandom.php';
	$wgAutoloadClasses[ 'SpecialRecordImpression' ] = $specialDir . 'SpecialRecordImpression.php';
	$wgAutoloadClasses[ 'SpecialHideBanners' ] = $specialDir . 'SpecialHideBanners.php';

	$wgAutoloadClasses[ 'BannerChooser' ] = $includeDir . 'BannerChooser.php';

	$wgAutoloadClasses[ 'ApiCentralNoticeAllocations' ] = $apiDir . 'ApiCentralNoticeAllocations.php';
	$wgAPIModules[ 'centralnoticeallocations' ] = 'ApiCentralNoticeAllocations';

	// Do this here, to ensure that wgScript has been bootstrapped
	if ( !$wgCentralBannerDispatcher ) {
		$wgCentralBannerDispatcher = "{$wgScript}/{$wgContLang->specialPage( 'BannerRandom' )}";
	}
	if ( !$wgCentralBannerRecorder ) {
		$wgCentralBannerRecorder = "{$wgScript}/{$wgContLang->specialPage( 'RecordImpression' )}";
	}

	// Register hooks
	$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'efCentralNoticeSchema';
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

	// Register special pages
	$wgSpecialPages[ 'BannerLoader' ] = 'SpecialBannerLoader';
	$wgSpecialPages[ 'BannerListLoader' ] = 'SpecialBannerListLoader';
	$wgSpecialPages[ 'BannerRandom' ] = 'SpecialBannerRandom';
	$wgSpecialPages[ 'RecordImpression' ] = 'SpecialRecordImpression';
	$wgSpecialPages[ 'HideBanners' ] = 'SpecialHideBanners';

	// If this is the wiki that hosts the management interface, load further components
	if ( $wgNoticeInfrastructure ) {
		$wgAutoloadClasses[ 'TemplatePager' ] = $dir . 'TemplatePager.php';
		$wgAutoloadClasses[ 'CentralNoticePager' ] = $dir . 'CentralNoticePager.php';
		$wgAutoloadClasses[ 'CentralNoticeCampaignLogPager' ] = $dir . 'CentralNoticeCampaignLogPager.php';
		$wgAutoloadClasses[ 'CentralNoticeBannerLogPager' ] = $dir . 'CentralNoticeBannerLogPager.php';
		$wgAutoloadClasses[ 'CentralNoticePageLogPager' ] = $dir . 'CentralNoticePageLogPager.php';
		$wgAutoloadClasses[ 'SpecialNoticeTemplate' ] = $specialDir . 'SpecialNoticeTemplate.php';
		$wgAutoloadClasses[ 'SpecialBannerAllocation' ] = $specialDir . 'SpecialBannerAllocation.php';
		$wgAutoloadClasses[ 'SpecialCentralNoticeLogs' ] = $specialDir . 'SpecialCentralNoticeLogs.php';

		if ( $wgNoticeUseTranslateExtension ) {
			$wgAutoloadClasses[ 'BannerMessageGroup' ] = $dir . 'BannerMessageGroup.php';
			$wgHooks[ 'TranslatePostInitGroups' ][ ] = 'efRegisterMessageGroups';
		}

		$wgSpecialPages[ 'CentralNotice' ] = 'CentralNotice';
		$wgSpecialPageGroups[ 'CentralNotice' ] = 'wiki'; // Wiki data and tools
		$wgSpecialPages[ 'NoticeTemplate' ] = 'SpecialNoticeTemplate';
		$wgSpecialPages[ 'BannerAllocation' ] = 'SpecialBannerAllocation';
		$wgSpecialPages[ 'CentralNoticeLogs' ] = 'SpecialCentralNoticeLogs';
	}
}

/**
 * LoadExtensionSchemaUpdates hook handler
 * This function makes sure that the database schema is up to date.
 *
 * @param $updater DatabaseUpdater|null
 * @return bool
 */
function efCentralNoticeSchema( $updater = null ) {
	$base = __DIR__;

	if ( $updater->getDB()->getType() == 'mysql' ) {
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_notices',
				$base . '/CentralNotice.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addField', 'cn_notices', 'not_preferred',
				$base . '/patches/patch-notice_preferred.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_notice_languages',
				$base . '/patches/patch-notice_languages.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addField', 'cn_templates', 'tmp_display_anon',
				$base . '/patches/patch-template_settings.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addField', 'cn_templates', 'tmp_fundraising',
				$base . '/patches/patch-template_fundraising.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_notice_countries',
				$base . '/patches/patch-notice_countries.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_notice_projects',
				$base . '/patches/patch-notice_projects.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_notice_log',
				$base . '/patches/patch-notice_log.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addTable', 'cn_template_log',
				$base . '/patches/patch-template_log.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addField', 'cn_templates', 'tmp_autolink',
				$base . '/patches/patch-template_autolink.sql', true
			)
		);
		$updater->addExtensionUpdate(
			array(
				'addField', 'cn_notices', 'not_buckets',
				$base . '/patches/patch-bucketing.sql', true
			)
		);
	}
	return true;
}

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
	// Setup siteNotice div and initialize the banner controller.
	// Comment hack for IE8 to collapse empty div
	$notice = '<!-- CentralNotice --><script>mw.centralNotice.initialize();</script>' . $notice;
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
		$wgCentralBannerRecorder;
	$vars[ 'wgNoticeFundraisingUrl' ] = $wgNoticeFundraisingUrl;
	$vars[ 'wgCentralPagePath' ] = $wgCentralPagePath;
	$vars[ 'wgNoticeBannerListLoader' ] = $wgContLang->specialPage( 'BannerListLoader' );
	$vars[ 'wgCentralBannerDispatcher' ] = $wgCentralBannerDispatcher;
	$vars[ 'wgCentralBannerRecorder' ] = $wgCentralBannerRecorder;
	$vars[ 'wgNoticeXXCountries' ] = $wgNoticeXXCountries;

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
	$files[ ] = __DIR__ . '/tests/CentralNoticeTest.php';
	return true;
}

/**
 * TranslatePostInitGroups hook handler
 * Add banner message groups to the list of message groups that should be
 * translated through the Translate extension.
 *
 * @param array $list
 * @return bool
 */
function efRegisterMessageGroups( &$list ) {
	$dbr = wfGetDB( DB_MASTER );

	// Find all the banners marked for translation
	$tables = array( 'page', 'revtag' );
	$vars   = array( 'page_id', 'page_namespace', 'page_title', );
	$conds  = array( 'page_id=rt_page', 'rt_type' => RevTag::getType( 'banner:translate' ) );
	$options = array( 'GROUP BY' => 'rt_page' );
	$res = $dbr->select( $tables, $vars, $conds, __METHOD__, $options );

	foreach ( $res as $r ) {
		$title = Title::makeTitle( $r->page_namespace, $r->page_title );
		$id = 'banner-' . $r->page_title;
		$bannerPageName = $r->page_title;
		$list[$id] = new BannerMessageGroup( $id, $bannerPageName );
		$list[$id]->setLabel( $title->getPrefixedText() );
	}
	return true;
}
