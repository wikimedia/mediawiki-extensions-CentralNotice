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
		global $wgNoticeBannerMaxAge;
		header( "Cache-Control: public, s-maxage={$wgNoticeBannerMaxAge}, max-age=0" );
	}
}
