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
		global $wgNoticeTestMode;
		$loader = $this->loaderScript();
		if( $wgNoticeTestMode ) {
			return $this->testCondition( $loader );
		} else {
			return $loader;
		}
	}
	
	function testCondition( $code ) {
		return
			'if(/[?&]sitenotice=yes/.test(document.location.search)){'.
			$code .
			'}';
	}
	
	function loaderScript() {
		global $wgNoticeText;
		$encUrl = htmlspecialchars( $wgNoticeText );
		$encEpoch = urlencode( $this->getEpoch() );
		return "document.writeln(" .
			Xml::encodeJsVar( "<script src=\"$encUrl/" ) .
			'+wgNoticeProject+"/"+wgNoticeLang+' .
			Xml::encodeJsVar( "?$encEpoch\"></script>" ).
			');';
	}
	
	function getEpoch() {
		global $wgNoticeEpoch;
		// Time when we should invalidate all notices...
		return wfTimestamp( TS_MW, $wgNoticeEpoch );
	}
}