<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mwalker
 * Date: 10/23/12
 * Time: 3:48 PM
 * To change this template use File | Settings | File Templates.
 */

class SpecialCNVarnishEndpoint extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CNVarnishEndpoint' );
	}

	public function execute( $par ) {
		// Set up for JSON output. As of 1.20 a special page is apparently faster than an API
		// call
		$this->getOutput()->disable();
		$this->sendHeaders();

		print ("HI! " . time());
		print ("\nSlot: " . $this->getRequest()->getHeader('X-CentralNotice-Slot'));
	}

	protected function sendHeaders() {
		global $wgJsMimeType, $wgNoticeBannerMaxAge;

		// Set the cache control
		$this->getRequest()->response()->header(
			"Cache-Control: public, s-maxage=30, max-age=30",
			true
		);

		$this->getRequest()->response()->header(
			"Vary: X-CentralNotice-Slot, X-CentralNotice-Language, X-CentralNotice-Anonymous, X-CentralNotice-Bucket",
			true
		);

		$this->getRequest()->response()->header(
			"X-CentralNotice-Banner: foo",
			true
		);

		$this->getRequest()->response()->header(
			"X-CentralNotice-Campaign: bar",
			true
		);

		// And the datatype
		$this->getRequest()->response()->header(
			"Content-type: $wgJsMimeType; charset=utf-8",
			true
		);
	}
}