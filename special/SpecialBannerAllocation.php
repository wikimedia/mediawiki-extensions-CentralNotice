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
class SpecialBannerAllocation extends UnlistedSpecialPage {

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
	 * @var string $project
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

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register special page
		parent::__construct( 'BannerAllocation' );
	}

	/**
	 * Handle different types of page requests
	 */
	public function execute( $sub ) {
		global $wgOut, $wgLang, $wgRequest, $wgNoticeProjects, $wgLanguageCode, $wgNoticeProject;

		$this->project = $wgRequest->getText( 'project', $wgNoticeProject );
		$this->language = $wgRequest->getText( 'language', $wgLanguageCode );

		// If the form has been submitted, the country code should be passed along.
		$locationSubmitted = $wgRequest->getVal( 'country' );
		$this->location = $locationSubmitted ? $locationSubmitted : $this->location;

		// Convert submitted location to boolean value. If it true, showList() will be called.
		$locationSubmitted = (boolean) $locationSubmitted;

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$wgOut->addModules( array( 'ext.centralNotice.interface', 'ext.centralNotice.bannerStats' ) );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$wgOut->addWikiMsg( 'centralnotice-summary' );

		// Show header
		CentralNotice::printHeader();

		// Begin Banners tab content
		$wgOut->addHTML( Html::openElement( 'div', array( 'id' => 'preferences' ) ) );

		$htmlOut = '';

		// Begin Allocation selection fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Html::openElement( 'form', array( 'method' => 'get' ) );
		$htmlOut .= Html::element( 'h2', null, wfMsg( 'centralnotice-view-allocation' ) );
		$htmlOut .= Xml::tags( 'p', null, wfMsg( 'centralnotice-allocation-instructions' ) );

		$htmlOut .= Html::openElement( 'table', array ( 'id' => 'envpicker', 'cellpadding' => 7 ) );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			array( 'style' => 'width: 20%;' ),
			wfMsg( 'centralnotice-project-name' ) );
		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= Html::openElement( 'select', array( 'name' => 'project' ) );

		foreach ( $wgNoticeProjects as $value ) {
			$htmlOut .= Xml::option( $value, $value, $value === $this->project );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			array( 'valign' => 'top' ),
			wfMsg( 'centralnotice-project-lang' ) );
		$htmlOut .= Html::openElement( 'td' );

		// Make sure the site language is in the list; a custom language code
		// might not have a defined name...
		$languages = Language::getLanguageNames( true );
		if( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}

		ksort( $languages );
		$htmlOut .= Html::openElement( 'select', array( 'name' => 'language' ) );

		foreach( $languages as $code => $name ) {
			$htmlOut .= Xml::option(
				wfMsg( 'centralnotice-language-listing', $code, $name ),
				$code, $code === $this->language );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), wfMsg( 'centralnotice-country' ) );
		$htmlOut .= Html::openElement( 'td' );

		$userLanguageCode = $wgLang->getCode();
		$countries = CentralNoticeDB::getCountriesList( $userLanguageCode );

		$htmlOut .= Html::openElement( 'select', array( 'name' => 'country' ) );

		foreach( $countries as $code => $name ) {
			$htmlOut .= Xml::option( $name, $code, $code === $this->location );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::closeElement( 'table' );

		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'cn-buttons' ),
			Xml::submitButton( wfMsg( 'centralnotice-view' ) )
		);
		$htmlOut .= Html::closeElement( 'form' );

		// End Allocation selection fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );

		// Handle form submissions
		if ( $locationSubmitted ) {
			$this->showList();
		}

		// End Banners tab content
		$wgOut->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	public function showList() {
		global $wgOut, $wgRequest, $wgLanguageCode;

		// Obtain all banners & campaigns
		$project = SpecialBannerListLoader::getTextAndSanitize(
			'project',
			SpecialBannerListLoader::PROJECT_FILTER
		);

		$language = SpecialBannerListLoader::getTextAndSanitize(
			'language',
			SpecialBannerListLoader::LANG_FILTER
		);

		$location = SpecialBannerListLoader::getTextAndSanitize(
			'country',
			SpecialBannerListLoader::LOCATION_FILTER
		);

		$campaigns = CentralNoticeDB::getCampaigns( $project, $language, $location );
		$banners = CentralNoticeDB::getCampaignBanners( $campaigns );

		// Filter appropriately
		$anonCampaigns = array();
		$accountCampaigns = array();

		$anonBanners = $this->filterBanners( $banners, 'display_anon', 'anon_weight', $anonCampaigns );
		$accountBanners = $this->filterBanners( $banners, 'display_account', 'account_weight', $accountCampaigns );

		$campaignsUsed = array_keys($anonCampaigns + $accountCampaigns);

		// Begin building HTML
		$htmlOut = '';

		// Begin Allocation list fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Xml::tags( 'p', null,
			wfMsg (
				'centralnotice-allocation-description',
				htmlspecialchars( $language ),
				htmlspecialchars( $project ),
				htmlspecialchars( $location )
			)
		);

		// Build campaign list for bannerstats.js
		$campaignList = FormatJson::encode( $campaignsUsed );
		$js = "wgCentralNoticeAllocationCampaigns = $campaignList;";
		$htmlOut .= Html::inlineScript( $js );

		// And now print the allocation tables
		$htmlOut .= $this->getTable( wfMsg ( 'centralnotice-banner-anonymous' ), $anonBanners, 'anon_weight' );
		$htmlOut .= $this->getTable( wfMsg ( 'centralnotice-banner-logged-in' ), $accountBanners, 'account_weight' );

		// End Allocation list fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}

	/**
	 * Generate the HTML for an allocation table
	 * @param $type string The title for the table
	 * @param $banners array The banners to list
	 * @param $weight integer The total weight of the banners
	 * @return HTML for the table
	 */
	public function getTable( $type, $banners, $weightKey ) {
		global $wgLang;

		$viewBanner = $this->getTitleFor( 'NoticeTemplate', 'view' );
		$viewCampaign = $this->getTitleFor( 'CentralNotice' );

		$htmlOut = Html::openElement( 'table',
			array ( 'cellpadding' => 9, 'class' => 'wikitable sortable', 'style' => 'margin: 1em 0;' )
		);
		$htmlOut .= Html::element( 'caption', array( 'style' => 'font-size: 1.2em;' ), $type );

		if (count($banners) > 0) {

			$htmlOut .= Html::openElement( 'tr' );
			$htmlOut .= Html::element( 'th', array( 'width' => '20%' ),
				wfMsg ( 'centralnotice-percentage' ) );
			$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
				wfMsg ( 'centralnotice-banner' ) );
			$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
				wfMsg ( 'centralnotice-notice' ) );
			$htmlOut .= Html::closeElement( 'tr' );

			foreach ( $banners as $banner ) {

				$percentage = round( $banner[$weightKey] * 100, 2 );

				$htmlOut .= Html::openElement( 'tr' );
				$htmlOut .= Html::openElement( 'td' );

				$htmlOut .= wfMsg ( 'percent', $wgLang->formatNum( $percentage ) );
				$htmlOut .= Html::closeElement( 'td' );

				$htmlOut .= Xml::openElement( 'td', array( 'valign' => 'top' ) );
				// The span class is used by bannerstats.js to find where to insert the stats
				$htmlOut .= Html::openElement( 'span',
					array( 'class' => 'cn-'.$banner['campaign'].'-'.$banner['name'] ) );
				$htmlOut .= Linker::makeLinkObj( $viewBanner, htmlspecialchars( $banner['name'] ),
						'template=' . urlencode( $banner['name'] ) );
				$htmlOut .= Html::closeElement( 'span' );
				$htmlOut .= Html::closeElement( 'td' );

				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),

					Linker::makeLinkObj( $viewCampaign, htmlspecialchars( $banner['campaign'] ),
						'method=listNoticeDetail&notice=' . urlencode( $banner['campaign'] ) )
				);

				$htmlOut .= Html::closeElement( 'tr' );
			}

		} else {
			$htmlOut .= Html::openElement('tr');
			$htmlOut .= Html::openElement('td');
			$htmlOut .= Xml::tags( 'p', null, wfMsg ( 'centralnotice-no-allocation' ) );
		}

		$htmlOut .= Html::closeElement( 'table' );

		return $htmlOut;
	}

	/**
	 * @param array  $banners
	 * @param string $filterKey
	 * @param string $weightKey
	 * @param array  $campaignWeights
	 *
	 * @return array
	 */
	private function filterBanners( $banners, $filterKey, $weightKey, &$campaignWeights = array() ) {
		$campaignZLevel = CentralNotice::MIN_Z_LEVEL;
		$filteredBanners = array();

		// Find the highest Z level
		foreach ( $banners as $banner ) {
			if ( ( $banner['campaign_z_index'] > $campaignZLevel ) && ( $banner[$filterKey] == true ) ) {
				$campaignZLevel = $banner['campaign_z_index'];
			}
		}

		// Determine the weighting factors
		foreach ( $banners as $banner ) {
			if ( ( $banner['campaign_z_index'] == $campaignZLevel ) && ( $banner[$filterKey] == true ) ) {
				if ( array_key_exists( $banner['campaign'], $campaignWeights ) ) {
					$campaignWeights[$banner['campaign']] += $banner['weight'];
				} else {
					$campaignWeights[$banner['campaign']] = $banner['weight'];
				}
			}
		}

		// Construct the relative weights
		foreach ( $banners as $banner ) {
			if ( ( $banner['campaign_z_index'] == $campaignZLevel ) && ( $banner[$filterKey] == true ) ) {

				$banner[$weightKey] = ( $banner['weight'] / $campaignWeights[$banner['campaign']] );
				$banner[$weightKey] *= 1 / count( $campaignWeights );

				$filteredBanners[] = $banner;
			}
		}

		// Return everything
		return $filteredBanners;
	}
}
