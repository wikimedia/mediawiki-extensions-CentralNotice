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

	// Register files
	$wgAutoloadClasses[ 'CentralNoticeDB' ] = $dir . 'CentralNotice.db.php';
	$wgAutoloadClasses[ 'CentralNotice' ] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses[ 'SpecialBannerLoader' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerListLoader' ] = $specialDir . 'SpecialBannerListLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerRandom' ] = $specialDir . 'SpecialBannerRandom.php';
	$wgAutoloadClasses[ 'SpecialRecordImpression' ] = $specialDir . 'SpecialRecordImpression.php';
	$wgAutoloadClasses[ 'SpecialHideBanners' ] = $specialDir . 'SpecialHideBanners.php';
	$wgAutoloadClasses[ 'SpecialCNReporter' ] = $specialDir . 'SpecialCNReporter.php';

	$wgAutoloadClasses[ 'BannerChooser' ] = $includeDir . 'BannerChooser.php';
	$wgAutoloadClasses[ 'CampaignLog' ] = $includeDir . 'CampaignLog.php';

	$wgAutoloadClasses[ 'ApiCentralNoticeAllocations' ] = $apiDir . 'ApiCentralNoticeAllocations.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeQueryCampaign' ] = $apiDir . 'ApiCentralNoticeQueryCampaign.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeLogs' ] = $apiDir . 'ApiCentralNoticeLogs.php';

	$wgAPIModules[ 'centralnoticeallocations' ] = 'ApiCentralNoticeAllocations';
	$wgAPIModules[ 'centralnoticequerycampaign' ] = 'ApiCentralNoticeQueryCampaign';
	$wgAPIListModules[ 'centralnoticelogs' ] = 'ApiCentralNoticeLogs';

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
	$wgSpecialPages[ 'CNReporter' ] = 'SpecialCNReporter';

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
			global $wgExtraNamespaces, $wgNamespacesWithSubpages, $wgTranslateMessageNamespaces;

			$wgAutoloadClasses[ 'BannerMessageGroup' ] = $dir . 'BannerMessageGroup.php';
			$wgHooks[ 'TranslatePostInitGroups' ][ ] = 'efRegisterMessageGroups';
            $wgHooks[ 'TranslateEventMessageGroupStateChange' ][] = array( 'BannerMessageGroup::updateBannerGroupStateHook' );
		}

		$wgSpecialPages[ 'CentralNotice' ] = 'CentralNotice';
		$wgSpecialPageGroups[ 'CentralNotice' ] = 'wiki'; // Wiki data and tools
		$wgSpecialPages[ 'NoticeTemplate' ] = 'SpecialNoticeTemplate';
		$wgSpecialPages[ 'BannerAllocation' ] = 'SpecialBannerAllocation';
		$wgSpecialPages[ 'CentralNoticeLogs' ] = 'SpecialCentralNoticeLogs';
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
				'addField', 'cn_template_log', 'tmplog_begin_prioritylangs',
				$base . '/patches/patch-prioritylangs.sql', true
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
	$notice = <<<EOT
<!-- CentralNotice --><script>
	mw.loader.using( 'ext.centralNotice.bannerController', function() { mw.centralNotice.initialize(); } );
</script>
$notice
EOT;

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

	// Making these calls too soon will causes issues with the namespace localization cache. This seems
	// to be just right. We require them at all because MW will 302 page requests made to non localized
	// namespaces which results in wasteful extra calls.
	if ( !$wgCentralBannerDispatcher ) {
		$wgCentralBannerDispatcher = "{$wgScript}/{$wgContLang->specialPage( 'BannerRandom' )}";
	}
	if ( !$wgCentralBannerRecorder ) {
		$wgCentralBannerRecorder = "{$wgScript}/{$wgContLang->specialPage( 'RecordImpression' )}";
	}

	$vars[ 'wgNoticeFundraisingUrl' ] = $wgNoticeFundraisingUrl;
	$vars[ 'wgCentralPagePath' ] = $wgCentralPagePath;
	$vars[ 'wgNoticeBannerListLoader' ] = $wgContLang->specialPage( 'BannerListLoader' );
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

	// Create the base aggregate group
	$conf = array();
	$conf['BASIC'] = array(
		'id' => BannerMessageGroup::TRANSLATE_GROUP_NAME_BASE,
		'label' => 'CentralNotice Banners',
		'description' => '{{int:centralnotice-aggregate-group-desc}}',
		'meta' => 1,
		'class' => 'AggregateMessageGroup',
		'namespace' => NS_CN_BANNER,
	);
	$conf['GROUPS'] = array();

	// Find all the banners marked for translation
	$tables = array( 'page', 'revtag' );
	$vars   = array( 'page_id', 'page_namespace', 'page_title', );
	$conds  = array( 'page_id=rt_page', 'rt_type' => RevTag::getType( 'banner:translate' ) );
	$options = array( 'GROUP BY' => 'rt_page' );
	$res = $dbr->select( $tables, $vars, $conds, __METHOD__, $options );

	foreach ( $res as $r ) {
        $grp = new BannerMessageGroup( $r->page_namespace, $r->page_title );
        $id = $grp::getTranslateGroupName( $r->page_title );
        $list[$id] = $grp;

		// Add the banner group to the aggregate group
		$conf['GROUPS'][] = $id;
	}

	// Update the subgroup meta with any new groups since the last time this was run
	$list[$conf['BASIC']['id']] = MessageGroupBase::factory( $conf );

	return true;
}
