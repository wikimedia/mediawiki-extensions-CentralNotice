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
 * Produces a banner preview DIV that can be embedded in an HTMLForm.
 *
 * Expects the following options:
 * - 'language'  - ISO language code to render banner in
 * - 'banner'    - Canonical name of banner
 * - 'withlabel' - Presence of this attribute causes a label to be shown
 */
class HTMLCentralNoticeBanner extends HTMLInfoField {
	/** Empty - no validation can be done on a banner */
	function validate( $value, $alldata ) { return true; }

	/** Get a preview of the banner */
	public function getInputHTML( $value ) {
		global $wgOut,
			$wgNoticeBannerPreview;

		$bannerName = $this->mParams['banner'];
		if ( array_key_exists( 'language', $this->mParams ) ) {
			$language = $this->mParams['language'];
		} else {
			$language = $wgOut->getContext()->getLanguage()->getCode();
		}

		$previewUrl = $wgNoticeBannerPreview . "/{$bannerName}/{$bannerName}_{$language}.png";
		$preview = Html::Element(
			'img',
			array(
				'src' => $previewUrl,
				'alt' => $bannerName,
			)
		);

		return Xml::tags(
			'div',
			array(
				 'id' => Sanitizer::escapeId( "cn-banner-preview-$bannerName" ),
				 'class' => 'cn-banner-preview-div',
			),
			$preview
		);
	}

	public function getTableRow( $value ) {
		throw new BadMethodCallException( "getTableRow() is not implemented for HTMLCentralNoticeBanner" );
	}

	public function getRaw( $value ) {
		throw new BadMethodCallException( "getRaw() is not implemented for HTMLCentralNoticeBanner" );
	}

	public function getDiv( $value ) {
		global $wgOut,
			$wgNoticeBannerPreview;

		if ( array_key_exists( 'language', $this->mParams ) ) {
			$language = $this->mParams['language'];
		} else {
			$language = $wgOut->getContext()->getLanguage()->getCode();
		}

		$html = Xml::openElement(
			'div',
			array(
				 'id' =>  Sanitizer::escapeId( "cn-banner-list-element-{$this->mParams['banner']}" ),
				 'class' => "cn-banner-list-element",
			)
		);

		// Make the label; this consists of a text link to the banner editor, and a series of status icons
		if ( array_key_exists( 'withlabel', $this->mParams ) ) {
			$bannerName =  $this->mParams['banner'];
			$html .= Xml::openElement( 'div', array( 'class' => 'cn-banner-list-element-label' ) );
			$html .= Linker::link(
				SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/$bannerName" ),
				htmlspecialchars( $bannerName ),
				array( 'class' => 'cn-banner-list-element-label-text' )
			);
			$html .= ' (' . Linker::link(
				SpecialPage::getTitleFor( 'Randompage' ),
				$this->msg( 'centralnotice-live-preview' ),
				array( 'class' => 'cn-banner-list-element-label-text' ),
				array(
					 'banner' => $bannerName,
					 'uselang' => $language,
					 'force' => '1'
				)
			) . ')';
			// TODO: Output status icons
			$html .= Xml::tags( 'div', array( 'class' => 'cn-banner-list-element-label-icons' ), '' );
			$html .= Xml::closeElement( 'div' );
		}

		// Add the banner preview
		if ( $wgNoticeBannerPreview ) {
			$html .= $this->getInputHTML( null );
		}

		$html .= Xml::closeElement( 'div' );
		return $html;
	}
}
