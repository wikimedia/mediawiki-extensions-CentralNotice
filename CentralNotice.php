<?php

/// If true, notice only displays if 'sitenotice=yes' is in the query string
$wgNoticeTestMode = false;

/// Client-side cache timeout for the loader JS stub.
/// If 0, clients will (probably) rechceck it on every hit,
/// which is good for testing.
$wgNoticeTimeout = 0;

// http://meta.wikimedia.org/wiki/Special:NoticeLoader
$wgNoticeLoader = 'http://smorgasbord.local/trunk/index.php/Special:NoticeLoader';
$wgNoticeText = 'http://smorgasbord.local/trunk/index.php/Special:NoticeText';
//$wgNoticeEpoch = '20071003183510';
$wgNoticeEpoch = gmdate( 'YmdHis', @filemtime( dirname( __FILE__ ) . '/SpecialNoticeText.php' ) );

$wgNoticeLang = 'en';
$wgNoticeProject = 'wikipedia';

function wfCentralNotice( &$notice ) {
	global $wgNoticeLoader, $wgNoticeLang, $wgNoticeProject;

	$encNoticeLoader = htmlspecialchars( $wgNoticeLoader );
	$encProject = htmlspecialchars( $wgNoticeProject );
	$encLang = htmlspecialchars( $wgNoticeLang );
	
	// Throw away the classic notice, use the central loader...
	$notice = <<<EOT
<script type="text/javascript">
var wgNotice = '';
var wgNoticeLang = '$encLang';
var wgNoticeProject = '$encProject';
</script>
<script type="text/javascript" src="$encNoticeLoader"></script>
<script type="text/javascript">
if (wgNotice != '') {
  document.writeln(wgNotice);
}
</script>
EOT;
	
	return true;
}

$wgHooks['SiteNoticeAfter'][] = 'wfCentralNotice';

$wgAutoloadClasses['NoticePage'] =
	dirname( __FILE__ ) . '/NoticePage.php';

$wgSpecialPages['NoticeLoader'] = 'SpecialNoticeLoader';
$wgAutoloadClasses['SpecialNoticeLoader'] =
	dirname( __FILE__ ) . '/SpecialNoticeLoader.php';

$wgSpecialPages['NoticeText'] = 'SpecialNoticeText';
$wgAutoloadClasses['SpecialNoticeText'] =
	dirname( __FILE__ ) . '/SpecialNoticeText.php';