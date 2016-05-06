<?php
/**
 * General hook definitions
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @file
 * @ingroup Extensions
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

global $wgExtensionFunctions, $wgHooks;

$wgExtensionFunctions[] = 'efCentralNoticeSetup';
$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'CNDatabasePatcher::applyUpdates';
$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = array( 'CentralNotice::addNavigationTabs' );

/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages,
		   $wgCentralNoticeLoader, $wgScript,
		   $wgNoticeUseTranslateExtension, $wgAPIModules, $wgAPIListModules,
		   $wgAvailableRights, $wgGroupPermissions, $wgCentralDBname, $wgDBname;

	// Default for a standalone wiki is that the CN tables are in the main database.
	if ( $wgCentralDBname === false ) {
		$wgCentralDBname = $wgDBname;
	}

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$apiDir = $dir . 'api/';
	$includeDir = $dir . 'includes/';
	$htmlFormDir = $includeDir . '/HtmlFormElements/';

	// Register files
	$wgAutoloadClasses[ 'CentralNotice' ] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses[ 'CentralNoticeBannerLogPager' ] = $dir . 'CentralNoticeBannerLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticeCampaignLogPager' ] = $dir . 'CentralNoticeCampaignLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticePageLogPager' ] = $dir . 'CentralNoticePageLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticePager' ] = $dir . 'CentralNoticePager.php';
	$wgAutoloadClasses[ 'SpecialBannerAllocation' ] = $specialDir . 'SpecialBannerAllocation.php';
	$wgAutoloadClasses[ 'SpecialBannerLoader' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerRandom' ] = $specialDir . 'SpecialBannerRandom.php';
	$wgAutoloadClasses[ 'SpecialCentralNoticeBanners' ] = $specialDir . 'SpecialCentralNoticeBanners.php';
	$wgAutoloadClasses[ 'SpecialCentralNoticeLogs' ] = $specialDir . 'SpecialCentralNoticeLogs.php';
	$wgAutoloadClasses[ 'SpecialGlobalAllocation' ] = $specialDir . 'SpecialGlobalAllocation.php';
	$wgAutoloadClasses[ 'SpecialNoticeTemplate' ] = $specialDir . 'SpecialNoticeTemplate.php';
	$wgAutoloadClasses[ 'SpecialRecordImpression' ] = $specialDir . 'SpecialRecordImpression.php';
	$wgAutoloadClasses[ 'SpecialHideBanners' ] = $specialDir . 'SpecialHideBanners.php';
	$wgAutoloadClasses[ 'SpecialCNReporter' ] = $specialDir . 'SpecialCNReporter.php';

	$wgAutoloadClasses[ 'BannerLoaderException' ] = $specialDir . 'SpecialBannerLoader.php';

	$wgAutoloadClasses[ 'Banner' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'AllocationCalculator' ] = $includeDir . 'AllocationCalculator.php';
	$wgAutoloadClasses[ 'BannerDataException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerContentException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerExistenceException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerMessage' ] = $includeDir . 'BannerMessage.php';
	$wgAutoloadClasses[ 'BannerMessageGroup' ] = $includeDir . 'BannerMessageGroup.php';
	$wgAutoloadClasses[ 'BannerRenderer' ] = $includeDir . 'BannerRenderer.php';
	$wgAutoloadClasses[ 'ChoiceDataProvider' ] = $includeDir . 'ChoiceDataProvider.php';
	$wgAutoloadClasses[ 'CNChoiceDataResourceLoaderModule' ] = $includeDir . 'CNChoiceDataResourceLoaderModule.php';
	$wgAutoloadClasses[ 'Campaign' ] = $includeDir . 'Campaign.php';
	$wgAutoloadClasses['CampaignCriteria'] = $specialDir . 'SpecialGlobalAllocation.php';
	$wgAutoloadClasses['CampaignExistenceException'] = $includeDir . 'Campaign.php';
	$wgAutoloadClasses[ 'CampaignLog' ] = $includeDir . 'CampaignLog.php';
	$wgAutoloadClasses['CentralNoticeHtmlForm'] = $specialDir . 'SpecialCentralNoticeBanners.php';
	$wgAutoloadClasses[ 'CNBannerPager' ] = $includeDir . 'CNBannerPager.php';
	$wgAutoloadClasses[ 'CNCampaignPager' ] = $includeDir . 'CNCampaignPager.php';
	$wgAutoloadClasses[ 'CNDeviceTarget' ] = $includeDir . 'CNDeviceTarget.php';
	$wgAutoloadClasses['EmptyBannerException'] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'GeoTarget' ] = $includeDir . 'GeoTarget.php';
	$wgAutoloadClasses['HTMLBannerPagerNavigation'] = $includeDir . 'CNBannerPager.php';
	$wgAutoloadClasses['HTMLLargeMultiSelectField'] = $specialDir . 'SpecialCentralNoticeBanners.php';
	$wgAutoloadClasses[ 'IBannerMixin' ] = $includeDir . 'IBannerMixin.php';
	$wgAutoloadClasses['LanguageSelectHeaderElement'] = $specialDir . 'SpecialCentralNoticeBanners.php';
	$wgAutoloadClasses['MissingRequiredParamsException'] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'StaleCampaignException' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'MixinController' ] = $includeDir . 'MixinController.php';
	$wgAutoloadClasses['MixinNotFoundException'] = $includeDir . 'MixinController.php';

	$wgAutoloadClasses[ 'HTMLCentralNoticeBanner' ] = $htmlFormDir . 'HTMLCentralNoticeBanner.php';
	$wgAutoloadClasses[ 'HTMLCentralNoticeBannerMessage' ] = $htmlFormDir . 'HTMLCentralNoticeBannerMessage.php';

	$wgAutoloadClasses[ 'CNDatabasePatcher' ] = $dir . 'patches/CNDatabasePatcher.php';

	$wgAutoloadClasses[ 'ApiCentralNoticeChoiceData' ] = $apiDir . 'ApiCentralNoticeChoiceData.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeQueryCampaign' ] = $apiDir . 'ApiCentralNoticeQueryCampaign.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeLogs' ] = $apiDir . 'ApiCentralNoticeLogs.php';
	$wgAutoloadClasses[ 'TemplatePager' ] = $dir . 'TemplatePager.php';

	$wgAutoloadClasses[ 'CNDatabase' ] = $includeDir . 'CNDatabase.php';
	$wgAPIModules[ 'centralnoticechoicedata' ] = 'ApiCentralNoticeChoiceData';
	$wgAPIModules[ 'centralnoticequerycampaign' ] = 'ApiCentralNoticeQueryCampaign';
	$wgAPIListModules[ 'centralnoticelogs' ] = 'ApiCentralNoticeLogs';

	// Register hooks
	// TODO: replace ef- global functions with static methods in CentralNoticeHooks
	$wgHooks['ResourceLoaderTestModules'][] = 'efCentralNoticeResourceLoaderTestModules';
	$wgHooks['UnitTestsList'][] = 'efCentralNoticeUnitTests';
	$wgHooks['EventLoggingRegisterSchemas'][] = 'efCentralNoticeEventLoggingRegisterSchemas';

	// If CentralNotice banners should be shown on this wiki, load the components we need for
	// showing banners. For discussion of banner loading strategies, see
	// http://wikitech.wikimedia.org/view/CentralNotice/Optimizing_banner_loading
	if ( $wgCentralNoticeLoader ) {
		$wgHooks[ 'MakeGlobalVariablesScript' ][ ] = 'efCentralNoticeDefaults';
		$wgHooks[ 'BeforePageDisplay' ][ ] = 'efCentralNoticeLoader';
		$wgHooks[ 'SiteNoticeAfter' ][ ] = 'efCentralNoticeDisplay';
		$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'efResourceLoaderGetConfigVars';
		// Register mobile modules
		// TODO To remove in a subsequent patch, when we start adding
		// ext.centralNotice.startUp to HTML instead of the current mix.
		$wgHooks[ 'SkinMinervaDefaultModules' ][] = 'onSkinMinervaDefaultModules';
	}

	// Tell the UserMerge extension where we store user ids
	$wgHooks[ 'UserMergeAccountFields' ][] = function( &$updateFields ) {
		global $wgNoticeInfrastructure;
		if ( $wgNoticeInfrastructure ) {
			// array( tableName, idField, textField )
			$updateFields[] = array( 'cn_notice_log', 'notlog_user_id' );
			$updateFields[] = array( 'cn_template_log', 'tmplog_user_id' );
		}
		return true;
	};

	// Register special pages
	$wgSpecialPages[ 'BannerLoader' ] = 'SpecialBannerLoader';
	$wgSpecialPages[ 'BannerRandom' ] = 'SpecialBannerRandom';
	$wgSpecialPages[ 'RecordImpression' ] = 'SpecialRecordImpression';
	$wgSpecialPages[ 'HideBanners' ] = 'SpecialHideBanners';
	$wgSpecialPages[ 'CNReporter' ] = 'SpecialCNReporter';

	// If this is the wiki that hosts the management interface, load further components
	if ( $wgNoticeInfrastructure ) {
		if ( $wgNoticeUseTranslateExtension ) {
			$wgHooks[ 'TranslatePostInitGroups' ][ ] = 'BannerMessageGroup::registerGroupHook';
			$wgHooks[ 'TranslateEventMessageGroupStateChange' ][] = 'BannerMessageGroup::updateBannerGroupStateHook';
		}

		$wgSpecialPages[ 'CentralNotice' ] = 'CentralNotice';
		$wgSpecialPages[ 'NoticeTemplate' ] = 'SpecialNoticeTemplate';
		$wgSpecialPages[ 'GlobalAllocation' ] = 'SpecialGlobalAllocation';
		$wgSpecialPages[ 'BannerAllocation' ] = 'SpecialBannerAllocation';
		$wgSpecialPages[ 'CentralNoticeLogs' ] = 'SpecialCentralNoticeLogs';
		$wgSpecialPages[ 'CentralNoticeBanners'] = 'SpecialCentralNoticeBanners';

		// Register user rights for editing
		$wgAvailableRights[] = 'centralnotice-admin';
		$wgGroupPermissions[ 'sysop' ][ 'centralnotice-admin' ] = true; // Only sysops can make change
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
$wgHooks['CanonicalNamespaces'][] = 'efCentralNoticeCanonicalNamespaces';

function efCentralNoticeCanonicalNamespaces( &$namespaces ) {
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
	global $wgCentralHost, $wgServer;

	// Insert DNS prefetch for banner loading
	if ( $wgCentralHost && $wgCentralHost !== $wgServer ) {
		$out->addHeadItem( 'dns-prefetch', '<link rel="dns-prefetch" href="' . htmlspecialchars( $wgCentralHost ) . '" />' );
	}
	// Insert the banner controller
	// TODO Change this to startUp once it's determined that a rollback is not
	// needed.
	$out->addModules( 'ext.centralNotice.bannerController' );
	return true;
}

/**
 * MakeGlobalVariablesScript hook handler
 * This function sets the pseudo-global JavaScript variables that are used by CentralNotice
 *
 * @param $vars array
 * @return bool
 */
