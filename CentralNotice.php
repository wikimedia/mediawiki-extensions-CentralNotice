<?php

/// Override this URL to point to the central loader...
/// This guy gets loaded from every page on every wiki, and is heavily cached.
/// Its contents are small, and just load up another cached JS page, but this
/// allows us to update everything with a single purge. Nice, eh?
$wgNoticeLoader = 'http://smorgasbord.local/trunk/index.php/Special:NoticeLoader';

/// Override these per-wiki to pass on via the loader to the text system
/// for localization by language and project.
$wgNoticeLang = 'en';
$wgNoticeProject = 'wikipedia';


/// Enable the notice-hosting infrastructure on this wiki...
/// Leave at false for wikis that only use a sister site for the control.
/// All remaining options apply only to the infrastructure wiki.
$wgNoticeInfrastructure = false;

/// URL prefix to the raw-text loader special.
/// Project/language and timestamp epoch keys get appended to this
/// via the loader stub.
$wgNoticeText = 'http://smorgasbord.local/trunk/index.php/Special:NoticeText';

/// If true, notice only displays if 'sitenotice=yes' is in the query string
$wgNoticeTestMode = false;

/// Client-side cache timeout for the loader JS stub.
/// If 0, clients will (probably) rechceck it on every hit,
/// which is good for testing.
$wgNoticeTimeout = 0;

/// Use a god-forsaken <marquee> to scroll multiple quotes...
$wgNoticeScroll = true;

$wgExtensionFunctions[] = 'efCentralNoticeSetup';

function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure;
	$wgHooks['SiteNoticeAfter'][] = 'efCentralNoticeLoader';
	
	if( $wgNoticeInfrastructure ) {
		global $wgAutoloadClasses, $wgSpecialPages;
		
		$wgHooks['ArticleSaveComplete'][] = 'efCentralNoticeSaveHook';
		$wgHooks['ArticleSaveComplete'][] = 'efCentralNoticeDeleteHook';

		$wgAutoloadClasses['NoticePage'] =
			dirname( __FILE__ ) . '/NoticePage.php';

		$wgSpecialPages['NoticeLoader'] = 'SpecialNoticeLoader';
		$wgAutoloadClasses['SpecialNoticeLoader'] =
			dirname( __FILE__ ) . '/SpecialNoticeLoader.php';

		$wgSpecialPages['NoticeText'] = 'SpecialNoticeText';
		$wgAutoloadClasses['SpecialNoticeText'] =
			dirname( __FILE__ ) . '/SpecialNoticeText.php';
	}
}


function efCentralNoticeLoader( &$notice ) {
	global $wgNoticeLoader, $wgNoticeLang, $wgNoticeProject;

	$encNoticeLoader = htmlspecialchars( $wgNoticeLoader );
	$encProject = Xml::encodeJsVar( $wgNoticeProject );
	$encLang = Xml::encodeJsVar( $wgNoticeLang );
	
	// Throw away the classic notice, use the central loader...
	$notice = <<<EOT
<script type="text/javascript">
var wgNotice = "";
var wgNoticeLang = $encLang;
var wgNoticeProject = $encProject;
</script>
<script type="text/javascript" src="$encNoticeLoader"></script>
<script type="text/javascript">
if (wgNotice != "") {
  document.writeln(wgNotice);
}
</script>
EOT;
	
	return true;
}

/**
 * 'ArticleSaveComplete' hook
 * Trigger a purge of the notice loader when we've updated the source pages.
 */
function efCentralNoticeSaveHook( $article, $user, $text, $summary, $isMinor,
                                $isWatch, $section, $flags, $revision ) {
	efCentralNoticeMaybePurge( $article->getTitle() );
	return true; // Continue hook processing
}

/**
 * 'ArticleDeleteComplete' hook
 * Trigger a purge of the notice loader if this removed one of the source pages.
 */
function efCentralNoticeDeleteHook( $article, $user, $reason ) {
	efCentralNoticeMaybePurge( $article->getTitle() );
	return true; // Continue hook processing
}

/**
 * Purge the notice loader if the given page would affect notice display.
 */
function efCentralNoticeMaybePurge( $title ) {
	if( $title->getNamespace() == NS_MEDIAWIKI &&
		substr( $title->getText(), 0, 14 ) == 'Centralnotice-' ) {
		efCentralNoticePurge();
	}
}

/**
 * Purge the notice loader, triggering a refresh in all clients
 * once $wgNoticeTimeout has expired.
 */
function efCentralNoticePurge() {
	global $wgNoticeLoader;
	
	// Update the notice epoch...
	efCentralNoticeUpdateEpoch();
	
	// Purge the central loader URL...
	$u = new SquidUpdate( array( $wgNoticeLoader ) );
	$u->doUpdate();
}

/**
 * Return a nice little epoch that gives the last time we updated
 * something in the notice...
 * @return string timestamp
 */
function efCentralNoticeEpoch() {
	global $wgMemc;
	$epoch = $wgMemc->get( 'centralnotice-epoch' );
	if( $epoch ) {
		return wfTimestamp( TS_MW, $epoch );
	} else {
		return efCentralNoticeUpdateEpoch();
	}
}

/**
 * Update the epoch.
 * @return string timestamp
 */
function efCentralNoticeUpdateEpoch() {
	global $wgMemc;
	$epoch = wfTimestamp( TS_MW );
	$wgMemc->set( "centralnotice-epoch", $epoch, 86400 );
	return $epoch;
}
