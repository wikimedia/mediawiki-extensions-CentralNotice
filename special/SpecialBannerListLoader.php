<?php
// FIXME deprecated in so many ways... assuming we will merge the Api patch here:
// can be deleted once the bannerController module is updated and purged.

class SpecialBannerListLoader extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'BannerListLoader' );
	}

	public function execute( $par ) {
		// Set up for JSON output. As of 1.20 a special page is apparently faster than an API
		// call
		$this->getOutput()->disable();
		$this->sendHeaders();

		$bannerList = ApiCentralNoticeAllocations::getAllocationInformation(
			$this->getRequest()->getText('project'),
			$this->getRequest()->getText('country'),
			$this->getRequest()->getText('language'),
			$this->getRequest()->getText('anonymous'),
			$this->getRequest()->getText('bucket'),
			true
		);

		$output = array();
		$output['centralnoticeallocations']['banners'] = $bannerList;

		print FormatJson::encode( $output );
	}

	/**
	 * Generate the HTTP response headers for the banner file
	 */
	protected function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;

		// Set the cache control
		$this->getRequest()->response()->header(
			"Cache-Control: public, s-maxage=$wgNoticeBannerMaxAge, max-age=$wgNoticeBannerMaxAge",
			true
		);

		// And the datatype
		$this->getRequest()->response()->header(
			"Content-type: $wgJsMimeType; charset=utf-8",
			true
		);
	}
}
