<?php

class BannerPreviewPermissionsException extends BannerLoaderException
	implements ILocalizedException {

	/** @var Message */
	private $msg;

	public function __construct( $banner ) {
		parent::__construct( $banner,
			'Permissions or edit token error for request to generate banner preview' );

		$this->msg = wfMessage( 'centralnotice-preview-loader-permissions-error' );
	}

	public function getMessageObject() {
		return $this->msg;
	}
}