function efCentralNoticeDefaults( &$vars ) {
	// Using global $wgUser for compatibility with 1.18
	global $wgNoticeProject, $wgCentralNoticeCookiesToDelete,
		$wgCentralNoticeCategoriesUsingLegacy,
		$wgUser, $wgMemc;

	// FIXME Is this no longer used anywhere in JS following the switch to
	// client-side banner selection? If so, remove it.
	$vars[ 'wgNoticeProject' ] = $wgNoticeProject;
	$vars[ 'wgCentralNoticeCookiesToDelete' ] = $wgCentralNoticeCookiesToDelete;

	$vars[ 'wgCentralNoticeCategoriesUsingLegacy' ] =
		$wgCentralNoticeCategoriesUsingLegacy;

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
	global $wgNoticeFundraisingUrl, $wgContLang, $wgNoticeXXCountries,
		   $wgNoticeInfrastructure, $wgCentralBannerRecorder, 
		   $wgNoticeNumberOfBuckets, $wgNoticeBucketExpiry,
		   $wgNoticeNumberOfControllerBuckets, $wgNoticeCookieDurations, $wgScript,
		   $wgNoticeHideUrls, $wgNoticeOldCookieEpoch, $wgCentralNoticeSampleRate,
		   $wgCentralSelectedBannerDispatcher,
		   $wgCentralNoticePerCampaignBucketExtension, $wgCentralNoticeCampaignMixins;

	// Making these calls too soon will causes issues with the namespace localisation cache. This seems
	// to be just right. We require them at all because MW will 302 page requests made to non localised
	// namespaces which results in wasteful extra calls.
	if ( !$wgCentralSelectedBannerDispatcher ) {
		$wgCentralSelectedBannerDispatcher = SpecialPage::getTitleFor( 'BannerLoader' )->getLocalUrl();
	}
	if ( !$wgCentralBannerRecorder ) {
		$wgCentralBannerRecorder = SpecialPage::getTitleFor( 'RecordImpression' )->getLocalUrl();
	}

	// FIXME Dicey code! It is likely that the following is never executed in
	// the WMF's setup. Also getMobileUrl() may not work in some cases.

	// Mangle infrastructure URLs for mobile use -- this should always be last.
	if ( class_exists( 'MobileContext' ) ) {
		// Where possible; make things mobile friendly
		$mc = MobileContext::singleton();
		if ( $mc->shouldDisplayMobileView() ) {
			// TODO Remove $wgNoticeFundraisingUrl, no longer used
			$wgNoticeFundraisingUrl = $mc->getMobileUrl( $wgNoticeFundraisingUrl );
			$wgCentralBannerRecorder = $mc->getMobileUrl( $wgCentralBannerRecorder );
			$wgCentralSelectedBannerDispatcher = $mc->getMobileUrl( $wgCentralSelectedBannerDispatcher );
		}
	}

	// TODO Remove, no longer used
	$vars[ 'wgNoticeFundraisingUrl' ] = $wgNoticeFundraisingUrl;

	$vars[ 'wgCentralBannerRecorder' ] = $wgCentralBannerRecorder;
	$vars[ 'wgCentralNoticeSampleRate' ] = $wgCentralNoticeSampleRate;

	// TODO Remove, no longer used
	$vars[ 'wgNoticeXXCountries' ] = $wgNoticeXXCountries;

	$vars[ 'wgNoticeNumberOfBuckets' ] = $wgNoticeNumberOfBuckets;
	$vars[ 'wgNoticeBucketExpiry' ] = $wgNoticeBucketExpiry;
	$vars[ 'wgNoticeNumberOfControllerBuckets' ] = $wgNoticeNumberOfControllerBuckets;
	$vars[ 'wgNoticeCookieDurations' ] = $wgNoticeCookieDurations;
	$vars[ 'wgNoticeHideUrls' ] = $wgNoticeHideUrls;

	// TODO Remove this after banner display refactor has been deployed
	$vars[ 'wgNoticeOldCookieApocalypse' ] = (int)wfTimestamp( TS_UNIX, $wgNoticeOldCookieEpoch );
	$vars[ 'wgCentralSelectedBannerDispatcher' ] = $wgCentralSelectedBannerDispatcher;
	$vars[ 'wgCentralNoticePerCampaignBucketExtension' ] = $wgCentralNoticePerCampaignBucketExtension;

	if ( $wgNoticeInfrastructure ) {
		// Add campaign mixin defs for use in admin interface
		$vars[ 'wgCentralNoticeCampaignMixins' ] = $wgCentralNoticeCampaignMixins;
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
	global $wgAutoloadClasses;

	$wgAutoloadClasses['CentralNoticeTestFixtures'] = __DIR__ . '/tests/CentralNoticeTestFixtures.php';

	$files[ ] = __DIR__ . '/tests/ApiCentralNoticeChoiceDataTest.php';
	$files[ ] = __DIR__ . '/tests/CentralNoticeTest.php';
	$files[ ] = __DIR__ . '/tests/AllocationCalculatorTest.php';
	$files[ ] = __DIR__ . '/tests/ChoiceDataProviderTest.php';
	$files[ ] = __DIR__ . '/tests/BannerTest.php';
	$files[ ] = __DIR__ . '/tests/CNChoiceDataResourceLoaderModuleTest.php';
	return true;
}

/**
 * ResourceLoaderTestModules hook handler
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
 *
 * @param array $testModules
 * @param ResourceLoader $resourceLoader
 * @return bool
 */
function efCentralNoticeResourceLoaderTestModules( array &$testModules,
	ResourceLoader $resourceLoader
) {
	global $wgResourceModules, $wgAutoloadClasses;

	// Set up test fixtures module, which is added as a dependency for all QUnit
	// tests.
	$testModules['qunit']['ext.centralNotice.testFixtures'] = array(
			'class'         => 'CNTestFixturesResourceLoaderModule'
	);

	// These classes are only used here or in phpunit tests
	$wgAutoloadClasses['CNTestFixturesResourceLoaderModule'] = __DIR__ . '/tests/CNTestFixturesResourceLoaderModule.php';
	// Note: the following setting is repeated in efCentralNoticeUnitTests()
	$wgAutoloadClasses['CentralNoticeTestFixtures'] = __DIR__ . '/tests/CentralNoticeTestFixtures.php';

	$testModuleBoilerplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'CentralNotice',
	);

	// find test files for every RL module
	$prefix = 'ext.centralNotice';
	foreach ( $wgResourceModules as $key => $module ) {

		if ( substr( $key, 0, strlen( $prefix ) ) ===
			$prefix && isset( $module['scripts'] ) ) {

			$testFiles = array();

			foreach ( ( ( array ) $module['scripts'] ) as $script ) {

				$testFile = 'tests/qunit/' . $script;
				$testFile = preg_replace( '/.js$/', '.tests.js', $testFile );

				// if a test file exists for a given JS file, add it
				if ( file_exists( __DIR__ . '/' . $testFile ) ) {
					$testFiles[] = $testFile;
				}
			}

			// if test files exist for given module, create a corresponding test
			// module
			if ( count( $testFiles ) > 0 ) {

				$testModules['qunit']["$key.tests"] = $testModuleBoilerplate +
					array(
						'dependencies' =>
							array( $key, 'ext.centralNotice.testFixtures' ),

						'scripts' => $testFiles,
					);
			}
		}
	}

	return true;
}

// TODO To remove in a subsequent patch, when we start adding
// ext.centralNotice.startUp to HTML instead of the current mix (once we
// determine that a rollback won't be necessary).
/**
 * Place CentralNotice ResourceLoader modules onto mobile pages.
 *
 * @param Skin $skin
 * @param array $modules
 *
 * @return bool
 */
function onSkinMinervaDefaultModules( Skin $skin, array &$modules ) {
	$modules[ 'centralnotice' ] = array(
		'ext.centralNotice.bannerController.mobile',
		'ext.centralNotice.choiceData',
	);

	return true;
}

/**
 * EventLoggingRegisterSchemas hook handler.
 *
 * @param array $schemas The schemas currently registered with the EventLogging
 *  extension
 * @return bool Always true
 */
function efCentralNoticeEventLoggingRegisterSchemas( &$schemas ) {
	// Coordinate with makeEventLoggingURL() in
	// ext.centralNotice.bannerHistoryLogger.js
	$schemas['CentralNoticeBannerHistory'] = 14321636;
	return true;
}
