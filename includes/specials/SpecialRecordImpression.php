<?php
/**
 * Null endpoint.  This is a workaround to simplify analytics.
 */
class SpecialRecordImpression extends UnlistedSpecialPage {
	// Cache this blank response for a day or so (60 * 60 * 24 s.)
	public const CACHE_EXPIRY = 86400;

	public function __construct() {
		// Register special page
		parent::__construct( "RecordImpression" );
	}

	public function execute( $par ) {
		$this->getOutput()->disable();

		$this->sendHeaders();

		// Output nothing else.
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	private function sendHeaders() {
		$expiry = static::CACHE_EXPIRY;
		header( "Content-Type: image/png" );

		// If we have a logged in user; do not cache (default for special pages)
		// lest we capture a set-cookie header. Otherwise cache so we don't have
		// too big of a DDoS hole.
		if ( !$this->getUser()->isRegistered() ) {
			header( "Cache-Control: public, s-maxage={$expiry}, max-age=0" );
		}
	}
}
