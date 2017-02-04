<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
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
 */

/**
 * SpecialBannerAllocation
 *
 * Special page for handling banner allocation.
 */
class SpecialBannerAllocation extends CentralNotice {
	/**
	 * The project being used for banner allocation.
	 *
	 * @see $wgNoticeProjects
	 *
	 * @var string $project
	 */
	public $project = 'wikipedia';

	/**
	 * The language being used for banner allocation
	 *
	 * This should always be a lowercase language code.
	 *
	 * @var string $language
	 */
	public $language = 'en';

	/**
	 * The location being used for banner allocation.
	 *
	 * This should always be an uppercase country code.
	 *
	 * @var string $location
	 */
	public $location = 'US';

	public function __construct() {
		// Register special page
		SpecialPage::__construct( 'BannerAllocation' );
	}

	public function isListed() {
		return false;
	}

	/**
	 * Handle different types of page requests
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		global $wgNoticeProjects, $wgLanguageCode, $wgNoticeProject;
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->project = $request->getText( 'project', $wgNoticeProject );
		$this->language = $request->getText( 'language', $wgLanguageCode );

		// If the form has been submitted, the country code should be passed along.
		$locationSubmitted = $request->getVal( 'country' );
		$this->location = $locationSubmitted ? $locationSubmitted : $this->location;

		// Convert submitted location to boolean value. If it true, showList() will be called.
		$locationSubmitted = (bool)$locationSubmitted;

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( [
			'ext.centralNotice.adminUi',
		] );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$out->addWikiMsg( 'centralnotice-summary' );

		// Begin Banners tab content
		$out->addHTML( Html::openElement( 'div', [ 'id' => 'preferences' ] ) );

		$htmlOut = '';

		// Begin Allocation selection fieldset
		$htmlOut .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		$htmlOut .= Html::openElement( 'form', [ 'method' => 'get' ] );
		$htmlOut .= Html::element( 'h2', [], $this->msg( 'centralnotice-view-allocation' )->text() );
		$htmlOut .= Xml::tags(
			'p', null, $this->msg( 'centralnotice-allocation-instructions' )->parse()
		);

		$htmlOut .= Html::openElement( 'table', [ 'id' => 'envpicker', 'cellpadding' => 7 ] );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			[ 'style' => 'width: 20%;' ],
			$this->msg( 'centralnotice-project-name' )->parse() );
		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= Html::openElement( 'select', [ 'name' => 'project' ] );

		foreach ( $wgNoticeProjects as $value ) {
			$htmlOut .= Xml::option( $value, $value, $value === $this->project );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			[ 'valign' => 'top' ],
			$this->msg( 'centralnotice-project-lang' )->parse() );
		$htmlOut .= Html::openElement( 'td' );

		// Retrieve the list of languages in user's language
		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		// Make sure the site language is in the list; a custom language code
		// might not have a defined name...
		if ( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}

		ksort( $languages );

		$htmlOut .= Html::openElement( 'select', [ 'name' => 'language' ] );

		foreach ( $languages as $code => $name ) {
			$htmlOut .= Xml::option(
				$this->msg( 'centralnotice-language-listing', $code, $name )->text(),
				$code, $code === $this->language );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', [], $this->msg( 'centralnotice-country' )->parse() );
		$htmlOut .= Html::openElement( 'td' );

		$userLanguageCode = $this->getLanguage()->getCode();
		$countries = GeoTarget::getCountriesList( $userLanguageCode );

		$htmlOut .= Html::openElement( 'select', [ 'name' => 'country' ] );

		foreach ( $countries as $code => $name ) {
			$htmlOut .= Xml::option( $name, $code, $code === $this->location );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::closeElement( 'table' );

		$htmlOut .= Xml::tags( 'div',
			[ 'class' => 'cn-buttons' ],
			Xml::submitButton( $this->msg( 'centralnotice-view' )->text() )
		);
		$htmlOut .= Html::closeElement( 'form' );

		// End Allocation selection fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$out->addHTML( $htmlOut );

		// Handle form submissions
		if ( $locationSubmitted ) {
			$this->showList();
		}

		// End Banners tab content
		$out->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	public function showList() {
		global $wgNoticeNumberOfBuckets;

		// Obtain all banners & campaigns
		$request = $this->getRequest();
		$project = $request->getText( 'project' );
		$country = $request->getText( 'country' );
		$language = $request->getText( 'language' );

		// Begin building HTML
		$htmlOut = '';

		// Begin Allocation list fieldset
		$htmlOut .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		// Given our project and language combination, get banner choice data,
		// then filter on country
		$choiceData = ChoiceDataProvider::getChoices( $project, $language );

		// Iterate through each possible device type and get allocation information
		$devices = CNDeviceTarget::getAvailableDevices();
		foreach ( $devices as $deviceId => $deviceData ) {
			$htmlOut .= Html::openElement(
				'div',
				[
					'id' => "cn-allocation-{$project}-{$language}-{$country}-{$deviceId}",
					'class' => 'cn-allocation-group'
				]
			);

			$htmlOut .= Xml::tags(
				'h3', null,
				$this->msg(
					'centralnotice-allocation-description',
					wfEscapeWikiText( $language ),
					wfEscapeWikiText( $project ),
					wfEscapeWikiText( $country ),
					$deviceData['label']
				)->parse()
			);

			// FIXME matrix is chosen dynamically based on more UI inputs
			$matrix = [];
			for ( $i = 0; $i < $wgNoticeNumberOfBuckets; $i++ ) {
				$matrix[] = [ 'anonymous' => 'true', 'bucket' => "$i" ];
			}
			for ( $i = 0; $i < $wgNoticeNumberOfBuckets; $i++ ) {
				$matrix[] = [ 'anonymous' => 'false', 'bucket' => "$i" ];
			}

			foreach ( $matrix as $target ) {
				if ( $target['anonymous'] === 'true' ) {
					$label = $this->msg( 'centralnotice-banner-anonymous' )->text();
					$status = AllocationCalculator::ANONYMOUS;
				} else {
					$label = $this->msg( 'centralnotice-banner-logged-in' )->text();
					$status = AllocationCalculator::LOGGED_IN;
				}
				$label .= ' -- ' . $this->msg( 'centralnotice-bucket-letter' )->
					rawParams( chr( $target['bucket'] + 65 ) )->text();

				$possibleBannersAllCampaigns =
					AllocationCalculator::filterAndAllocate( $country,
					$status, $deviceData['header'], $target['bucket'],
					$choiceData );

				$htmlOut .= $this->getTable( $label, $possibleBannersAllCampaigns );
			}

			$htmlOut .= Html::closeElement( 'div' );
		}

		// End Allocation list fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Generate the HTML for an allocation table
	 * @param string $type The title for the table
	 * @param array $banners The banners as allocated by AllocationCalculator
	 * @return string HTML for the table
	 */
	public function getTable( $type, $banners ) {
		$htmlOut = Html::openElement( 'table',
			[ 'cellpadding' => 9, 'class' => 'wikitable sortable', 'style' => 'margin: 1em 0;' ]
		);
		$htmlOut .= Html::element( 'h4', [], $type );

		if ( count( $banners ) > 0 ) {
			$htmlOut .= Html::openElement( 'tr' );
			$htmlOut .= Html::element( 'th', [ 'width' => '5%' ],
				$this->msg( 'centralnotice-percentage' )->parse() );
			$htmlOut .= Html::element( 'th', [ 'width' => '30%' ],
				$this->msg( 'centralnotice-banner' )->parse() );
			$htmlOut .= Html::element( 'th', [ 'width' => '30%' ],
				$this->msg( 'centralnotice-notice' )->parse() );
			$htmlOut .= Html::closeElement( 'tr' );
		}
		$htmlOut .= $this->createRows( $banners );

		$htmlOut .= Html::closeElement( 'table' );

		return $htmlOut;
	}

