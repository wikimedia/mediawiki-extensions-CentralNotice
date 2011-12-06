<?php

// Override these per-wiki to pass on via the loader to the text system
// for localization by language and project.
// Actual user language is used for localization; $wgNoticeLang is used
// for selective enabling/disabling on sites.
$wgNoticeLang = $wgLanguageCode;
$wgNoticeProject = 'wikipedia';

// List of available projects
$wgNoticeProjects = array(
	'wikipedia',
	'wiktionary',
	'wikiquote',
	'wikibooks',
	'wikinews',
	'wikisource',
	'wikiversity',
	'wikimedia',
	'commons',
	'meta',
	'wikispecies',
	'test'
);

// Enable the notice-hosting infrastructure on this wiki...
// Leave at false for wikis that only use a sister site for the control.
$wgNoticeInfrastructure = true;

// The name of the database which hosts the centralized campaign data
$wgCentralDBname = $wgDBname;

// The script path on the wiki that hosts the CentralNotice infrastructure
// For example 'http://meta.wikimedia.org/w/index.php'
$wgCentralPagePath = '';

// Enable the loader itself
// Allows to control the loader visibility, without destroying infrastructure
// for cached content
$wgCentralNoticeLoader = true;

// Flag for turning on fundraising specific features
$wgNoticeEnableFundraising = true;

// Base URL for default fundraiser landing page (without query string)
$wgNoticeFundraisingUrl = 'http://wikimediafoundation.org/wiki/Special:LandingCheck';

// Source for live counter information
$wgNoticeCounterSource = 'http://wikimediafoundation.org/wiki/Special:ContributionTotal?action=raw';

// Domain to set global cookies for.
// Example: '.wikipedia.org'
$wgNoticeCookieDomain = '';

// When the cookie set in SpecialHideBanners.php should expire
// This would typically be the end date for a fundraiser
// NOTE: This must be in UNIX timestamp format, for example, '1325462400'
$wgNoticeHideBannersExpiration = '';

$wgExtensionFunctions[] = 'efCentralNoticeSetup';

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'CentralNotice',
	'version'        => '2.0',
	'author'         => array( 'Brion Vibber', 'Ryan Kaldari' ),
	'url'            => 'http://www.mediawiki.org/wiki/Extension:CentralNotice',
	'descriptionmsg' => 'centralnotice-desc',
);

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['CentralNotice'] = $dir . 'CentralNotice.i18n.php';
$wgExtensionAliasesFiles['CentralNotice'] = $dir . 'CentralNotice.alias.php';

// Register user rights
$wgAvailableRights[] = 'centralnotice-admin';
$wgGroupPermissions['sysop']['centralnotice-admin'] = true; // Only sysops can make change

# Unit tests
$wgHooks['UnitTestsList'][] = 'efCentralNoticeUnitTests';

// Register ResourceLoader modules
$wgResourceModules['ext.centralNotice.interface'] = array(
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'CentralNotice',
	'scripts' => 'centralnotice.js',
	'styles' => 'centralnotice.css',
	'messages' => 'centralnotice-documentwrite-error'
);
$wgResourceModules['ext.centralNotice.bannerStats'] = array(
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'CentralNotice',
	'scripts' => 'bannerstats.js',
);

// Temporary setting to configure salt for Harvard banner protocol
$wgNoticeBanner_Harvard2011_salt = 'default';

/**
 * UnitTestsList hook handler
 */
function efCentralNoticeUnitTests( &$files ) {
	$files[] = dirname( __FILE__ ) . '/tests/CentralNoticeTest.php';
	return true;
}

/**
 * Called through wgExtensionFunctions
 */
