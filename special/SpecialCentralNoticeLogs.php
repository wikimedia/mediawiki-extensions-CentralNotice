<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class SpecialCentralNoticeLogs extends UnlistedSpecialPage {
	public $logType = 'campaignsettings';
	
	function __construct() {
		// Register special page
		parent::__construct( "CentralNoticeLogs" );
	}
	
	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgRequest, $wgExtensionAssetsPath;
		
		$this->logType = $wgRequest->getText( 'log', 'campaignsettings' );

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
		
		// Begin log selection fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-view-logs' ) );
		$htmlOut .= Xml::openElement( 'div', array( 'id' => 'cn-log-switcher' ) );
		$title = SpecialPage::getTitleFor( 'CentralNoticeLogs' );
		$fullUrl = $title->getFullUrl();
		
		$htmlOut .= Xml::radio( 
			'log_type',
			'campaign',
			( $this->logType == 'campaignsettings' ? true : false ),
			array( 'onclick' => "switchLogs( '".$fullUrl."', 'campaignsettings' )" )
		);
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-campaign-settings' ), 'campaign' );
		
		$htmlOut .= Xml::radio(
			'log_type',
			'banner',
			( $this->logType == 'bannersettings' ? true : false ),
			array( 'onclick' => "switchLogs( '".$fullUrl."', 'bannersettings' )" )
		);
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-banner-settings' ), 'banner' );
		
		$htmlOut .= Xml::closeElement( 'div' );
		$htmlOut .= Xml::closeElement( 'form' );
		
		// End log selection fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
		
		$this->showLog( $this->logType );

		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a log of changes.
	 * @param $logType string: which type of log to show
	 */
	function showLog( $logType ) {
		global $wgOut;
		
		if ( $logType == 'bannersettings' ) {
			$pager = new CentralNoticeBannerLogPager( $this );
		} else {
			$pager = new CentralNoticeLogPager( $this );
		}
		
		$htmlOut = '';
		
		// Begin log fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		// Show paginated list of log entries
		$htmlOut .= Xml::tags( 'div', 
			array( 'class' => 'cn-pager' ), 
			$pager->getNavigationBar() );
		$htmlOut .= $pager->getBody();
		$htmlOut .= Xml::tags( 'div', 
			array( 'class' => 'cn-pager' ), 
			$pager->getNavigationBar() );
		
		// End log fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}

}
