<?php
/**
 * ResourceLoader module definitions
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

global $wgResourceModules;
$dir = __DIR__;

// Register ResourceLoader modules

$wgResourceModules[ 'jquery.ui.multiselect' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'dependencies'  => array(
		'jquery.ui.core',
		'jquery.ui.sortable',
		'jquery.ui.draggable',
		'jquery.ui.droppable',
		'mediawiki.jqueryMsg'
	),
	'scripts'       => 'vendor/jquery.ui.multiselect/ui.multiselect.js',
	'styles'        => 'vendor/jquery.ui.multiselect/ui.multiselect.css',
	'position'      => 'top',
);

$wgResourceModules[ 'ext.centralNotice.adminUi' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'dependencies' => array(
		'jquery.ui.datepicker',
		'jquery.ui.multiselect'
	),
	'scripts'       => 'infrastructure/centralnotice.js',
	'styles'        => array(
		'infrastructure/centralnotice.css',
		'infrastructure/adminui.common.css'
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
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog',
		'mediawiki.Uri'
	),
	'scripts'       => 'infrastructure/bannermanager.js',
	'styles'        => 'infrastructure/bannermanager.css',
	'messages'      => array(
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
		'centralnotice-delete-banner-cancel',
	)
);

$wgResourceModules[ 'ext.centralNotice.adminUi.bannerEditor' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog'
	),
	'scripts'       => 'infrastructure/bannereditor.js',
	'styles'        => 'infrastructure/bannereditor.css',
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

$wgResourceModules[ 'ext.centralNotice.adminUi.campaignManager' ] = array(
	'localBasePath' => $dir,
	'remoteExtPath' => 'CentralNotice',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog',
		'jquery.ui.slider',
		'mediawiki.template.mustache',
	),
	'scripts'       => 'resources/infrastructure/campaignManager.js',
	'styles'        => 'resources/infrastructure/campaignManager.css',
	'templates'     => array(
		'campaignMixinParamControls.mustache' => 'templates/campaignMixinParamControls.mustache'
	),
	'messages'      => array(
		'centralnotice-notice-mixins-int-required',
		'centralnotice-notice-mixins-float-required',

		// Messages used for campaign mixin parameter labels (labelMsg).
		// See CentralNotice.php.
		'centralnotice-banner-history-logger-rate',
		'centralnotice-banner-history-logger-max-entry-age',
		'centralnotice-banner-history-logger-max-entries'
	)
);

$wgResourceModules[ 'ext.centralNotice.startUp' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'scripts'       => 'subscribing/ext.centralNotice.startUp.js',
	'dependencies'  => array(
		'ext.centralNotice.choiceData',
		'mediawiki.util',
	),
	'targets'       => array( 'desktop', 'mobile' ),
);

$wgResourceModules[ 'ext.centralNotice.geoIP' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'scripts'       => 'subscribing/ext.centralNotice.geoIP.js',
	'targets'       => array( 'desktop', 'mobile' ),
	'dependencies'  => array(
		'jquery.cookie',
	),
);

$wgResourceModules[ 'ext.centralNotice.choiceData' ] = array(
	// This module's dependencies are set dynamically based on context.
	// The following settings are also brought in via the PHP class:
	// 'targets' => array( 'desktop', 'mobile' )
	'class'         => 'CNChoiceDataResourceLoaderModule'
);

$wgResourceModules[ 'ext.centralNotice.display' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'scripts'       => array(
		'subscribing/ext.centralNotice.display.js',
		'subscribing/ext.centralNotice.display.state.js',
		'subscribing/ext.centralNotice.display.chooser.js',
		'subscribing/ext.centralNotice.display.bucketer.js',
		'subscribing/ext.centralNotice.display.hide.js',
	),
	'styles'        => 'subscribing/ext.centralNotice.display.css',
	'dependencies'  => array(
		'ext.centralNotice.geoIP',
		'jquery.cookie',
		'json',
		'mediawiki.Uri',
	),
	'targets'       => array( 'desktop', 'mobile' ),
);

$wgResourceModules[ 'ext.centralNotice.kvStore' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'scripts'       => 'subscribing/ext.centralNotice.kvStore.js',
	'targets'       => array( 'desktop', 'mobile' ),
);

// Deprecated, left here for cached HTML. Just brings in startUp and geoIP.
$wgResourceModules[ 'ext.centralNotice.bannerController' ] = array(
	'position'      => 'top',
	'dependencies'  => array(
		'ext.centralNotice.startUp',
		'ext.centralNotice.geoIP'
	),
	'targets' => array( 'desktop' ),
);

// Deprecated, left here for cached HTML. Just brings in startUp and geoIP.
$wgResourceModules[ 'ext.centralNotice.bannerController.mobile' ] = array(
	'position'      => 'top',
	'dependencies'  => array(
		'ext.centralNotice.startUp',
		'ext.centralNotice.geoIP'
	),
	'targets' => array( 'mobile' ),
);

// Deprecated, left here for cached HTML.
$wgResourceModules[ 'ext.centralNotice.bannerController.mobiledevice' ] = array();

// Deprecated, left here for cached HTML
$wgResourceModules[ 'ext.centralNotice.bannerChoiceData' ] = array();

// Deprecated, left here for cached HTML
$wgResourceModules[ 'ext.centralNotice.bannerController.lib' ] = array();

// This mixin module is just to make campaign mixins smoke testable. It'll be
// removed before merging the campaign_mixnis feature branch to master.
$wgResourceModules[ 'ext.centralNotice.bannerHistoryLogger' ] = array(
	'localBasePath' => $dir . '/resources',
	'remoteExtPath' => 'CentralNotice/resources',
	'scripts'       => 'subscribing/ext.centralNotice.bannerHistoryLogger.js',
	// campaign mixin modules need this dependency, to be sure it is loaded first
	'dependencies'  => array(
		'ext.centralNotice.kvStore',
		'mediawiki.Uri',
		// Mixins must depend on display to ensure the hook they use to
		// register themselves is available when they run
		'ext.centralNotice.display',
	)
);
