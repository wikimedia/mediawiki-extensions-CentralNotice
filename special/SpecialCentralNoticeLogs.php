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
		
		if ( $wgRequest->wasPosted() ) {
			$this->logType = $wgRequest->getText( 'log', 'campaignsettings' );
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
		
		// Begin log selection fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-view-logs' ) );
		$htmlOut .= Xml::closeElement( 'form' );
		
		// End log selection fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
		
		$this->showLog( $this->logType );

		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a log.
	 */
	function showLog( $logType ) {
		global $wgOut, $wgLang;
		
		$htmlOut = '';
		
		// Begin log fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$campaignLogs = $this->getCampaignLogs();
		
		foreach( $campaignLogs as $campaignLog ) {
			$htmlOut .= Xml::openElement( 'div', array( 'class' => 'cn-log-entry' ) );
			
			// Create a user object so we can pull the name, user page link, etc.
			$loggedUser = User::newFromId( $campaignLog['user_id'] );
			
			$htmlOut .= Xml::tags( 'span', null,
				$wgLang->date( $campaignLog['timestamp'] ) . ' ' // date
				. $wgLang->time( $campaignLog['timestamp'] ) . ' ' // time
				. $loggedUser->getName() . ' ' // user
				. $campaignLog['action'] . ' '  // action
				. $campaignLog['campaign_name']. ' '. strtolower( wfMsg( 'centralnotice-notice' ) ) // campaign
			);
			
			$htmlOut .= Xml::closeElement( 'div', array( 'class' => 'cn-log-entry' ) );
		}
		
		// End log fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}
	
	function getCampaignLogs() {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$logs = array();

		$results = $dbr->select( 'cn_notice_log', 
			array(
				'notlog_timestamp',
				'notlog_user_id',
				'notlog_action',
				'notlog_not_id',
				'notlog_not_name',
			),
			null,
			__METHOD__,
			array( 'ORDER BY' => 'notlog_timestamp DESC' )
		);
		foreach ( $results as $row ) {
			$logs[] = array(
				'timestamp' => $row->notlog_timestamp,
				'user_id' => $row->notlog_user_id,
				'action' => $row->notlog_action,
				'campaign_id' => $row->notlog_not_id,
				'campaign_name' => $row->notlog_not_name,
			);
		}
		return $logs;
	}

}
