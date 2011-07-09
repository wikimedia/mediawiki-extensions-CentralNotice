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
		global $wgOut;
		
		$pager = new CentralNoticeLogPager( $this );
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

class CentralNoticeLogPager extends ReverseChronologicalPager {
	var $viewPage, $special;

	function __construct( $special ) {
		$this->special = $special;
		parent::__construct();
		
		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffset( 20, '' );
		$this->mLimitsShown = array( 20, 50, 100 );
		
		$this->viewPage = SpecialPage::getTitleFor( 'CentralNotice' );
	}
	
	/**
	 * Sort the log list by timestamp
	 */
	function getIndexField() {
		return 'notlog_timestamp';
	}
	
	/**
	 * Pull log entries from the database
	 */
	function getQueryInfo() {
		return array(
			'tables' => array( 'cn_notice_log' ),
			'fields' => array(
				'notlog_timestamp',
				'notlog_user_id',
				'notlog_action',
				'notlog_not_id',
				'notlog_not_name',
			)
		);
	}
	
	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 */
	function formatRow( $row ) {
		global $wgLang;
		
		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->notlog_user_id );
		// Create the user page link
		$userLink = $this->getSkin()->makeLinkObj( $loggedUser->getUserPage(), 
			$loggedUser->getName() );
		
		// Create the campaign link
		$campaignLink = $this->getSkin()->makeLinkObj( $this->viewPage,
			htmlspecialchars( $row->notlog_not_name ),
			'method=listNoticeDetail&notice=' . urlencode( $row->notlog_not_name ) );
				
		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );
		
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$wgLang->date( $row->notlog_timestamp ) . ' ' . $wgLang->time( $row->notlog_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$userLink
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$row->notlog_action
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$campaignLink
		);
		
		// End banner row
		$htmlOut .= Xml::closeElement( 'tr' );
		
		return $htmlOut;
	}
	
	/**
	 * Specify table headers
	 */
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'id' => 'cn-campaign-logs', 'cellpadding' => 4 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			 wfMsg ( 'centralnotice-timestamp' )
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			 wfMsg ( 'centralnotice-user' )
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			 wfMsg ( 'centralnotice-action' )
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			wfMsg ( 'centralnotice-notice' )
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}
	
	/**
	 * Close table
	 */
	function getEndBody() {
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		return $htmlOut;
	}
}
