<?php
/**
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * Produces a preview div of a banner message that can be included in an HTMLForm
 *
 * Expects the following options:
 * - 'language' - ISO language code to render message in
 * - 'banner'   - Canonical name of banner message belongs to
 * - 'message'  - Canonical name of the message
 */
class HTMLCentralNoticeBannerMessage extends HTMLTextAreaField {
	const DEFAULT_COLS = 45;
	const DEFAULT_ROWS = 1;

	function __construct( $params ) {
		if ( !array_key_exists( 'default', $params ) ) {
			$message = new BannerMessage( $this->mParams[ 'banner' ], $this->mParams[ 'message' ] );
			$params[ 'default' ] = $message->getContents( $this->mParams[ 'language' ] );
		}

		parent::__construct( $params );
	}

	/** Empty - no validation can be done on a banner message */
	function validate( $value, $alldata ) { return true; }

	/** Get a preview of the banner message */
	public function getInputHTML( $value ) {
		global $wgContLang;

		$message = new BannerMessage( $this->mParams[ 'banner' ], $this->mParams[ 'message' ] );

		$html = Xml::openElement( 'table', array( 'class' => 'cn-message-table' ) );
		$html .= Xml::openElement( 'tr' );

		$originText = $message->getContents( $wgContLang->getCode() );
		$html .= Xml::element(
			'td',
			array( 'class' => 'cn-message-text-origin' ),
			$originText
		);

		$this->mParams[ 'placeholder' ] = $originText;
		$html .= Xml::openElement( 'td', array( 'class' => 'cn-message-text-native' ) );
		$html .= parent::getInputHTML( $message->getContents( $this->mParams[ 'language' ] ) );
		$html .= Xml::closeElement( 'td' );

		$html .= Xml::closeElement( 'tr' );
		$html .= Xml::closeElement( 'table' );

		return $html;
	}
}