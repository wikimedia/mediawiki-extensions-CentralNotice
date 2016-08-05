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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CentralNotice' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CentralNotice'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles[ 'CentralNoticeAliases' ] = __DIR__ . '/CentralNotice.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for CentralNotice extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the CentralNotice extension requires MediaWiki 1.25+' );
}
