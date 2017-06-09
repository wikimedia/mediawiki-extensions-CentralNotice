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

class CentralNoticeHooks {

	/**
	 * Conditional configuration
	 */
	public static function onRegistration() {
		global $wgHooks, $wgNoticeInfrastructure, $wgSpecialPages,
			$wgCentralNoticeLoader, $wgNoticeUseTranslateExtension,
			$wgAvailableRights, $wgGroupPermissions, $wgCentralDBname, $wgDBname;

		// Default for a standalone wiki is that the CN tables are in the main database.
		if ( !$wgCentralDBname ) {
			$wgCentralDBname = $wgDBname;
		}

		// If CentralNotice banners should be shown on this wiki, load the components we need for
		// showing banners. For discussion of banner loading strategies, see
		// http://wikitech.wikimedia.org/view/CentralNotice/Optimizing_banner_loading
		if ( $wgCentralNoticeLoader ) {
			$wgHooks['MakeGlobalVariablesScript'][] =
				'CentralNoticeHooks::onMakeGlobalVariablesScript';
			$wgHooks['BeforePageDisplay'][] = 'CentralNoticeHooks::onBeforePageDisplay';
			$wgHooks['SiteNoticeAfter'][] = 'CentralNoticeHooks::onSiteNoticeAfter';
			$wgHooks['ResourceLoaderGetConfigVars'][] =
				'CentralNoticeHooks::onResourceLoaderGetConfigVars';
		}

		// If this is the wiki that hosts the management interface, load further components
		if ( $wgNoticeInfrastructure ) {
			if ( $wgNoticeUseTranslateExtension ) {
				$wgHooks['TranslatePostInitGroups'][] = 'BannerMessageGroup::registerGroupHook';
				$wgHooks['TranslateEventMessageGroupStateChange'][] =
					'BannerMessageGroup::updateBannerGroupStateHook';
			}

			$wgSpecialPages['CentralNotice'] = 'CentralNotice';
			$wgSpecialPages['NoticeTemplate'] = 'SpecialNoticeTemplate';
			$wgSpecialPages['GlobalAllocation'] = 'SpecialGlobalAllocation';
			$wgSpecialPages['BannerAllocation'] = 'SpecialBannerAllocation';
			$wgSpecialPages['CentralNoticeLogs'] = 'SpecialCentralNoticeLogs';
			$wgSpecialPages['CentralNoticeBanners'] = 'SpecialCentralNoticeBanners';

			// Register user rights for editing
			$wgAvailableRights[] = 'centralnotice-admin';
			// Only sysops can make change
			$wgGroupPermissions['sysop']['centralnotice-admin'] = true;
		}
	}

	/**
	 * Initialization: set default values for some config globals. Invoked via
	 * $wgExtensionFunctions.
	 */
	public static function initCentralNotice() {
		global $wgCentralBannerRecorder, $wgCentralSelectedBannerDispatcher,
			$wgCentralSelectedMobileBannerDispatcher;

		// Defaults for infrastructure wiki URLs
		if ( !$wgCentralBannerRecorder ) {
			$wgCentralBannerRecorder =
				SpecialPage::getTitleFor( 'RecordImpression' )->getLocalUrl();
		}

		if ( !$wgCentralSelectedBannerDispatcher ) {
			$wgCentralSelectedBannerDispatcher =
				SpecialPage::getTitleFor( 'BannerLoader' )->getLocalUrl();
		}

		if ( !$wgCentralSelectedMobileBannerDispatcher && class_exists( 'MobileContext' ) ) {
			$wgCentralSelectedMobileBannerDispatcher = $wgCentralSelectedBannerDispatcher;
		}
	}

