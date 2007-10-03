<?php

/**
 * The notice loader is a central point of contact; a single consistent
 * URL used for the cluster, in all language and project versions.
 *
 * That central URL can be heavily cached, and centrally purged when
 * updates do happen.
 *
 * It loads up a second page (Special:NoticeText) with specific project
 * and language options and a version timestamp for clean cache breaking.
 */
class SpecialNoticeLoader extends NoticePage {
	function __construct() {
		parent::__construct("NoticeLoader");
	}

	/**
	 * Clients should recheck this fairly often, but not _constantly_.
	 * 10 minutes?
	 */
	protected function maxAge() {
		return 0;
	}
	
	function getJsOutput() {
		global $wgNoticeText;
		$encUrl = Xml::escapeJsString( $wgNoticeText );
		$encEpoch = Xml::escapeJsString( $this->getEpoch() );
		return <<<EOT
document.writeln("<"+"script src=\"$encUrl/"+wgNoticeProject+"/"+wgNoticeLang+"?$encEpoch"+"\"><"+"/script>");
EOT;
	}
	
	function getEpoch() {
		global $wgNoticeEpoch;
		// Time when we should invalidate all notices...
		return wfTimestamp( TS_MW, $wgNoticeEpoch );
	}
}

?>