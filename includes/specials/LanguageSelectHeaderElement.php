<?php

use MediaWiki\MediaWikiServices;

/**
 * Acts as a header to the translatable banner message list
 */
class LanguageSelectHeaderElement extends HTMLSelectField {
	public function getInputHTML( $value ) {
		$html = Xml::openElement( 'table', [ 'class' => 'cn-message-table' ] );
		$html .= Xml::openElement( 'tr' );

		$code = MediaWikiServices::getInstance()->getContentLanguage()->getCode();
		$html .= Xml::element( 'td', [ 'class' => 'cn-message-text-origin-header' ],
			Language::fetchLanguageName( $code, $code )
		);

		$html .= Xml::openElement( 'td', [ 'class' => 'cn-message-text-native-header' ] );
		$html .= parent::getInputHTML( $value );
		$html .= Xml::closeElement( 'td' );

		$html .= Xml::closeElement( 'tr' );
		$html .= Xml::closeElement( 'table' );

		return $html;
	}
}
