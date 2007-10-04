<?php

class NoticePage extends SpecialPage {
	function execute( $par ) {
		global $wgOut;
		$wgOut->disable();
		$this->sendHeaders();
		echo $this->getJsOutput();
	}
	
	protected function sharedMaxAge() {
		return 86400;
	}
	
	protected function maxAge() {
		return 86400;
	}
	
	private function sendHeaders() {
		global $wgNoticeEpoch;
		$smaxage = $this->sharedMaxAge();
		$maxage = $this->maxAge();
		$epoch = wfTimestamp( TS_RFC2822, $wgNoticeEpoch );
		
		header( "Content-type: text/javascript; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$smaxage, max-age=$maxage" );
		header( "Last-modified: $epoch" );
	}
	
	function getJsOutput() {
		return "";
	}
}