<?php
/**
 * Null endpoint.  This is a workaround to simplify analytics.
 */
class SpecialCNReporter extends UnlistedSpecialPage {
	function __construct() {
		// Register special page
		parent::__construct( "CNReporter" );
	}

	function execute( $par ) {
		global $wgNoticeReporterDomains;

		$this->getOutput()->disable();

		$this->sendHeaders();

		$script = <<<EOT
<html><body><script type="text/javascript">
	var i,k,v,cookieJar = document.cookie.split(";");
	var data = {};

	for (i = 0; i < cookieJar.length; i++) {
		k = cookieJar[i].substr(0, cookieJar[i].indexOf("="));
		v = cookieJar[i].substr(cookieJar[i].indexOf("=") + 1);

		if (k.indexOf("centralnotice") !== -1) {
			data[k.trim()] = v;
		}
	}

	window.parent.postMessage(data, '$wgNoticeReporterDomains');
</script></body></html>
EOT;
		print( $script );

	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	function sendHeaders() {
		$expiry = SpecialRecordImpression::CACHE_EXPIRY;

		// If we have a logged in user; do not cache (default for special pages)
		// lest we capture a set-cookie header. Otherwise cache so we don't have
		// too big of a DDoS hole.
		if ( !$this->getUser()->isLoggedIn() ) {
			header( "Cache-Control: public, s-maxage={$expiry}, max-age=0" );
		}
	}
}
