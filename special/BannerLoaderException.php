<?php

/**
 * These exceptions are thrown whenever an error occurs, which is fatal to
 * rendering the banner, but can be fairly expected.
 *
 * @ingroup Exception
 */
class BannerLoaderException extends Exception {
	public function __construct( $bannerName = '(none provided)', $extraMsg = null ) {
		$this->message = static::class .
			" while loading banner: '{$bannerName}'";
		if ( $extraMsg ) {
			$this->message .= ". {$extraMsg}";
		}
	}
}
