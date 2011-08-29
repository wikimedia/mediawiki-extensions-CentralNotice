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
		global $wgOut, $wgRequest, $wgExtensionAssetsPath, $wgNoticeProjects, $wgLanguageCode, $wgNoticeProject;

		$locationSubmitted = false;
		
        $this->project = $wgRequest->getText( 'project', 'wikipedia', $wgNoticeProject );
        $this->language = $wgRequest->getText( 'language', $wgLanguageCode );
			
        // If the form has been submitted, the country code should be passed along.
        $locationSubmitted = $wgRequest->getVal( 'country' );
        $this->location = $locationSubmitted ? $locationSubmitted : $this->location;
		
        // Convert submitted location to boolean value. If it true, showList() will be called.
        $locationSubmitted = (boolean) $locationSubmitted;

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
		
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'get' ) );
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
			wfMsg( 'centralnotice-project-lang' ) );
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
		if ( $locationSubmitted ) {
			$this->showList();
		}

		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	function showList() {
		global $wgOut, $wgRequest;
		
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
		$anonBanners = array();
		$accountBanners = array();
		$anonWeight = 0;
		$accountWeight = 0;
		if ( $banners ) {
			foreach ( $banners as $banner ) {
				if ($banner['display_anon']) {
					$anonBanners[] = $banner;
					$anonWeight += $banner['weight'];
				}
				if ($banner['display_account']) {
					$accountBanners[] = $banner;
					$accountWeight += $banner['weight'];
				}
			}
			if ( $anonBanners ) {
				$htmlOut .= $this->getTable( wfMsg ( 'centralnotice-banner-anonymous' ), $anonBanners, $anonWeight );
			}
			if ( $accountBanners ) {
				$htmlOut .= $this->getTable( wfMsg ( 'centralnotice-banner-logged-in' ), $accountBanners, $accountWeight );
			}
		} else {
			$htmlOut .= Xml::tags( 'p', null, wfMsg ( 'centralnotice-no-allocation' ) );
		}
		
		// End Allocation list fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}
	
	function getTable( $type, $banners, $weight ) {
		global $wgUser, $wgLang;
		
		$sk = $wgUser->getSkin();
		$viewBanner = $this->getTitleFor( 'NoticeTemplate', 'view' );
		$viewCampaign = $this->getTitleFor( 'CentralNotice' );
		
		$htmlOut = Xml::openElement( 'table', 
			array ( 'cellpadding' => 9, 'class' => 'wikitable sortable', 'style' => 'margin: 1em;' )
		);
		$htmlOut .= Xml::element( 'caption', array( 'style' => 'font-size: 1.2em;' ), $type );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', array( 'width' => '20%' ), 
			wfMsg ( 'centralnotice-percentage' ) );
		$htmlOut .= Xml::element( 'th', array( 'width' => '30%' ), 
			wfMsg ( 'centralnotice-banner' ) );
		$htmlOut .= Xml::element( 'th', array( 'width' => '30%' ), 
			wfMsg ( 'centralnotice-notice' ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		foreach ( $banners as $banner ) {
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::openElement( 'td' );
			$percentage = round( ( $banner['weight'] / $weight ) * 100, 2 );
			$htmlOut .= wfMsg ( 'percent', $wgLang->formatNum( $percentage ) );
			$htmlOut .= Xml::closeElement( 'td' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$sk->makeLinkObj( $viewBanner, htmlspecialchars( $banner['name'] ), 
					'template=' . urlencode( $banner['name'] ) )
			);
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$sk->makeLinkObj( $viewCampaign, htmlspecialchars( $banner['campaign'] ), 
					'method=listNoticeDetail&notice=' . urlencode( $banner['campaign'] ) )
			);
			$htmlOut .= Xml::closeElement( 'tr' );
		}
		$htmlOut .= Xml::closeElement( 'table' );
		return $htmlOut;
	}

}