	public function createRows( $banners ) {
		$viewCampaign = $this->getTitleFor( 'CentralNotice' );
		$htmlOut = '';
		if ( count( $banners ) > 0 ) {
			$linkRenderer = $this->getLinkRenderer();
			foreach ( $banners as $banner ) {
				$percentage = sprintf( "%0.2f", round( $banner['allocation'] * 100, 2 ) );

				// Row begin
				$htmlOut .= Html::openElement( 'tr', [ 'class' => 'mw-sp-centralnotice-allocationrow' ] );

				// Percentage
				$htmlOut .= Html::openElement( 'td', [ 'align' => 'right' ] );
				$htmlOut .= $this->msg( 'percent', $percentage )->escaped();
				$htmlOut .= Html::closeElement( 'td' );

				// Banner name
				$viewBanner = $this->getTitleFor( 'CentralNoticeBanners', "edit/{$banner['name']}" );
				$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top' ] );

				$htmlOut .= Html::openElement( 'span',
					[ 'class' => 'cn-'.$banner['campaign'].'-'.$banner['name'] ] );
				$htmlOut .= $linkRenderer->makeLink(
					$viewBanner,
					$banner['name'],
					[],
					[ 'template' => $banner['name'] ]
				);
				$htmlOut .= Html::closeElement( 'span' );
				$htmlOut .= Html::closeElement( 'td' );

				// Campaign name
				$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
					$linkRenderer->makeLink(
						$viewCampaign,
						$banner['campaign'],
						[],
						[
							'subaction' => 'noticeDetail',
							'notice' => $banner['campaign']
						]
					)
				);

				// Row end
				$htmlOut .= Html::closeElement( 'tr' );
			}

		} else {
			$htmlOut .= Html::openElement( 'tr' );
			$htmlOut .= Html::openElement( 'td' );
			$htmlOut .= Xml::tags( 'p', null, $this->msg( 'centralnotice-no-allocation' )->parse() );
			$htmlOut .= Html::closeElement( 'td' );
			$htmlOut .= Html::closeElement( 'tr' );
		}
		return $htmlOut;
	}
}
