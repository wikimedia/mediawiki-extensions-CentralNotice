<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class SpecialBannerAllocation extends UnlistedSpecialPage {
	var $centralNoticeError;
	
	function __construct() {
		// Register special page
		parent::__construct( "BannerAllocation" );

		// Internationalization
		wfLoadExtensionMessages( 'CentralNotice' );
	}
	
	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgUser, $wgRequest, $wgScriptPath, $wgNoticeProjects, $wgContLanguageCode;

		// Begin output
		$this->setHeaders();
		
		// Add style file to the output headers
		$wgOut->addExtensionStyle( "$wgScriptPath/extensions/CentralNotice/centralnotice.css" );
		
		// Add script file to the output headers
		$wgOut->addScriptFile( "$wgScriptPath/extensions/CentralNotice/centralnotice.js" );

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
		$htmlOut .= Xml::element( 'h2', null, 'View banner allocation' );
		$htmlOut .= Xml::tags( 'p', null, 'Choose the environment you would like to view banner allocation for:' );
		
		$htmlOut .= Xml::openElement( 'table', array ( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array( 'style' => 'width: 150px;' ), wfMsgHtml( 'centralnotice-project-name' ) );
		$htmlOut .= Xml::openElement( 'td' );
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'project' ) );
		foreach ( $wgNoticeProjects as $value ) {
			$htmlOut .= Xml::option( $value, $value, false );
		}
		$htmlOut .= Xml::closeElement( 'select' );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 'Project language' );
		$htmlOut .= Xml::openElement( 'td' );
		// Make sure the site language is in the list; a custom language code might not have a defined name...
		$languages = Language::getLanguageNames( true );
		if( !array_key_exists( $wgContLanguageCode, $languages ) ) {
			$languages[$wgContLanguageCode] = $wgContLanguageCode;
		}
		ksort( $languages );
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'language' ) );
		foreach( $languages as $code => $name ) {
			$htmlOut .= Xml::option(
				wfMsg( 'centralnotice-language-listing', $code, $name ),
				$code,
				false
			);
		}
		$htmlOut .= Xml::closeElement( 'select' );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), 'Country' );
		$htmlOut .= Xml::openElement( 'td' );
		$countries = CentralNoticeDB::getCountriesList();
		$htmlOut .= Xml::openElement( 'select', array( 'name' => 'country' ) );
		foreach( $countries as $code => $name ) {
			$htmlOut .= Xml::option(
				$name,
				$code,
				false
			);
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
				
			// Show list of banners by default
			$this->showList();
			
		}

		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	function showList() {
		// Begin building HTML
		$htmlOut = '';
		
		// Begin Allocation list fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= "List goes here";
		
		// End Allocation list fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}

}
