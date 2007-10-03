<?php

class SpecialNoticeText extends NoticePage {
	function __construct() {
		parent::__construct( "NoticeText" );
	}
	
	/**
	 * Clients can cache this as long as they like -- if it changes,
	 * we'll be bumping things at the loader level, bringing a new URL.
	 *
	 * Let's say a week.
	 */
	protected function maxAge() {
		return 86400 * 7;
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