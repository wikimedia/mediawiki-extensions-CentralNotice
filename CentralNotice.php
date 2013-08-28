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

$dir = __DIR__;

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
	/* disabled, see bug 53443
	'GlobalAllocation' => array(
		'type' => 'views',
		'message' => 'centralnotice-global-allocation',
	),
	*/
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

/* Setup */
require_once $dir . '/CentralNotice.hooks.php';
require_once $dir . '/CentralNotice.modules.php';

// Register message files
$wgExtensionMessagesFiles[ 'CentralNotice' ] = $dir . '/CentralNotice.i18n.php';
$wgExtensionMessagesFiles[ 'CentralNoticeAliases' ] = $dir . '/CentralNotice.alias.php';

// Register user rights
$wgAvailableRights[] = 'centralnotice-admin';
$wgGroupPermissions[ 'sysop' ][ 'centralnotice-admin' ] = true; // Only sysops can make change