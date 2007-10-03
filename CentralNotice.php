<?php

// http://meta.wikimedia.org/wiki/Special:NoticeLoader
$wgNoticeLoader = 'http://smorgasbord.local/trunk/index.php/Special:NoticeLoader';
$wgNoticeText = 'http://smorgasbord.local/trunk/index.php/Special:NoticeText';
$wgNoticeEpoch = '20071003015645';

function wfCentralNotice( &$notice ) {
	global $wgNoticeLoader;

	$encNoticeLoader = htmlspecialchars( $wgNoticeLoader );
	
	// Throw away the classic notice, use the central loader...
	$notice = <<<EOT
<script type="text/javascript">
var wgNotice = '';
var wgNoticeLang = 'en';
var wgNoticeProject = 'wikipedia';
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

?>