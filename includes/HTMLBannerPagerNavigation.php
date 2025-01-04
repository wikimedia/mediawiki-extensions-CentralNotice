<?php

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLFormField;

class HTMLBannerPagerNavigation extends HTMLFormField {
	/** @inheritDoc */
	public function validate( $value, $alldata ) {
		// Empty - no validation can be done on a navigation element
		return true;
	}

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		return $this->mParams['value'];
	}

	/** @inheritDoc */
	public function getDiv( $value ) {
		return Html::rawElement(
			'div',
			[ 'class' => 'cn-banner-list-pager-nav' ],
			$this->getInputHTML( $value )
		);
	}
}
