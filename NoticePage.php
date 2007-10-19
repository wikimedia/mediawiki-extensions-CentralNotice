<?php

class NoticePage extends UnlistedSpecialPage {
	function execute( $par ) {
		global $wgOut;
		$wgOut->disable();
		$this->sendHeaders();
		echo $this->getJsOutput( $par );
	}
	
	protected function sharedMaxAge() {
		return 86400;
	}
	
	protected function maxAge() {
		return 86400;
	}
	
	private function sendHeaders() {
		$smaxage = $this->sharedMaxAge();
		$maxage = $this->maxAge();
		$epoch = wfTimestamp( TS_RFC2822, efCentralNoticeEpoch() );
		
		// Paranoia
		$public = (session_id() == '');
		
		header( "Content-type: text/javascript; charset=utf-8" );
		if( $public ) {
			header( "Cache-Control: public, s-maxage=$smaxage, max-age=$maxage" );
		} else {
			header( "Cache-Control: private, s-maxage=0, max-age=$maxage" );
		}
		header( "Last-modified: $epoch" );
	}
	
	function getJsOutput( $par ) {
		return "";
	}
}