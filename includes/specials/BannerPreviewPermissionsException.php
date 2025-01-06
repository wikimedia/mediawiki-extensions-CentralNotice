<?php

class BannerPreviewPermissionsException extends BannerLoaderException {

	public function __construct( string $banner ) {
		parent::__construct( $banner,
			'Permissions or edit token error for request to generate banner preview' );
	}

}
