<?php

use MediaWiki\SpecialPage\SpecialPage;

class SpecialNoticeTemplate extends CentralNotice {
	public function __construct() {
		// Register special page
		SpecialPage::__construct( 'NoticeTemplate' );
	}

	/**
	 * Handle different types of page requests
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		if ( $sub == 'view' ) {
			// Trying to view a banner -- so redirect to edit form
			$banner = $this->getRequest()->getText( 'template' );
			$title = SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/$banner" );
		} else {
			// don't know where they were trying to go, redirect them to the new list form
			$title = SpecialPage::getTitleFor( 'CentralNoticeBanners' );
		}
		$this->getOutput()->redirect(
			$title->getFullUrlForRedirect(),
			301
		);
	}
}
