<?php

use MediaWiki\Html\Html;

class HTMLBannerPagerNavigation extends HTMLFormField {
	public function validate( $value, $alldata ) {
		// Empty - no validation can be done on a navigation element
		return true;
	}

	public function getInputHTML( $value ) {
		return $this->mParams['value'];
	}

	public function getDiv( $value ) {
		return Html::rawElement(
			'div',
			[ 'class' => 'cn-banner-list-pager-nav' ],
			$this->getInputHTML( $value )
		);
	}
}
