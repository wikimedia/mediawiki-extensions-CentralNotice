<?php

class SpecialNoticeText extends SpecialPage {
	function __construct() {
		parent::__construct( "NoticeText" );
	}
	
	function execute( $par ) {
		global $wgOut;
		$wgOut->disable();
		
		echo $this->getJsOutput();
	}
	
	function getJsOutput() {
		global $wgSiteNotice;
		$encNotice = Xml::escapeJsString( $wgSiteNotice );
		return <<<EOT
wgNotice = "$encNotice";
EOT;
	}
}

?>