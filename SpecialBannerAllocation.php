<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class SpecialBannerAllocation extends UnlistedSpecialPage {
	public $project = 'wikipedia';
	public $language = 'en';
	public $location = 'US';
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerAllocation" );
	}
	
	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgRequest, $wgExtensionAssetsPath, $wgNoticeProjects, $wgLanguageCode;
		
		if ( $wgRequest->wasPosted() ) {
			$this->project = $wgRequest->getText( 'project', 'wikipedia' );
			$this->language = $wgRequest->getText( 'language', 'en' );
			$this->location = $wgRequest->getText( 'country', 'US' );
		}

		// Begin output
		$this->setHeaders();
		
		// Add style file to the output headers
		$wgOut->addExtensionStyle( "$wgExtensionAssetsPath/CentralNotice/centralnotice.css" );
		
		// Add script file to the output headers
		$wgOut->addScriptFile( "$wgExtensionAssetsPath/CentralNotice/centralnotice.js" );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$wgOut->addWikiMsg( 'centralnotice-summary' );

		// Show header
		CentralNotice::printHeader();

		// Begin Banners tab content
		$wgOut->addHTML( Xml::openElement( 'div', array( 'id' => 'preferences' ) ) );
		
		$htmlOut = '';
		
		// Begin Allocation selection fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-view-allocation' ) );
		$htmlOut .= Xml::tags( 'p', null, wfMsg( 'centralnotice-allocation-instructions' ) );
		
		$htmlOut .= Xml::openElement( 'table', array ( 'id' => 'envpicker', 'cellpadding' => 7 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', 
			array( 'style' => 'width: 20%;' ), 
			wfMsg( 'centralnotice-project-name' ) );
		$htmlOut .= Xml::openElement( 'td' );
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'project' ) );
		foreach ( $wgNoticeProjects as $value ) {
			$htmlOut .= Xml::option( $value, $value, $value === $this->project );
		}
		$htmlOut .= Xml::closeElement( 'select' );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', 
			array( 'valign' => 'top' ), 
			wfMsg( 'centralnotice-project-language' ) );
		$htmlOut .= Xml::openElement( 'td' );
		// Make sure the site language is in the list; a custom language code 
		// might not have a defined name...
		$languages = Language::getLanguageNames( true );
		if( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}
		ksort( $languages );
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'language' ) );
		foreach( $languages as $code => $name ) {
			$htmlOut .= Xml::option( 
				wfMsg( 'centralnotice-language-listing', $code, $name ), 
				$code, $code === $this->language );
		}
		$htmlOut .= Xml::closeElement( 'select' );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), wfMsg( 'centralnotice-country' ) );
		$htmlOut .= Xml::openElement( 'td' );
		$countries = CentralNoticeDB::getCountriesList();
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'country' ) );
		foreach( $countries as $code => $name ) {
			$htmlOut .= Xml::option( $name, $code, $code === $this->location );
		}
		$htmlOut .= Xml::closeElement( 'select' );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::closeElement( 'table' );
		
		$htmlOut .= Xml::tags( 'div', 
			array( 'class' => 'cn-buttons' ), 
			Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) 
		);
		$htmlOut .= Xml::closeElement( 'form' );
		
		// End Allocation selection fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
		
		// Handle form submissions
		if ( $wgRequest->wasPosted() ) {
			$this->showList();
		}

		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	function showList() {
		global $wgRequest, $wgOut, $wgUser, $wgRequest, $wgLang;
		
		$sk = $wgUser->getSkin();
		$viewPage = $this->getTitleFor( 'NoticeTemplate', 'view' );
		
		// Begin building HTML
		$htmlOut = '';
		
		// Begin Allocation list fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$bannerLister = new SpecialBannerListLoader();
		$bannerLister->project = $wgRequest->getVal( 'project' );
		$bannerLister->language = $wgRequest->getVal( 'language' );
		$bannerLister->location = $wgRequest->getVal( 'country' );

		$htmlOut .= Xml::tags( 'p', null,
			wfMsg (
				'centralnotice-allocation-description',
				htmlspecialchars( $bannerLister->language ),
				htmlspecialchars( $bannerLister->project ),
				htmlspecialchars( $bannerLister->location )
			)
		);

		$bannerList = $bannerLister->getJsonList();
		$banners = FormatJson::decode( $bannerList, true );
		$totalWeight = 0;
		foreach ( $banners as $banner ) {
			$totalWeight += $banner['weight'];
		}
		if ( $banners ) {
			$htmlOut .= Xml::openElement( 'table', 
				array ( 'cellpadding' => 9, 'class' => 'wikitable sortable' ) );
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::element( 'th', array( 'width' => '40%' ), 
				wfMsg ( 'centralnotice-percentage' ) );
			$htmlOut .= Xml::element( 'th', array( 'width' => '60%' ), 
				wfMsg ( 'centralnotice-banner' ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			foreach ( $banners as $banner ) {
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::openElement( 'td' );
				$percentage = ( $banner['weight'] / $totalWeight ) * 100;
				$htmlOut .= wfMsg ( 'percent', $wgLang->formatNum( $percentage ) );
				$htmlOut .= Xml::closeElement( 'td' );
				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
					$sk->makeLinkObj( $viewPage, htmlspecialchars( $banner['name'] ), 
						'template=' . urlencode( $banner['name'] ) )
				);
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );
		} else {
			$htmlOut .= Xml::tags( 'p', null, wfMsg ( 'centralnotice-no-allocation' ) );
		}
		
		// End Allocation list fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}

}
