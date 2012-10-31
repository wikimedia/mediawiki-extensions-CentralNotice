<?php
/**
 * Null endpoint.  This is a workaround to simplify analytics.
 */
class SpecialRecordImpression extends UnlistedSpecialPage {
	// Cache this blank response for a day or so (60 * 60 * 24 s.)
	const CACHE_EXPIRY = 86400;

	function __construct() {
		// Register special page
		parent::__construct( "RecordImpression" );
	}

	function execute( $par ) {
		$this->getOutput()->disable();

		$this->sendHeaders();

		// Output nothing else.
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	function sendHeaders() {
		$expiry = static::CACHE_EXPIRY;
		header( "Content-Type: image/png" );
		header( "Cache-Control: public, s-maxage={$expiry}, max-age=0" );
	}
}
