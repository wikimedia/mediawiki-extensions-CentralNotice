<?php

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLSelectField;
use MediaWiki\MediaWikiServices;

/**
 * Acts as a header to the translatable banner message list
 */
class LanguageSelectHeaderElement extends HTMLSelectField {
	public function getInputHTML( $value ) {
		$html = Html::openElement( 'table', [ 'class' => 'cn-message-table' ] );
		$html .= Html::openElement( 'tr' );

		$code = MediaWikiServices::getInstance()->getContentLanguage()->getCode();
		$html .= Html::element( 'td', [ 'class' => 'cn-message-text-origin-header' ],
			MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName( $code, $code )
		);

		$html .= Html::rawElement( 'td', [ 'class' => 'cn-message-text-native-header' ],
			parent::getInputHTML( $value )
		);

		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'table' );

		return $html;
	}
}