function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages;
	global $wgCentralNoticeLoader, $wgSpecialPageGroups;

	$dir = dirname( __FILE__ ) . '/';

	if ( $wgCentralNoticeLoader ) {
		$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCentralNoticeSchema';
		$wgHooks['BeforePageDisplay'][] = 'efCentralNoticeLoader';
		$wgHooks['MakeGlobalVariablesScript'][] = 'efCentralNoticeDefaults';
		$wgHooks['SiteNoticeAfter'][] = 'efCentralNoticeDisplay';
		$wgHooks['SkinAfterBottomScripts'][] = 'efCentralNoticeGeoLoader';
	}

	$specialDir = $dir . 'special/';

	$wgSpecialPages['BannerLoader'] = 'SpecialBannerLoader';
	$wgAutoloadClasses['SpecialBannerLoader'] = $specialDir . 'SpecialBannerLoader.php';

	$wgSpecialPages['BannerListLoader'] = 'SpecialBannerListLoader';
	$wgAutoloadClasses['SpecialBannerListLoader'] = $specialDir . 'SpecialBannerListLoader.php';

	$wgSpecialPages['BannerController'] = 'SpecialBannerController';
	$wgAutoloadClasses['SpecialBannerController'] = $specialDir . 'SpecialBannerController.php';

	$wgSpecialPages['HideBanners'] = 'SpecialHideBanners';
	$wgAutoloadClasses['SpecialHideBanners'] = $specialDir . 'SpecialHideBanners.php';

	$wgAutoloadClasses['CentralNotice'] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses['CentralNoticeDB'] = $dir . 'CentralNotice.db.php';

	if ( $wgNoticeInfrastructure ) {
		$wgSpecialPages['CentralNotice'] = 'CentralNotice';
		$wgSpecialPageGroups['CentralNotice'] = 'wiki'; // Wiki data and tools"

		$wgSpecialPages['NoticeTemplate'] = 'SpecialNoticeTemplate';
		$wgAutoloadClasses['SpecialNoticeTemplate'] = $specialDir . 'SpecialNoticeTemplate.php';

		$wgSpecialPages['BannerAllocation'] = 'SpecialBannerAllocation';
		$wgAutoloadClasses['SpecialBannerAllocation'] = $specialDir . 'SpecialBannerAllocation.php';

		$wgSpecialPages['CentralNoticeLogs'] = 'SpecialCentralNoticeLogs';
		$wgAutoloadClasses['SpecialCentralNoticeLogs'] = $specialDir . 'SpecialCentralNoticeLogs.php';
		
		$wgAutoloadClasses['TemplatePager'] = $dir . 'TemplatePager.php';
		$wgAutoloadClasses['CentralNoticePager'] = $dir . 'CentralNoticePager.php';
		$wgAutoloadClasses['CentralNoticeCampaignLogPager'] = $dir . 'CentralNoticeCampaignLogPager.php';
		$wgAutoloadClasses['CentralNoticeBannerLogPager'] = $dir . 'CentralNoticeBannerLogPager.php';
		$wgAutoloadClasses['CentralNoticePageLogPager'] = $dir . 'CentralNoticePageLogPager.php';
	}
}

/**
 * LoadExtensionSchemaUpdates hook handler
 */
function efCentralNoticeSchema( $updater = null ) {
	$base = dirname( __FILE__ );
	if ( $updater === null ) {
		global $wgDBtype, $wgExtNewTables, $wgExtNewFields;

		if ( $wgDBtype == 'mysql' ) {
			$wgExtNewTables[] = array( 'cn_notices',
				$base . '/CentralNotice.sql' );
			$wgExtNewFields[] = array( 'cn_notices', 'not_preferred',
			   $base . '/patches/patch-notice_preferred.sql' );
			$wgExtNewTables[] = array( 'cn_notice_languages',
				$base . '/patches/patch-notice_languages.sql' );
			$wgExtNewFields[] = array( 'cn_templates', 'tmp_display_anon',
				$base . '/patches/patch-template_settings.sql' );
			$wgExtNewFields[] = array( 'cn_templates', 'tmp_fundraising',
				$base . '/patches/patch-template_fundraising.sql' );
			$wgExtNewTables[] = array( 'cn_notice_countries',
				$base . '/patches/patch-notice_countries.sql' );
			$wgExtNewTables[] = array( 'cn_notice_projects',
				$base . '/patches/patch-notice_projects.sql' );
			$wgExtNewTables[] = array( 'cn_notice_log',
				$base . '/patches/patch-notice_log.sql' );
			$wgExtNewTables[] = array( 'cn_template_log',
				$base . '/patches/patch-template_log.sql' );
			$wgExtNewFields[] = array( 'cn_templates', 'tmp_autolink',
				$base . '/patches/patch-template_autolink.sql' );
		}
	} else {
		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate( array( 'addTable', 'cn_notices',
				$base . '/CentralNotice.sql', true ) );
			$updater->addExtensionUpdate( array( 'addField', 'cn_notices', 'not_preferred',
				$base . '/patches/patch-notice_preferred.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cn_notice_languages',
				$base . '/patches/patch-notice_languages.sql', true ) );
			$updater->addExtensionUpdate( array( 'addField', 'cn_templates', 'tmp_display_anon',
				$base . '/patches/patch-template_settings.sql', true ) );
			$updater->addExtensionUpdate( array( 'addField', 'cn_templates', 'tmp_fundraising',
				$base . '/patches/patch-template_fundraising.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cn_notice_countries',
				$base . '/patches/patch-notice_countries.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cn_notice_projects',
				$base . '/patches/patch-notice_projects.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cn_notice_log',
				$base . '/patches/patch-notice_log.sql', true ) );
			$updater->addExtensionUpdate( array( 'addTable', 'cn_template_log',
				$base . '/patches/patch-template_log.sql', true ) );
			$updater->addExtensionUpdate( array( 'addField', 'cn_templates', 'tmp_autolink',
				$base . '/patches/patch-template_autolink.sql', true ) );
		}
	}
	return true;
}

