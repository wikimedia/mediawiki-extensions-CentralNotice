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
);

// Enable the notice-hosting infrastructure on this wiki...
// Leave at false for wikis that only use a sister site for the control.
// All remaining options apply only to the infrastructure wiki.
$wgNoticeInfrastructure = true;

// Enable the loader itself
// Allows to control the loader visibility, without destroying infrastructure
// for cached content
$wgCentralNoticeLoader = true;

// If true, notice only displays if 'sitenotice=yes' is in the query string
$wgNoticeTestMode = false;

// Array of '$lang.$project' for exceptions to the test mode rule
$wgNoticeEnabledSites = array();

// Client-side cache timeout for the loader JS stub.
// If 0, clients will (probably) rechceck it on every hit,
// which is good for testing.
$wgNoticeTimeout = 0;

// Server-side cache timeout for the loader JS stub.
// Should be big if you won't include the counter info in the text,
// smallish if you will. :)
$wgNoticeServerTimeout = 0;

// Source for live counter information
$wgNoticeCounterSource = "http://donate.wikimedia.org/counter.php";

$wgExtensionFunctions[] = 'efCentralNoticeSetup';

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'CentralNotice',
	'author'         => 'Brion Vibber',
	'url'            => 'http://www.mediawiki.org/wiki/Extension:CentralNotice',
	'descriptionmsg' => 'centralnotice-desc',
);

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['CentralNotice'] = $dir . 'CentralNotice.i18n.php';
$wgExtensionAliasesFiles['CentralNotice'] = $dir . 'CentralNotice.alias.php';

$wgAvailableRights[] = 'centralnotice-admin';
$wgAvailableRights[] = 'centralnotice-translate';
$wgGroupPermissions['sysop']['centralnotice-admin'] = true; // Only sysops can make change
$wgGroupPermissions['sysop']['centralnotice-translate'] = true; // Only sysops can make change

function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages;
	global $wgCentralNoticeLoader;

	$dir = dirname( __FILE__ ) . '/';

	if ( $wgCentralNoticeLoader ) {
		$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCentralNoticeSchema';
		$wgHooks['BeforePageDisplay'][] = 'efCentralNoticeLoader';
		$wgHooks['MakeGlobalVariablesScript'][] = 'efCentralNoticeDefaults';
		$wgHooks['SiteNoticeAfter'][] = 'efCentralNoticeDisplay';
	}
	
	$wgSpecialPages['BannerLoader'] = 'SpecialBannerLoader';
	$wgAutoloadClasses['SpecialBannerLoader'] = $dir . 'SpecialBannerLoader.php';
	
	$wgSpecialPages['BannerListLoader'] = 'SpecialBannerListLoader';
	$wgAutoloadClasses['SpecialBannerListLoader'] = $dir . 'SpecialBannerListLoader.php';
	
	$wgSpecialPages['BannerController'] = 'SpecialBannerController';
	$wgAutoloadClasses['SpecialBannerController'] = $dir . 'SpecialBannerController.php';

	if ( $wgNoticeInfrastructure ) {
		$wgSpecialPages['CentralNotice'] = 'CentralNotice';
		$wgSpecialPageGroups['CentralNotice'] = 'wiki'; // Wiki data and tools"
		$wgAutoloadClasses['CentralNotice'] = $dir . 'SpecialCentralNotice.php';
		
		$wgSpecialPages['NoticeTemplate'] = 'SpecialNoticeTemplate';
		$wgAutoloadClasses['SpecialNoticeTemplate'] = $dir . 'SpecialNoticeTemplate.php';
		
		// remove these as soon as banner loader is complete
		$wgSpecialPages['NoticeText'] = 'SpecialNoticeText';
		$wgAutoloadClasses['SpecialNoticeText'] = $dir . 'SpecialNoticeText.php';
		$wgAutoloadClasses['NoticePage'] = $dir . 'NoticePage.php';
		
		$wgAutoloadClasses['CentralNoticeDB'] = $dir . 'CentralNotice.db.php';
		$wgAutoloadClasses['TemplatePager'] = $dir . 'TemplatePager.php';
	}
}

function efCentralNoticeSchema() {
	global $wgDBtype, $wgExtNewTables, $wgExtNewFields;
	
	$base = dirname( __FILE__ );
	if ( $wgDBtype == 'mysql' ) {
		$wgExtNewTables[] = array( 'cn_notices', $base . '/CentralNotice.sql' );
		$wgExtNewFields[] = array( 'cn_notices', 'not_preferred', $base . '/patches/patch-notice_preferred.sql' );
		$wgExtNewTables[] = array( 'cn_notice_languages', $base . '/patches/patch-notice_languages.sql' );
		$wgExtNewFields[] = array( 'cn_templates', 'tmp_display_anon', $base . '/patches/patch-template_settings.sql' );
		$wgExtNewTables[] = array( 'cn_notice_geo', $base . '/patches/patch-notice_geo.sql' );
	}
	return true;
}

function efCentralNoticeLoader( $out, $skin ) {
	global $wgUser, $wgOut;

	$centralLoader = SpecialPage::getTitleFor( 'BannerController' )->getLocalUrl();

	// Insert the banner controller Javascript into the <head>
	$wgOut->addScriptFile( $centralLoader );

	return true;
}

function efCentralNoticeDefaults( &$vars ) {
	global $wgNoticeProject;
	// Initialize global Javascript variables. We initialize wgNotice to empty so if the notice
	// script fails we don't have any surprises.
	$vars['wgNotice'] = '';
	$vars['wgNoticeProject'] = $wgNoticeProject;
	return true;
}

function efCentralNoticeDisplay( &$notice ) {
	// Slip in loading of the banner (inside the siteNotice div)
	$notice =
		Html::inlineScript( "if (wgNotice != '') document.writeln(wgNotice);" ) .
		$notice;
	return true;
}
