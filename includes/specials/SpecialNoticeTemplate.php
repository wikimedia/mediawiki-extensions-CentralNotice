<?php

class SpecialNoticeTemplate extends CentralNotice {
	public function __construct() {
		// Register special page
		SpecialPage::__construct( 'NoticeTemplate' );
	}

	public function isListed() {
		return false;
	}

	/**
	 * Handle different types of page requests
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		if ( $sub == 'view' ) {
			// Trying to view a banner -- so redirect to edit form
			$banner = $this->getRequest()->getText( 'template' );

			$this->getOutput()->redirect(
				Title::makeTitleSafe( NS_SPECIAL, "CentralNoticeBanners/edit/$banner" )->
					getFullUrlForRedirect(),
				301
			);
		} else {
			// don't know where they were trying to go, redirect them to the new list form
			$this->getOutput()->redirect(
				Title::makeTitle( NS_SPECIAL, 'CentralNoticeBanners' )->getFullUrlForRedirect(),
				301
			);
		}
	}
}
