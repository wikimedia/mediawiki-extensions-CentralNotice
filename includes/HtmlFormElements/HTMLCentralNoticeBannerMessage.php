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

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLTextAreaField;
use MediaWiki\MediaWikiServices;

/**
 * Produces a preview div of a banner message that can be included in an HTMLForm
 *
 * Expects the following options:
 * - 'language' - ISO language code to render message in
 * - 'banner'   - Canonical name of banner message belongs to
 * - 'message'  - Canonical name of the message
 */
class HTMLCentralNoticeBannerMessage extends HTMLTextAreaField {
	protected const DEFAULT_COLS = 45;
	protected const DEFAULT_ROWS = 1;

	/** @inheritDoc */
	public function __construct( $params ) {
		if ( !array_key_exists( 'default', $params ) ) {
			$message = new BannerMessage( $params[ 'banner' ], $params[ 'message' ] );
			$params[ 'default' ] = $message->getContents( $params[ 'language' ] );
		}

		parent::__construct( $params );
	}

	/** @inheritDoc */
	public function validate( $value, $alldata ) {
		// Empty - no validation can be done on a banner message
		return true;
	}

	/**
	 * Get a preview of the banner message
	 * @param string $value
	 * @return string HTML
	 */
	public function getInputHTML( $value ) {
		$message = new BannerMessage( $this->mParams[ 'banner' ], $this->mParams[ 'message' ] );

		$html = Html::openElement( 'table', [ 'class' => 'cn-message-table' ] );
		$html .= Html::openElement( 'tr' );

		$originText = $message->getContents(
			MediaWikiServices::getInstance()->getContentLanguageCode()->toString()
		);
		$html .= Html::element(
			'td',
			[ 'class' => 'cn-message-text-origin' ],
			$originText
		);

		$this->mParams[ 'placeholder' ] = $originText;
		$html .= Html::rawElement( 'td', [ 'class' => 'cn-message-text-native' ],
			parent::getInputHTML( $message->getContents( $this->mParams[ 'language' ] ) )
		);

		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'table' );

		return $html;
	}
}
