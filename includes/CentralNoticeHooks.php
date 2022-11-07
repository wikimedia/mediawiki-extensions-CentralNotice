<?php

use MediaWiki\ResourceLoader\ResourceLoader;

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
			$wgAvailableRights, $wgGroupPermissions, $wgCentralDBname,
			$wgDBname, $wgCentralNoticeAdminGroup,
			$wgCentralNoticeMessageProtectRight, $wgResourceModules,
			$wgDefaultUserOptions;

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

		// Set default user preferences for campaign type filtering.
		// All types are on by default.
		foreach ( CampaignType::getTypes() as $type ) {
			$wgDefaultUserOptions[ $type->getPreferenceKey() ] = 1;
		}

		// If this is the wiki that hosts the management interface, load further components
		if ( $wgNoticeInfrastructure ) {
			if ( $wgNoticeUseTranslateExtension ) {
				$wgHooks['TranslatePostInitGroups'][] = 'BannerMessageGroup::registerGroupHook';
				$wgHooks['TranslateEventMessageGroupStateChange'][] =
					'BannerMessageGroup::updateBannerGroupStateHook';
			}

			$wgSpecialPages['CentralNotice'] = CentralNotice::class;
			$wgSpecialPages['NoticeTemplate'] = SpecialNoticeTemplate::class;
			$wgSpecialPages['BannerAllocation'] = SpecialBannerAllocation::class;
			$wgSpecialPages['CentralNoticeLogs'] = SpecialCentralNoticeLogs::class;
			$wgSpecialPages['CentralNoticeBanners'] = SpecialCentralNoticeBanners::class;

			$moduleTemplate = [
				'localBasePath' => dirname( __DIR__ ) . '/resources',
				'remoteExtPath' => 'CentralNotice/resources',
			];
			$wgResourceModules += [
				'ext.centralNotice.adminUi' => $moduleTemplate + [
					'dependencies' => [
						'jquery.ui',
						'mediawiki.jqueryMsg',
						'mediawiki.util',
						'mediawiki.Uri'
					],
					'scripts' => [
						'vendor/jquery.ui.multiselect/ui.multiselect.js',
						'vendor/jquery.jstree/jstree.js',
						'infrastructure/centralnotice.js',
					],
					'styles' => [
						'vendor/jquery.ui.multiselect/ui.multiselect.css',
						'vendor/jquery.jstree/themes/default/style.css',
						'infrastructure/ext.centralNotice.adminUi.css'
					],
					'messages' => [
						'centralnotice-documentwrite-error',
						'centralnotice-close-title',
						'centralnotice-select-all',
						'centralnotice-remove-all',
						'centralnotice-items-selected',
						'centralnotice-geo-status'
					]
				],
				'ext.centralNotice.adminUi.campaignPager' => $moduleTemplate + [
					'scripts' => 'infrastructure/ext.centralNotice.adminUi.campaignPager.js',
					'styles' => 'infrastructure/ext.centralNotice.adminUi.campaignPager.css'
				],
				'ext.centralNotice.adminUi.bannerManager' => $moduleTemplate + [
					'dependencies' => [
						'ext.centralNotice.adminUi',
						'jquery.ui',
						'mediawiki.Uri'
					],
					'scripts' => 'infrastructure/bannermanager.js',
					'styles' => 'infrastructure/bannermanager.css',
					'messages' => [
						'centralnotice-add-notice-button',
						'centralnotice-add-notice-cancel-button',
						'centralnotice-archive-banner',
						'centralnotice-archive-banner-title',
						'centralnotice-archive-banner-confirm',
						'centralnotice-archive-banner-cancel',
						'centralnotice-add-new-banner-title',
						'centralnotice-delete-banner',
						'centralnotice-delete-banner-title',
						'centralnotice-delete-banner-confirm',
						'centralnotice-delete-banner-cancel'
					]
				],
				'ext.centralNotice.adminUi.bannerEditor' => $moduleTemplate + [
					'dependencies' => [
						'ext.centralNotice.adminUi',
						'jquery.ui',
						'ext.centralNotice.kvStore',
						'mediawiki.api',
						'mediawiki.Uri',
						'mediawiki.Title',
						'mediawiki.user',
					],
					'scripts' => 'infrastructure/bannereditor.js',
					'styles' => 'infrastructure/bannereditor.css',
					'messages' => [
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
						'centralnotice-banner-cdn-dialog-waiting-text',
						'centralnotice-banner-cdn-dialog-title',
						'centralnotice-banner-cdn-dialog-ok',
						'centralnotice-banner-cdn-dialog-error',
						'centralnotice-banner-cdn-dialog-success',
						'centralnotice-fieldset-preview',
						'centralnotice-preview-page',
						'centralnotice-update-preview',
						'centralnotice-preview-loader-error-dialog-title',
						'centralnotice-preview-loader-error-dialog-ok'
					]
				],
				'ext.centralNotice.adminUi.campaignManager' => [
					'localBasePath' => dirname( __DIR__ ),
					'remoteExtPath' => 'CentralNotice',
					'dependencies' => [
						'ext.centralNotice.adminUi',
						'oojs-ui',
						'mediawiki.template',
						'mediawiki.template.mustache'
					],
					'scripts' => 'resources/infrastructure/campaignManager.js',
					'styles' => 'resources/infrastructure/campaignManager.css',
					'templates' => [
						'campaignMixinParamControls.mustache' => 'templates/campaignMixinParamControls.mustache'
					],
					'messages' => [
						'centralnotice-notice-mixins-int-required',
						'centralnotice-notice-mixins-float-required',
						'centralnotice-notice-mixins-out-of-bound',
						'centralnotice-banner-history-logger-rate',
						'centralnotice-banner-history-logger-rate-help',
						'centralnotice-banner-history-logger-max-entry-age',
						'centralnotice-banner-history-logger-max-entry-age-help',
						'centralnotice-banner-history-logger-max-entries',
						'centralnotice-banner-history-logger-max-entries-help',
						'centralnotice-banner-history-logger-wait-log-no-send-beacon',
						'centralnotice-banner-history-logger-wait-log-no-send-beacon-help',
						'centralnotice-set-record-impression-sample-rate',
						'centralnotice-custom-record-impression-sample-rate',
						'centralnotice-banners-not-guaranteed-to-display',
						'centralnotice-impression-diet-identifier',
						'centralnotice-impression-diet-identifier-help',
						'centralnotice-impression-diet-maximum-seen',
						'centralnotice-impression-diet-maximum-seen-help',
						'centralnotice-impression-diet-restart-cycle-delay',
						'centralnotice-impression-diet-restart-cycle-delay-help',
						'centralnotice-impression-diet-skip-initial',
						'centralnotice-impression-diet-skip-initial-help',
						'centralnotice-large-banner-limit-days',
						'centralnotice-large-banner-limit-days-help',
						'centralnotice-large-banner-limit-randomize',
						'centralnotice-large-banner-limit-randomize-help',
						'centralnotice-large-banner-limit-identifier',
						'centralnotice-large-banner-limit-identifier-help',
						'centralnotice-impression-events-sample-rate',
						'centralnotice-impression-events-sample-rate-help',
						'centralnotice-impression-events-sample-rate-field'
					]
				],
				'ext.centralNotice.adminUi.bannerSequence' => $moduleTemplate + [
					'scripts' => 'infrastructure/ext.centralNotice.adminUi.bannerSequence.js',
					'styles' => 'infrastructure/ext.centralNotice.adminUi.bannerSequence.less',
					'dependencies' => [
						'ext.centralNotice.adminUi.campaignManager',
						'oojs-ui',
						'oojs-ui.styles.icons-moderation',
						'mediawiki.jqueryMsg'
					],
					'messages' => [
						'centralnotice-banner-sequence',
						'centralnotice-banner-sequence-days',
						'centralnotice-banner-sequence-days-error',
						'centralnotice-banner-sequence-days-help',
						'centralnotice-banner-sequence-help',
						'centralnotice-banner-sequence-bucket-seq',
						'centralnotice-banner-sequence-bucket-add-step',
						'centralnotice-banner-sequence-banner',
						'centralnotice-banner-sequence-page-views',
						'centralnotice-banner-sequence-skip-with-id',
						'centralnotice-banner-sequence-page-views-error',
						'centralnotice-banner-sequence-skip-with-id-error',
						'centralnotice-banner-sequence-banner-removed-error',
						'centralnotice-banner-sequence-no-banner',
						'centralnotice-banner-sequence-detailed-help'
					]
				],
			];

			// Register user rights for editing
			$wgAvailableRights[] = 'centralnotice-admin';

			if ( $wgCentralNoticeAdminGroup ) {
				// Grant admin permissions to this group
				$wgGroupPermissions[$wgCentralNoticeAdminGroup]['centralnotice-admin'] = true;
			}

			if ( !in_array( $wgCentralNoticeMessageProtectRight, $wgAvailableRights ) ) {
				$wgAvailableRights[] = $wgCentralNoticeMessageProtectRight;
			}
			self::addCascadingRestrictionRight( $wgCentralNoticeMessageProtectRight );
			self::addCascadingRestrictionRight( 'centralnotice-admin' );
		}
	}

	protected static function addCascadingRestrictionRight( $right ) {
		global $wgCascadingRestrictionLevels, $wgRestrictionLevels;
		if ( !in_array( $right, $wgRestrictionLevels ) ) {
			$wgRestrictionLevels[] = $right;
		}
		if ( !in_array( $right, $wgCascadingRestrictionLevels ) ) {
			$wgCascadingRestrictionLevels[] = $right;
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

		if ( !$wgCentralSelectedMobileBannerDispatcher &&
			ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' )
		) {
			$wgCentralSelectedMobileBannerDispatcher = $wgCentralSelectedBannerDispatcher;
		}
	}

	/**
	 * Tell the UserMerge extension where we store user ids
	 * @param array[] &$updateFields
	 * @return true
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
	 * @param array &$namespaces Modifiable list of namespaces -- similar to $wgExtraNamespaces
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
	 * This function adds the startUp and geoIP modules to the page as needed,
	 * and if there is a forced banner preview, add CSP headers and violation
	 * reporting javascript.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( $out, $skin ) {
		global $wgCentralHost, $wgServer, $wgCentralNoticeContentSecurityPolicy,
			$wgCentralNoticeESITestString;

		// Add ESI test string (see T308799)
		// It is expected that only HTML comments in the form of '<!--esi ...' will be
		// injected here.
		// TODO Remove this once ESI tests are complete.
		if ( $wgCentralNoticeESITestString ) {
			$out->addHTML( $wgCentralNoticeESITestString );
		}

		// Always add geoIP
		// TODO Separate geoIP from CentralNotice
		$out->addModules( 'ext.centralNotice.geoIP' );

		$request = $skin->getRequest();
		// If we're on a special page, editing, viewing history or a diff, bow out now
		// This is to reduce chance of bad misclicks from delayed banner loading
		if ( $out->getTitle()->inNamespace( NS_SPECIAL ) ||
			( $request->getText( 'action' ) === 'edit' ) ||
			( $request->getText( 'action' ) === 'history' ) ||
			$request->getCheck( 'diff' )
		) {
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

		// FIXME: as soon as I80f6f469ba4c0b60 is available in core, get rid
		// of $wgCentralNoticeContentSecurityPolicy and use their stuff.
		if (
			$wgCentralNoticeContentSecurityPolicy &&
			$request->getVal( 'banner' )
		) {
			$request->response()->header(
				"content-security-policy: $wgCentralNoticeContentSecurityPolicy"
			);
			$out->addModules( 'ext.centralNotice.cspViolationAlert' );
		}
		return true;
	}

	/**
	 * MakeGlobalVariablesScript hook handler
	 * This function sets the pseudo-global JavaScript variables that are used by CentralNotice
	 *
	 * @param array &$vars
	 * @param OutputPage $out
	 * @return bool
	 */
	public static function onMakeGlobalVariablesScript( &$vars, $out ) {
		global $wgNoticeProject, $wgCentralNoticeGeoIPBackgroundLookupModule;

		// FIXME Is this no longer used anywhere in JS following the switch to
		// client-side banner selection? If so, remove it.
		$vars[ 'wgNoticeProject' ] = $wgNoticeProject;

		// No need to provide this variable if it's null, because mw.config.get()
		// will return null if it's not there.
		if ( $wgCentralNoticeGeoIPBackgroundLookupModule ) {
			$vars[ 'wgCentralNoticeGeoIPBackgroundLookupModule' ] =
				$wgCentralNoticeGeoIPBackgroundLookupModule;
		}

		// Output the user's registration date, total edit count, and past year's edit count.
		// This is useful for banners that need to be targeted to specific types of users.
		// Only do this for logged-in users, keeping anonymous user output equal (for Squid-cache).
		$user = $out->getUser();
		if ( $user->isRegistered() ) {
			if ( $user->isBot() ) {
				$userData = false;
			} else {
				$userData = [
					// Add the user's registration date (TS_MW)
					'registration' => $user->getRegistration() ?: 0
				];
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
	 * @param string &$notice
	 * @return bool
	 */
	public static function onSiteNoticeAfter( &$notice ) {
		// TODO Legacy comment below, likely inaccurate; check and fix
		// Ensure that the div including #siteNotice is actually included
		$notice = "<!-- CentralNotice -->$notice";

		return true;
	}

	/**
	 * ResourceLoaderGetConfigVars hook handler
	 * Send php config vars to js via ResourceLoader
	 *
	 * @param array &$vars variables to be added to the output of the startup module
	 * @return bool
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgNoticeInfrastructure, $wgCentralBannerRecorder,
			$wgNoticeNumberOfBuckets, $wgNoticeBucketExpiry,
			$wgNoticeNumberOfControllerBuckets, $wgNoticeCookieDurations,
			$wgNoticeHideUrls, $wgCentralNoticeSampleRate,
			$wgCentralNoticeImpressionEventSampleRate,
			$wgCentralSelectedBannerDispatcher, $wgCentralSelectedMobileBannerDispatcher,
			$wgCentralNoticePerCampaignBucketExtension, $wgCentralNoticeCampaignMixins,
			$wgCentralNoticeMaxCampaignFallback;

		// TODO Check if the following comment still applies
		// Making these calls too soon will causes issues with the namespace localisation cache.
		// This seems to be just right. We require them at all because MW will 302 page requests
		// made to non localised namespaces which results in wasteful extra calls.

		// Set infrastructure URL variables, which change between mobile/desktop
		if ( class_exists( MobileContext::class ) ) {
			$mc = MobileContext::singleton();
			$displayMobile = $mc->shouldDisplayMobileView();
		} else {
			$displayMobile = false;
		}

		if ( $displayMobile ) {
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
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

		$vars[ 'wgCentralNoticeImpressionEventSampleRate' ] =
			$wgCentralNoticeImpressionEventSampleRate;

		$vars[ 'wgNoticeNumberOfBuckets' ] = $wgNoticeNumberOfBuckets;
		$vars[ 'wgNoticeBucketExpiry' ] = $wgNoticeBucketExpiry;
		$vars[ 'wgNoticeNumberOfControllerBuckets' ] = $wgNoticeNumberOfControllerBuckets;
		$vars[ 'wgNoticeCookieDurations' ] = $wgNoticeCookieDurations;
		$vars[ 'wgNoticeHideUrls' ] = $wgNoticeHideUrls;
		$vars[ 'wgCentralNoticeMaxCampaignFallback' ] = $wgCentralNoticeMaxCampaignFallback;

		$vars[ 'wgCentralNoticePerCampaignBucketExtension' ] =
			$wgCentralNoticePerCampaignBucketExtension;

		if ( $wgNoticeInfrastructure ) {
			// Add campaign mixin defs for use in admin interface
			$vars[ 'wgCentralNoticeCampaignMixins' ] = $wgCentralNoticeCampaignMixins;
		}
		return true;
	}

	/**
	 * Conditionally register resource loader modules.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		global $wgEnableJavaScriptTest, $wgAutoloadClasses;

		if ( $wgEnableJavaScriptTest ) {
			// These classes are only used here or in phpunit tests
			$wgAutoloadClasses['CNTestFixturesResourceLoaderModule'] =
				dirname( __DIR__ ) . '/tests/phpunit/CNTestFixturesResourceLoaderModule.php';
			$wgAutoloadClasses['CentralNoticeTestFixtures'] =
				dirname( __DIR__ ) . '/tests/phpunit/CentralNoticeTestFixtures.php';

			// Set up test fixtures module, which is added as a dependency for all QUnit
			// tests.
			$resourceLoader->register( 'ext.centralNotice.testFixtures', [
				'class' => 'CNTestFixturesResourceLoaderModule'
			] );
		}
	}

	/**
	 * Add tags defined by this extension to list of defined and active tags.
	 *
	 * @param array &$tags List of defined or active tags
	 */
	public static function onListDefinedTags( &$tags ) {
		$tags[] = 'centralnotice';
		$tags[] = 'centralnotice translation';
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		// Explanatory text
		$preferences['centralnotice-intro'] = [
			'type' => 'info',
			'default' => wfMessage( 'centralnotice-user-prefs-intro' )->parseAsBlock(),
			'section' => 'centralnotice-banners',
			'raw' => true,
		];

		foreach ( CampaignType::getTypes() as $type ) {
			// This allows fallback languages while also showing something not-too-
			// horrible if the config variable has types that don't have i18n
			// messages.
			// Note also that the value of 'label' will escaped prior to output.
			$message = Message::newFromKey( $type->getMessageKey() );
			$label = $message->exists() ? $message->text() : $type->getId();

			$preferences[ $type->getPreferenceKey() ] = [
				'type' => 'toggle',
				'section' => 'centralnotice-banners/centralnotice-display-banner-types',
				'label' => $label,
				'disabled' => $type->getOnForAll()
			];
		}

		return true;
	}

	/**
	 * Add icon for Special:Preferences mobile layout
	 *
	 * @param array &$iconNames Array of icon names for their respective sections.
	 */
	public static function onPreferencesGetIcon( &$iconNames ) {
		$iconNames[ 'centralnotice-banners' ] = 'feedback';
	}
}
