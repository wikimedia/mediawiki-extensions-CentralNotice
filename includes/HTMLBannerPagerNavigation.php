<?php

class HTMLBannerPagerNavigation extends HTMLFormField {
	public function validate( $value, $alldata ) {
		// Empty - no validation can be done on a navigation element
		return true;
	}

	public function getInputHTML( $value ) {
		return $this->mParams['value'];
	}

	public function getDiv( $value ) {
		$html = Xml::openElement(
			'div',
			[ 'class' => "cn-banner-list-pager-nav", ]
		);
		$html .= $this->getInputHTML( $value );
		$html .= Xml::closeElement( 'div' );

		return $html;
	}
}
