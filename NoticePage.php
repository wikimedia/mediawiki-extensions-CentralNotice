<?php

class NoticePage extends UnlistedSpecialPage {
	function execute( $par ) {
		global $wgOut;
		$wgOut->disable();
		$this->sendHeaders();
		$content = $this->getOutput( $par );

		if ( strlen( $content ) == 0 ) {
			/* Hack for IE/Mac 0-length keepalive problem, see RawPage.php */
			echo "/* Empty */";
		} else {
			echo $content;
		}
	}

	protected function sharedMaxAge() {
		return 600;
	}

	protected function maxAge() {
		return 600;
	}

	protected function sendHeaders() {
		$smaxage = $this->sharedMaxAge();
		$maxage = $this->maxAge();
		// $epoch = wfTimestamp( TS_RFC2822, efCentralNoticeEpoch() );

		// Paranoia
		$public = ( session_id() == '' );

		header( "Content-type: text/javascript; charset=utf-8" );
		if ( $public ) {
			header( "Cache-Control: public, s-maxage=$smaxage, max-age=$maxage" );
		} else {
			header( "Cache-Control: private, s-maxage=0, max-age=$maxage" );
		}
		// header( "Last-modified: $epoch" );
	}

	function getOutput( $par ) {
		return "";
	}
}
