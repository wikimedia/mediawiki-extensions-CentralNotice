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
		'centralnotice-add-new-banner-title',
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
$wgResourceModules[ 'ext.centralNotice.adminUi.campaignManager' ] = array(
	'localBasePath' => $dir . '/modules',
	'remoteExtPath' => 'CentralNotice/modules',
	'dependencies' => array(
		'ext.centralNotice.adminUi',
		'jquery.ui.dialog',
		'jquery.ui.slider',
	),
	'scripts'       => 'ext.centralNotice.adminUi.campaignManager/campaignManager.js',
	'styles'        => 'ext.centralNotice.adminUi.campaignManager/campaignManager.css',
	'messages'      => array( )
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

