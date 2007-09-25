<?php

class SpecialNoticeLoader extends SpecialPage {
	function __construct() {
		parent::__construct("NoticeLoader");
	}
	
	function execute( $par ) {
		global $wgOut;
		$wgOut->disable();
		
		echo $this->getJsOutput();
	}
	
	function getJsOutput() {
		global $wgCentralNoticeText;
		$encUrl = Xml::escapeJsString( $wgCentralNoticeText );
		$encEpoch = Xml::escapeJsString( $this->getEpoch() );
		return <<<EOT
document.writeln("<"+"script src=\"$encUrl/"+wgNoticeProject+"/"+wgNoticeLang+"?$encEpoch"+"\"><"+"/script>");
EOT;
	}
	
	function getEpoch() {
		// Time when we should invalidate all notices...
		return wfTimestamp( TS_MW );
	}
}

?>