/**
 * BeforePageDisplay hook handler
 */
function efCentralNoticeLoader( $out, $skin ) {
	global $wgOut;

	// Include '.js' to exempt script from squid cache expiration override
	$centralLoader = SpecialPage::getTitleFor( 'BannerController' )->getLocalUrl( 'cache=/cn.js' );

	// Insert the banner controller Javascript into the page
	$wgOut->addScriptFile( $centralLoader );

	return true;
}

/**
 * SkinAfterBottomScripts hook handler
 */
function efCentralNoticeGeoLoader( $skin, &$text ) {
	// Insert the geo IP lookup
	$text .= Html::linkedScript( "//geoiplookup.wikimedia.org/" );
	return true;
}


/**
 * MakeGlobalVariablesScript hook handler
 */
function efCentralNoticeDefaults( &$vars ) {
	// Using global $wgUser for compatibility with 1.18
	global $wgNoticeProject, $wgUser, $wgMemc;

	// Initialize global Javascript variables. We initialize Geo with empty values so if the geo
	// IP lookup fails we don't have any surprises.
	$geo = array( 'city' => '', 'country' => '' );
	$vars['Geo'] = $geo; // change this to wgGeo as soon as Mark updates on his end
	$vars['wgNoticeProject'] = $wgNoticeProject;

	// XXX: Temporary WMF-specific code for the 2011 Harvard survey invitation banner.
	// Only do this for logged-in users, keeping anonymous user output equal (for Squid-cache).
	// Also, don't run if the UserDailyContribs-extension isn't installed.
	if ( $wgUser->isLoggedIn() && function_exists( 'getUserEditCountSince' ) ) {

		$cacheKey = wfMemcKey( 'CentralNotice', 'Harvard2011', $wgUser->getId() );
		$data = $wgMemc->get( $cacheKey );

		// Cached ?
		if ( !$data ) {
			/**
			 * To be eligible, the user must match all of the following:
			 * - have an account
			 * - not be a bot (userright=bot)
			 * .. and match one of the following:
			 * - be an admin (group=sysop)
			 * - have an editcount higher than 300, of which 20 within the last 180 days (on the launch date)
			 * - have had their account registered for less than 30 days (on to the launch date)
			 */
			if ( $wgUser->isAllowed( 'bot' ) ) {
				$data = false;

			} else {
				global $wgNoticeBanner_Harvard2011_salt;

				$launchTimestamp = wfTimestamp( TS_UNIX, '2011-12-06 00:00:00' );
				$groups = $wgUser->getGroups();
				$registrationDate = $wgUser->getRegistration() ? $wgUser->getRegistration() : 0;
				$daysOld = floor( ( $launchTimestamp - wfTimestamp( TS_UNIX, $registrationDate ) ) / ( 60*60*24 ) );
				$salt = $wgNoticeBanner_Harvard2011_salt;
				$metrics = array(
					// "username" the user's username
					'username' => $wgUser->getName(),

					// "group" is the group name(s) of the user (comma-separated).
					'group' => join( ',', $groups ),

					// "duration" is the number of days since the user registered his (on the launching date).
					// Note: Will be negative if user registered after launch date!
					'duration' => $daysOld,

					// "editcounts" is the user's total number of edits
					'editcounts' => $wgUser->getEditCount() == NULL ? 0 : $wgUser->getEditCount(),

					// "last6monthseditcount" is the user's total number of edits in the last 180 days (on the launching date)
					'last6monthseditcount' => getUserEditCountSince(
						$launchTimestamp - ( 180*24*3600 ),
						$wgUser,
						$launchTimestamp
					),
				);
				$realData = array(
					'id' => $wgUser->getId(),
					'metrics' => $metrics,
					'hash' => md5( $salt . serialize( $metrics ) ),
				);

				if (
					in_array( 'sysop', $groups )
					|| ( $metrics['editcounts'] >= 300 && $metrics['last6monthseditcount'] >= 20 )
					|| ( $metrics['duration'] < 30 )
				) {
					$data = $realData;
				} else {
					$data = false;
				}
			}

			$wgMemc->set( $cacheKey, $data, strtotime( '+10 days' ) );
		}

		$vars['wgNoticeBanner_Harvard2011'] = $data;

	}

	return true;
}

/**
 * SiteNoticeAfter hook handler
 */
function efCentralNoticeDisplay( &$notice ) {
	// setup siteNotice div
	$notice =
		'<!-- centralNotice loads here -->'. // hack for IE8 to collapse empty div
		$notice;
	return true;
}