	/**
	 * Tell the UserMerge extension where we store user ids
	 */
	public static function onUserMergeAccountFields( &$updateFields ) {
		global $wgNoticeInfrastructure;
		if ( $wgNoticeInfrastructure ) {
			// array( tableName, idField, textField )
			$updateFields[] = [ 'cn_notice_log', 'notlog_user_id' ];
			$updateFields[] = [ 'cn_template_log', 'tmplog_user_id' ];
		}
		return true;
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
	public static function onCanonicalNamespaces( &$namespaces ) {
		global $wgExtraNamespaces, $wgNamespacesWithSubpages, $wgTranslateMessageNamespaces;
		global $wgNoticeUseTranslateExtension, $wgNoticeInfrastructure;

		// TODO XXX Old doc copied from legacy follows, verify accuracy!
		// When using the group review feature of translate; this
		// will be the namespace ID for the banner staging area -- ie: banners
		// here are world editable and will not be moved to the MW namespace
		// until they are in $wgNoticeTranslateDeployStates

		// TODO This may be unnecessary. Must coordinate with extension.json
		if ( !defined( 'NS_CN_BANNER' ) ) {
			define( 'NS_CN_BANNER', 866 );
			define( 'NS_CN_BANNER_TALK', 867 );
		}

		if ( $wgNoticeInfrastructure && $wgNoticeUseTranslateExtension ) {
			$wgExtraNamespaces[NS_CN_BANNER] = 'CNBanner';
			$wgTranslateMessageNamespaces[] = NS_CN_BANNER;

			$wgExtraNamespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
			$wgNamespacesWithSubpages[NS_CN_BANNER_TALK] = true;

			$namespaces[NS_CN_BANNER] = 'CNBanner';
			$namespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
		}

		return true;
	}

	/**
	 * BeforePageDisplay hook handler
	 * This function adds the startUp and geoIP modules to the page (as needed)
	 *
	 * @param $out  OutputPage
	 * @param $skin Skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( $out, $skin ) {
		global $wgCentralHost, $wgServer, $wgRequest;

		// Always add geoIP
		// TODO Separate geoIP from CentralNotice
		$out->addModules( 'ext.centralNotice.geoIP' );

		// If we're editing or we're on a special page, bow out now
		if ( $out->getTitle()->inNamespace( NS_SPECIAL ) ||
			( $wgRequest->getText( 'action' ) === 'edit' ) ) {
			return true;
		}

		// Insert DNS prefetch for banner loading
		if ( $wgCentralHost && $wgCentralHost !== $wgServer ) {
			$out->addHeadItem(
				'cn-dns-prefetch',
				'<link rel="dns-prefetch" href="' . htmlspecialchars( $wgCentralHost ) . '" />'
			);
		}

		// Insert the startup module
		$out->addModules( 'ext.centralNotice.startUp' );

		return true;
	}

	/**
	 * MakeGlobalVariablesScript hook handler
	 * This function sets the pseudo-global JavaScript variables that are used by CentralNotice
	 *
	 * @param $vars array
	 * @return bool
	 */
	public static function onMakeGlobalVariablesScript( &$vars ) {
		// Using global $wgUser for compatibility with 1.18
		global $wgNoticeProject, $wgCentralNoticeCookiesToDelete,
			$wgCentralNoticeCategoriesUsingLegacy,
			$wgCentralNoticeGeoIPBackgroundLookupModule,
			$wgUser, $wgMemc;

		// FIXME Is this no longer used anywhere in JS following the switch to
		// client-side banner selection? If so, remove it.
		$vars[ 'wgNoticeProject' ] = $wgNoticeProject;
		$vars[ 'wgCentralNoticeCookiesToDelete' ] = $wgCentralNoticeCookiesToDelete;

		$vars[ 'wgCentralNoticeCategoriesUsingLegacy' ] =
			$wgCentralNoticeCategoriesUsingLegacy;

		// No need to provide this variable if it's null, because mw.config.get()
		// will return null if it's not there.
		if ( $wgCentralNoticeGeoIPBackgroundLookupModule ) {
			$vars[ 'wgCentralNoticeGeoIPBackgroundLookupModule' ] =
				$wgCentralNoticeGeoIPBackgroundLookupModule;
		}

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
					$userData = [];

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
	public static function onSiteNoticeAfter( &$notice ) {
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
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgNoticeXXCountries,
			$wgNoticeInfrastructure, $wgCentralBannerRecorder,
			$wgNoticeNumberOfBuckets, $wgNoticeBucketExpiry,
			$wgNoticeNumberOfControllerBuckets, $wgNoticeCookieDurations,
			$wgNoticeHideUrls, $wgNoticeOldCookieEpoch, $wgCentralNoticeSampleRate,
			$wgCentralSelectedBannerDispatcher, $wgCentralSelectedMobileBannerDispatcher,
			$wgCentralNoticePerCampaignBucketExtension, $wgCentralNoticeCampaignMixins;

		// TODO Check if the following comment still applies
		// Making these calls too soon will causes issues with the namespace localisation cache.
		// This seems to be just right. We require them at all because MW will 302 page requests
		// made to non localised namespaces which results in wasteful extra calls.

		// Set infrastructure URL variables, which change between mobile/desktop
		if ( class_exists( 'MobileContext' ) ) {
			$mc = MobileContext::singleton();
			$displayMobile = $mc->shouldDisplayMobileView();
		} else {
			$displayMobile = false;
		}

		if ( $displayMobile ) {
			$wgCentralBannerRecorder = $mc->getMobileUrl( $wgCentralBannerRecorder );
			$bannerDispatcher = $wgCentralSelectedMobileBannerDispatcher;
		} else {
			$bannerDispatcher = $wgCentralSelectedBannerDispatcher;
		}

		$vars[ 'wgCentralNoticeActiveBannerDispatcher' ] = $bannerDispatcher;
		// TODO Temporary setting to support cached javascript following deploy; remove.
		$vars[ 'wgCentralSelectedBannerDispatcher' ] = $bannerDispatcher;
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
		$vars[ 'wgCentralNoticePerCampaignBucketExtension' ] =
			$wgCentralNoticePerCampaignBucketExtension;

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
	public static function onUnitTestsList( &$files ) {
		global $wgAutoloadClasses;

		$wgAutoloadClasses['CentralNoticeTestFixtures'] =
			__DIR__ . '/tests/CentralNoticeTestFixtures.php';

		$files[] = __DIR__ . '/tests/ApiCentralNoticeChoiceDataTest.php';
		$files[] = __DIR__ . '/tests/CentralNoticeTest.php';
		$files[] = __DIR__ . '/tests/AllocationCalculatorTest.php';
		$files[] = __DIR__ . '/tests/ChoiceDataProviderTest.php';
		$files[] = __DIR__ . '/tests/BannerTest.php';
		$files[] = __DIR__ . '/tests/CNChoiceDataResourceLoaderModuleTest.php';
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
	public static function onResourceLoaderTestModules( array &$testModules,
		ResourceLoader $resourceLoader
	) {
		global $wgResourceModules, $wgAutoloadClasses;

		// Set up test fixtures module, which is added as a dependency for all QUnit
		// tests.
		$testModules['qunit']['ext.centralNotice.testFixtures'] = [
				'class' => 'CNTestFixturesResourceLoaderModule'
		];

		// These classes are only used here or in phpunit tests
		$wgAutoloadClasses['CNTestFixturesResourceLoaderModule'] =
			__DIR__ . '/tests/CNTestFixturesResourceLoaderModule.php';
		// Note: the following setting is repeated in efCentralNoticeUnitTests()
		$wgAutoloadClasses['CentralNoticeTestFixtures'] =
			__DIR__ . '/tests/CentralNoticeTestFixtures.php';

		$testModuleBoilerplate = [
			'localBasePath' => __DIR__,
			'remoteExtPath' => 'CentralNotice',
		];

		// find test files for every RL module
		$prefix = 'ext.centralNotice';
		foreach ( $wgResourceModules as $key => $module ) {

			if ( substr( $key, 0, strlen( $prefix ) ) ===
				$prefix && isset( $module['scripts'] ) ) {

				$testFiles = [];

				foreach ( ( (array)$module['scripts'] ) as $script ) {

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
						[
							'dependencies' =>
								[ $key, 'ext.centralNotice.testFixtures' ],

							'scripts' => $testFiles,
						];
				}
			}
		}

		return true;
	}

	/**
	 * EventLoggingRegisterSchemas hook handler.
	 *
	 * @param array $schemas The schemas currently registered with the EventLogging
	 *  extension
	 * @return bool Always true
	 */
	public static function onEventLoggingRegisterSchemas( &$schemas ) {
		// Coordinate with makeEventLoggingURL() in
		// ext.centralNotice.bannerHistoryLogger.js
		$schemas['CentralNoticeBannerHistory'] = 14321636;
		return true;
	}
}
