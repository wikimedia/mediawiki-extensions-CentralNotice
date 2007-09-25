<?php

// http://meta.wikimedia.org/wiki/Special:NoticeLoader
global $wgCentralNoticeLoader, $wgCentralNoticeText;
$wgCentralNoticeLoader = 'http://smorgasbord.local/trunk/index.php/Special:NoticeLoader';
$wgCentralNoticeText = 'http://smorgasbord.local/trunk/index.php/Special:NoticeText';

function wfCentralNotice( &$notice ) {
	global $wgCentralNoticeLoader;

	$encNoticeLoader = htmlspecialchars( $wgCentralNoticeLoader );
	
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

$wgSpecialPages['NoticeLoader'] = 'SpecialNoticeLoader';
$wgAutoloadClasses['SpecialNoticeLoader'] =
	dirname( __FILE__ ) . '/SpecialNoticeLoader.php';

$wgSpecialPages['NoticeText'] = 'SpecialNoticeText';
$wgAutoloadClasses['SpecialNoticeText'] =
	dirname( __FILE__ ) . '/SpecialNoticeText.php';

?>