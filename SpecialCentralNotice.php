<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class CentralNotice extends SpecialPage {
	var $editable, $centralNoticeError;
	
	function __construct() {
		// Register special page
		parent::__construct( 'CentralNotice' );
	}
	
	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgUser, $wgRequest, $wgExtensionAssetsPath;

		// Begin output
		$this->setHeaders();
		
		// Add style file to the output headers
		$wgOut->addExtensionStyle( "$wgExtensionAssetsPath/CentralNotice/centralnotice.css" );
		
		// Add script file to the output headers
		$wgOut->addScriptFile( "$wgExtensionAssetsPath/CentralNotice/centralnotice.js" );
		
		// Check permissions
		$this->editable = $wgUser->isAllowed( 'centralnotice-admin' );
		
		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$wgOut->addWikiText( wfMsg( 'centralnotice-summary' ) );

		// Show header
		$this->printHeader( $sub );
		
		// Begin Campaigns tab content
		$wgOut->addHTML( Xml::openElement( 'div', array( 'id' => 'preferences' ) ) );
		
		$method = $wgRequest->getVal( 'method' );
		
		// Switch to campaign detail interface if requested
		if ( $method == 'listNoticeDetail' ) {
			$notice = $wgRequest->getVal ( 'notice' );
			$this->listNoticeDetail( $notice );
			$wgOut->addHTML( Xml::closeElement( 'div' ) );
			return;
		}
		
		// Handle form submissions from "Manage campaigns" or "Add a campaign" interface
		if ( $this->editable && $wgRequest->wasPosted() ) {
		 
		 	// Check authentication token
		 	if ( $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {
				
				// Handle removing campaigns
				$toRemove = $wgRequest->getArray( 'removeNotices' );
				if ( $toRemove ) {
					// Remove campaigns in list
					foreach ( $toRemove as $notice ) {
						$this->removeNotice( $notice );
					}
	
					// Skip subsequent form handling and show list of campaigns
					$this->listNotices();
					$wgOut->addHTML( Xml::closeElement( 'div' ) );
					return;
				}

				// Handle locking/unlocking campaigns
				$lockedNotices = $wgRequest->getArray( 'locked' );
				if ( $lockedNotices ) {
					// Build list of campaigns to lock
					$unlockedNotices = array_diff( $this->getNoticesName(), $lockedNotices );

					// Set locked/unlocked flag accordingly
					foreach ( $lockedNotices as $notice ) {
						$this->updateLock( $notice, '1' );
					}
					foreach ( $unlockedNotices as $notice ) {
						$this->updateLock( $notice, '0' );
					}
				// Handle updates if no post content came through (all checkboxes unchecked)
				} elseif ( $method !== 'addNotice' ) {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						$this->updateLock( $notice, '0' );
					}
				}

				// Handle enabling/disabling campaigns
				$enabledNotices = $wgRequest->getArray( 'enabled' );
				if ( $enabledNotices ) {
					// Build list of campaigns to disable
					$disabledNotices = array_diff( $this->getNoticesName(), $enabledNotices );

					// Set enabled/disabled flag accordingly
					foreach ( $enabledNotices as $notice ) {
						$this->updateEnabled( $notice, '1' );
					}
					foreach ( $disabledNotices as $notice ) {
						$this->updateEnabled( $notice, '0' );
					}
				// Handle updates if no post content came through (all checkboxes unchecked)
				} elseif ( $method !== 'addNotice' ) {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						$this->updateEnabled( $notice, '0' );
					}
				}

				// Handle setting preferred campaigns
				$preferredNotices = $wgRequest->getArray( 'preferred' );
				if ( $preferredNotices ) {
					// Build list of campaigns to unset 
					$unsetNotices = array_diff( $this->getNoticesName(), $preferredNotices );

					// Set flag accordingly
					foreach ( $preferredNotices as $notice ) {
						$this->updatePreferred( $notice, '1' );
					}
					foreach ( $unsetNotices as $notice ) {
						$this->updatePreferred( $notice, '0' );
					}
				// Handle updates if no post content came through (all checkboxes unchecked)
				} elseif ( $method !== 'addNotice' ) {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						$this->updatePreferred( $notice, '0' );
					}
				}

				// Handle adding of campaign
				if ( $method == 'addNotice' ) {
					$noticeName        = $wgRequest->getVal( 'noticeName' );
					$start             = $wgRequest->getArray( 'start' );
					$projects          = $wgRequest->getArray( 'projects' );
					$project_languages = $wgRequest->getArray( 'project_languages' );
					$geotargeted       = $wgRequest->getCheck( 'geotargeted' );
					$geo_countries     = $wgRequest->getArray( 'geo_countries' );
					if ( $noticeName == '' ) {
						$this->showError( 'centralnotice-null-string' );
					} else {
						$this->addNotice( $noticeName, '0', $start, $projects,
							$project_languages, $geotargeted, $geo_countries );
					}
				}
				
				// If there were no errors, reload the page to prevent duplicate form submission
				if ( !$this->centralNoticeError ) {
					$wgOut->redirect( $this->getTitle()->getLocalUrl() );
					return;
				}
				
			} else {
				$this->showError( 'sessionfailure' );
			}

		}

		// Show list of campaigns
		$this->listNotices();
		
		// End Campaigns tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}

	public static function printHeader() {
		global $wgOut, $wgTitle, $wgUser;
		$sk = $wgUser->getSkin();

		$pages = array(
			'CentralNotice' => wfMsg( 'centralnotice-notices' ),
			'NoticeTemplate' => wfMsg ( 'centralnotice-templates' ),
			'BannerAllocation' => wfMsg ( 'centralnotice-allocation' )
		);
		$htmlOut = Xml::openElement( 'ul', array( 'id' => 'preftoc' ) );
		foreach ( $pages as $page => $msg ) {
			$title = SpecialPage::getTitleFor( $page );
			$attribs = array();
			if ( $wgTitle->equals( $title ) ) {
				$attribs['class'] = 'selected';
			}
			$htmlOut .= Xml::tags( 'li', $attribs,
				$sk->makeLinkObj( $title, htmlspecialchars( $msg ) )
			);
		}
		$htmlOut .= Xml::closeElement( 'ul' );

		$wgOut->addHTML( $htmlOut );
	}

	/**
	 * Get all the campaigns in the database
	 * @return an array of campaign names
	 */
	function getNoticesName() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_notices', 'not_name', null, __METHOD__ );
		$notices = array();
		foreach ( $res as $row ) {
			$notices[] = $row->not_name;
		}
		return $notices;
	}

	/**
	 * Build a table row. Needed since Xml::buildTableRow escapes all HTML.
	 */
	function tableRow( $fields, $element = 'td', $attribs = array() ) {
		$cells = array();
		foreach ( $fields as $field ) {
			$cells[] = Xml::tags( $element, array(), $field );
		}
		return Xml::tags( 'tr', $attribs, implode( "\n", $cells ) ) . "\n";
	}

	function dateSelector( $prefix, $timestamp = null ) {
		if ( $this->editable ) {
			// Default ranges...
			$years = range( 2008, 2014 );
			$months = range( 1, 12 );
			$months = array_map( array( $this, 'addZero' ), $months );
			$days = range( 1 , 31 );
			$days = array_map( array( $this, 'addZero' ), $days );
	
			// Normalize timestamp format. If no timestamp passed, defaults to now.
			$ts = wfTimestamp( TS_MW, $timestamp );
			
			$fields = array(
				array( "month", "centralnotice-month", $months, substr( $ts, 4, 2 ) ),
				array( "day",   "centralnotice-day",   $days,   substr( $ts, 6, 2 ) ),
				array( "year",  "centralnotice-year",  $years,  substr( $ts, 0, 4 ) ),
			);
	
			return $this->genSelector( $prefix, $fields );
		} else {
			global $wgLang;
			return $wgLang->date( $timestamp );
		}
	}

	function timeSelector( $prefix, $timestamp = null ) {
		if ( $this->editable ) {
			// Default ranges...
			$minutes = range( 0, 59 ); // formerly in 15-minute increments
			$minutes = array_map( array( $this, 'addZero' ), $minutes );
			$hours = range( 0 , 23 );
			$hours = array_map( array( $this, 'addZero' ), $hours );
	
			// Normalize timestamp format...
			$ts = wfTimestamp( TS_MW, $timestamp );
	
			$fields = array(
				array( "hour", "centralnotice-hours", $hours,   substr( $ts, 8, 2 ) ),
				array( "min",  "centralnotice-min",   $minutes, substr( $ts, 10, 2 ) ),
			);
	
			return $this->genSelector( $prefix, $fields );
		} else {
			global $wgLang;
			return $wgLang->time( $timestamp );
		}
	}

	private function genSelector( $prefix, $fields ) {
		$out = '';
		foreach ( $fields as $data ) {
			list( $field, $label, $set, $current ) = $data;
			$out .= Xml::listDropDown( "{$prefix}[{$field}]",
				$this->dropDownList( wfMsg( $label ), $set ),
				'',
				$current );
		}
		return $out;
	}

	/**
	 * Show all campaigns found in the database, show "Add a campaign" form
	 */
	function listNotices() {
		global $wgOut, $wgUser, $wgLang, $wgRequest, $wgNoticeProjects;

		// Get connection
		$dbr = wfGetDB( DB_SLAVE );
		$sk = $wgUser->getSkin();
		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		// Get all campaigns from the database
		$res = $dbr->select( 'cn_notices',
			array(
				'not_name',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked'
			),
			null,
			__METHOD__,
			array( 'ORDER BY' => 'not_id DESC' )
		);
		
		// Begin building HTML
		$htmlOut = '';
		
		// Begin Manage campaigns fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		// If there are campaigns to show...
		if ( $dbr->numRows( $res ) >= 1 ) {
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			}
			$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-manage' ) );
			
			// Begin table of campaigns
			$htmlOut .= Xml::openElement( 'table',
				array(
					'cellpadding' => 9,
					'width' => '100%',
					'class' => 'wikitable sortable'
				)
			);
	
			// Table headers
			$headers = array(
				wfMsgHtml( 'centralnotice-notice-name' ),
				wfMsgHtml( 'centralnotice-projects' ),
				wfMsgHtml( 'centralnotice-languages' ),
				wfMsgHtml( 'centralnotice-start-date' ),
				wfMsgHtml( 'centralnotice-end-date' ),
				wfMsgHtml( 'centralnotice-enabled' ),
				wfMsgHtml( 'centralnotice-preferred' ),
				wfMsgHtml( 'centralnotice-locked' ),
			);
			if ( $this->editable ) {
				$headers[] = wfMsgHtml( 'centralnotice-remove' );
			}
			$htmlOut .= $this->tableRow( $headers, 'th' );
	
			// Table rows
			foreach ( $res as $row ) {
				$fields = array();

				// Name
				$fields[] = $sk->makeLinkObj( $this->getTitle(),
						htmlspecialchars( $row->not_name ),
						'method=listNoticeDetail&notice=' . urlencode( $row->not_name ) );

				// Projects
				$projects = $this->getNoticeProjects( $row->not_name );
				$project_count = count( $projects );
				$projectList = '';
				if ( $project_count > 1 ) {
					if ( $project_count == count( $wgNoticeProjects ) ) {
						$projectList = wfMsg ( 'centralnotice-all-projects' );
					} else {
						$projectList = wfMsg ( 'centralnotice-multiple', $project_count );
					}
				} elseif ( $project_count > 0 ) {
					$projectList = $wgLang->commaList( $projects );
				}
				$fields[] = $projectList;

				// Languages
				$project_langs = $this->getNoticeLanguages( $row->not_name );
				$language_count = count( $project_langs );
				$languageList = '';
				if ( $language_count > 3 ) {
					$languageList = wfMsg ( 'centralnotice-multiple', $language_count );
				} elseif ( $language_count > 0 ) {
					$languageList = $wgLang->commaList( $project_langs );
				}
				$fields[] = $languageList;

				// Date and time calculations
				$start_timestamp = wfTimestamp( TS_MW, $row->not_start );
				$start_year = substr( $start_timestamp, 0 , 4 );
				$start_month = substr( $start_timestamp, 4, 2 );
				$start_day = substr( $start_timestamp, 6, 2 );
				$start_hour = substr( $start_timestamp, 8, 2 );
				$start_min = substr( $start_timestamp, 10, 2 );
				$end_timestamp = wfTimestamp( TS_MW, $row->not_end );
				$end_year = substr( $end_timestamp, 0 , 4 );
				$end_month = substr( $end_timestamp, 4, 2 );
				$end_day = substr( $end_timestamp, 6, 2 );
				$end_hour = substr( $end_timestamp, 8, 2 );
				$end_min = substr( $end_timestamp, 10, 2 );

				// Start
				$fields[] = "{$start_year}/{$start_month}/{$start_day} {$start_hour}:{$start_min}";

				// End
				$fields[] = "{$end_year}/{$end_month}/{$end_day} {$end_hour}:{$end_min}";

				// Enabled
				$fields[] =
					Xml::check( 'enabled[]', ( $row->not_enabled == '1' ),
					wfArrayMerge( $readonly,
						array( 'value' => $row->not_name, 'class' => 'noshiftselect' ) ) );

				// Preferred
				$fields[] =
				Xml::check( 'preferred[]', ( $row->not_preferred == '1' ),
				wfArrayMerge( $readonly,
				array( 'value' => $row->not_name, 'class' => 'noshiftselect' ) ) );

				// Locked
				$fields[] =
					Xml::check( 'locked[]', ( $row->not_locked == '1' ),
					wfArrayMerge( $readonly,
						array( 'value' => $row->not_name, 'class' => 'noshiftselect' ) ) );

				if ( $this->editable ) {
					// Remove
					$fields[] = Xml::check( 'removeNotices[]', false,
						array( 'value' => $row->not_name, 'class' => 'noshiftselect' ) );
				}
				
				// If campaign is currently active, set special class on table row.
				$attribs = array();
				if ( wfTimestamp() > wfTimestamp( TS_UNIX , $row->not_start ) 
					&& wfTimestamp() < wfTimestamp( TS_UNIX , $row->not_end ) 
					&& $row->not_enabled == '1' ) 
				{
					$attribs = array( 'class' => 'cn-active-campaign' );
				}
				
				$htmlOut .= $this->tableRow( $fields, 'td', $attribs );
			}
			// End table of campaigns
			$htmlOut .= Xml::closeElement( 'table' );
			
			if ( $this->editable ) {
				$htmlOut .= Html::hidden( 'authtoken', $wgUser->editToken() );
				$htmlOut .= Xml::openElement( 'div', array( 'class' => 'cn-buttons' ) );
				$htmlOut .= Xml::submitButton( wfMsg( 'centralnotice-modify' ),
					array(
						'id' => 'centralnoticesubmit',
						'name' => 'centralnoticesubmit'
					)
				);
				$htmlOut .= Xml::closeElement( 'div' );
				$htmlOut .= Xml::closeElement( 'form' );
			}

		// No campaigns to show
		} else {
			$htmlOut .= wfMsg( 'centralnotice-no-notices-exist' );
		}
		
		// End Manage Campaigns fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		if ( $this->editable ) {
		
			// If there was an error, we'll need to restore the state of the form
			if ( $wgRequest->wasPosted() && ( $wgRequest->getVal( 'method' ) == 'addNotice' ) ) {
				$startArray = $wgRequest->getArray( 'start' );
				$startTimestamp = $startArray['year'] .
					$startArray['month'] .
					$startArray['day'] .
					$startArray['hour'] .
					$startArray['min'] . '00'
				;
				$noticeProjects = $wgRequest->getArray( 'projects', array() );
				$noticeLanguages = $wgRequest->getArray( 'project_languages', array() );
			} else { // Defaults
				$startTimestamp = null;
				$noticeProjects = array();
				$noticeLanguages = array();
			}
		
			// Begin Add a campaign fieldset
			$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
			// Form for adding a campaign
			$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-add-notice' ) );
			$htmlOut .= Html::hidden( 'title', $this->getTitle()->getPrefixedText() );
			$htmlOut .= Html::hidden( 'method', 'addNotice' );
	
			$htmlOut .= Xml::openElement( 'table', array ( 'cellpadding' => 9 ) );
			
			// Name
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-notice-name' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::input( 'noticeName', 25, $wgRequest->getVal( 'noticeName' ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $startTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-time' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->timeSelector( 'start', $startTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Project
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				wfMsgHtml( 'centralnotice-projects' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->projectMultiSelector( $noticeProjects ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Languages
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 
				wfMsgHtml( 'centralnotice-languages' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				$this->languageMultiSelector( $noticeLanguages ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Countries
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::label( wfMsg( 'centralnotice-geotargeted' ), 'geotargeted' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::check( 'geotargeted', false, 
					wfArrayMerge( $readonly, array( 'value' => 1, 'id' => 'geotargeted' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr', 
				array( 'id'=>'geoMultiSelector', 'style'=>'display:none;' ) );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 
				wfMsgHtml( 'centralnotice-countries' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->geoMultiSelector() );
			$htmlOut .= Xml::closeElement( 'tr' );
			
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Html::hidden( 'change', 'weight' );
			$htmlOut .= Html::hidden( 'authtoken', $wgUser->editToken() );
			
			// Submit button
			$htmlOut .= Xml::tags( 'div', 
				array( 'class' => 'cn-buttons' ), 
				Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) 
			);
			
			$htmlOut .= Xml::closeElement( 'form' );
			
			// End Add a campaign fieldset
			$htmlOut .= Xml::closeElement( 'fieldset' );
		}
		
		// Output HTML
		$wgOut->addHTML( $htmlOut );
	}

	/**
	 * Show the interface for viewing/editing an individual campaign
	 * @param $notice The name of the campaign to view
	 */
	function listNoticeDetail( $notice ) {
		global $wgOut, $wgRequest, $wgUser;
		
		// Make sure notice exists
		if ( !$this->noticeExists( $notice ) ) {
			$this->showError( 'centralnotice-notice-doesnt-exist' );
		} else {

			// Handle form submissions from campaign detail interface
			if ( $this->editable && $wgRequest->wasPosted() ) {
				
				// Check authentication token
				if ( $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {
				
					// Handle removing campaign
					if ( $wgRequest->getVal( 'remove' ) ) {
						$this->removeNotice( $notice );
						if ( !$this->centralNoticeError ) {
							// Leave campaign detail interface
							$wgOut->redirect( $this->getTitle()->getLocalUrl() );
							return;
						}
					}
					
					// Handle locking/unlocking campaign
					if ( $wgRequest->getCheck( 'locked' ) ) {
						$this->updateLock( $notice, '1' );
					} else {
						$this->updateLock( $notice, 0 );
					}
					
					// Handle enabling/disabling campaign
					if ( $wgRequest->getCheck( 'enabled' ) ) {
						$this->updateEnabled( $notice, '1' );
					} else {
						$this->updateEnabled( $notice, 0 );
					}
					
					// Handle setting campaign to preferred/not preferred
					if ( $wgRequest->getCheck( 'preferred' ) ) {
						$this->updatePreferred( $notice, '1' );
					} else {
						$this->updatePreferred( $notice, 0 );
					}
					
					// Handle updating geotargeting
					if ( $wgRequest->getCheck( 'geotargeted' ) ) {
						$this->updateGeotargeted( $notice, 1 );
						$countries = $wgRequest->getArray( 'geo_countries' );
						if ( $countries ) {
							$this->updateCountries( $notice, $countries );
						}
					} else {
						$this->updateGeotargeted( $notice, 0 );
					}
					
					// Handle updating the start and end settings
					$start = $wgRequest->getArray( 'start' );
					$end = $wgRequest->getArray( 'end' );
					if ( $start && $end ) {
						$updatedStart = sprintf( "%04d%02d%02d%02d%02d00",
							$start['year'],
							$start['month'],
							$start['day'],
							$start['hour'],
							$start['min']
						);
						$updatedEnd = sprintf( "%04d%02d%02d%02d%02d00",
							$end['year'],
							$end['month'],
							$end['day'],
							$end['hour'],
							$end['min']
						);
						
						$this->updateNoticeDate( $notice, $updatedStart, $updatedEnd );
					}
				
					// Handle adding of banners to the campaign
					$templatesToAdd = $wgRequest->getArray( 'addTemplates' );
					if ( $templatesToAdd ) {
						$weight = $wgRequest->getArray( 'weight' );
						foreach ( $templatesToAdd as $templateName ) {
							$templateId = $this->getTemplateId( $templateName );
							$this->addTemplateTo( $notice, $templateName, $weight[$templateId] );
						}
					}
			
					// Handle removing of banners from the campaign
					$templateToRemove = $wgRequest->getArray( 'removeTemplates' );
					if ( $templateToRemove ) {
						foreach ( $templateToRemove as $template ) {
							$this->removeTemplateFor( $notice, $template );
						}
					}
					
					// Handle weight changes
					$updatedWeights = $wgRequest->getArray( 'weight' );
					if ( $updatedWeights ) {
						foreach ( $updatedWeights as $templateId => $weight ) {
							$this->updateWeight( $notice, $templateId, $weight );
						}
					}
		
					// Handle new projects
					$projects = $wgRequest->getArray( 'projects' );
					if ( $projects ) {
						$this->updateProjects( $notice, $projects );
					}
		
					// Handle new project languages
					$projectLangs = $wgRequest->getArray( 'project_languages' );
					if ( $projectLangs ) {
						$this->updateProjectLanguages( $notice, $projectLangs );
					}
					
					// If there were no errors, reload the page to prevent duplicate form submission
					if ( !$this->centralNoticeError ) {
						$wgOut->redirect( $this->getTitle()->getLocalUrl( 
							"method=listNoticeDetail&notice=$notice" ) );
						return;
					}
				} else {
					$this->showError( 'sessionfailure' );
				}
				
			}
		
			$htmlOut = '';
			
			// Begin Campaign detail fieldset
			$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
			
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form',
					array(
						'method' => 'post',
						'action' => $this->getTitle()->getLocalUrl( 
							"method=listNoticeDetail&notice=$notice" )
					)
				);
			}
	
			$output_detail = $this->noticeDetailForm( $notice );
			$output_assigned = $this->assignedTemplatesForm( $notice );
			$output_templates = $this->addTemplatesForm( $notice );
	
			$htmlOut .= $output_detail;
		
			// Catch for no banners so that we don't double message
			if ( $output_assigned == '' && $output_templates == '' ) {
				$htmlOut .= wfMsg( 'centralnotice-no-templates' );
				$htmlOut .= Xml::element( 'p' );
				$newPage = $this->getTitleFor( 'NoticeTemplate', 'add' );
				$sk = $wgUser->getSkin();
				$htmlOut .= $sk->makeLinkObj( $newPage, wfMsgHtml( 'centralnotice-add-template' ) );
				$htmlOut .= Xml::element( 'p' );
			} elseif ( $output_assigned == '' ) {
				$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-assigned-templates' ) );
				$htmlOut .= wfMsg( 'centralnotice-no-templates-assigned' );
				$htmlOut .= Xml::closeElement( 'fieldset' );
				if ( $this->editable ) {
					$htmlOut .= $output_templates;
				}
			} else {
				$htmlOut .= $output_assigned;
				if ( $this->editable ) {
					$htmlOut .= $output_templates;
				}
			}
			if ( $this->editable ) {
				 $htmlOut .= Html::hidden( 'authtoken', $wgUser->editToken() );
				
				// Submit button
				$htmlOut .= Xml::tags( 'div', 
					array( 'class' => 'cn-buttons' ), 
					Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) 
				);
			}
	
			if ( $this->editable ) {
				$htmlOut .= Xml::closeElement( 'form' );
			}
			$htmlOut .= Xml::closeElement( 'fieldset' );
			$wgOut->addHTML( $htmlOut );
		}
	}
	
	/**
	 * Create form for managing campaign settings (start date, end date, languages, etc.)
	 */
	function noticeDetailForm( $notice ) {
		global $wgRequest;
		
		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}
		$dbr = wfGetDB( DB_SLAVE );

		// Get campaign info from database
		$row = $dbr->selectRow( 'cn_notices',
			array(
				'not_id',
				'not_name',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked',
				'not_geo'
			),
			array( 'not_name' => $notice ),
			__METHOD__
		);
		
		if ( $row ) {
		
			// If there was an error, we'll need to restore the state of the form
			if ( $wgRequest->wasPosted() ) {
				$startArray = $wgRequest->getArray( 'start' );
				$startTimestamp = $startArray['year'] .
					$startArray['month'] .
					$startArray['day'] .
					$startArray['hour'] .
					$startArray['min'] . '00'
				;
				$endArray = $wgRequest->getArray( 'end' );
				$endTimestamp = $endArray['year'] .
					$endArray['month'] .
					$endArray['day'] .
					$endArray['hour'] .
					$endArray['min'] .'00'
				;
				$isEnabled = $wgRequest->getCheck( 'enabled' );
				$isPreferred = $wgRequest->getCheck( 'preferred' );
				$isLocked = $wgRequest->getCheck( 'locked' );
				$noticeProjects = $wgRequest->getArray( 'projects', array() );
				$noticeLanguages = $wgRequest->getArray( 'project_languages', array() );
				$isGeotargeted = $wgRequest->getCheck( 'geotargeted' );
				$countries = $wgRequest->getArray( 'geo_countries', array() );
			} else { // Defaults
				$startTimestamp = $row->not_start;
				$endTimestamp = $row->not_end;
				$isEnabled = ( $row->not_enabled == '1' );
				$isPreferred = ( $row->not_preferred == '1' );
				$isLocked = ( $row->not_locked == '1' );
				$noticeProjects = $this->getNoticeProjects( $notice );
				$noticeLanguages = $this->getNoticeLanguages( $notice );
				$isGeotargeted = ( $row->not_geo == '1' );
				$countries = $this->getNoticeCountries( $notice );
			}
		
			// Build Html
			$htmlOut = '';
			$htmlOut .= Xml::tags( 'h2', null, wfMsg( 'centralnotice-notice-heading', $notice ) );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

			// Rows
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $startTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-time' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->timeSelector( 'start', $startTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// End Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-end-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'end', $endTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// End Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-end-time' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->timeSelector( 'end', $endTimestamp ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Project
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 
				wfMsgHtml( 'centralnotice-projects' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				$this->projectMultiSelector( $noticeProjects ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Languages
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 
				wfMsgHtml( 'centralnotice-languages' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				$this->languageMultiSelector( $noticeLanguages ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Countries
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::label( wfMsg( 'centralnotice-geotargeted' ), 'geotargeted' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::check( 'geotargeted', $isGeotargeted, 
					wfArrayMerge( 
						$readonly, 
						array( 'value' => $row->not_name, 'id' => 'geotargeted' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			if ( $isGeotargeted ) {
				$htmlOut .= Xml::openElement( 'tr', array( 'id'=>'geoMultiSelector' ) );
			} else {
				$htmlOut .= Xml::openElement( 'tr', 
					array( 'id'=>'geoMultiSelector', 'style'=>'display:none;' ) );
			}
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), 
				wfMsgHtml( 'centralnotice-countries' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->geoMultiSelector( $countries ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Enabled
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::label( wfMsg( 'centralnotice-enabled' ), 'enabled' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::check( 'enabled', $isEnabled, 
					wfArrayMerge( $readonly, 
						array( 'value' => $row->not_name, 'id' => 'enabled' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Preferred
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::label( wfMsg( 'centralnotice-preferred' ), 'preferred' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::check( 'preferred', $isPreferred, 
					wfArrayMerge( $readonly, 
						array( 'value' => $row->not_name, 'id' => 'preferred' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Locked
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::label( wfMsg( 'centralnotice-locked' ), 'locked' ) );
			$htmlOut .= Xml::tags( 'td', array(), 
				Xml::check( 'locked', $isLocked, 
					wfArrayMerge( $readonly, 
						array( 'value' => $row->not_name, 'id' => 'locked' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			if ( $this->editable ) {
				// Locked
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), 
					Xml::label( wfMsg( 'centralnotice-remove' ), 'remove' ) );
				$htmlOut .= Xml::tags( 'td', array(), 
					Xml::check( 'remove', false, 
						array( 'value' => $row->not_name, 'id' => 'remove' ) ) );
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );
			return $htmlOut;
		} else {
			return '';
		}
	}

	/**
	 * Create form for managing banners assigned to a campaign
	 */
	function assignedTemplatesForm( $notice ) {
		global $wgUser;
		$sk = $wgUser->getSkin();

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array(
				'cn_notices',
				'cn_assignments',
				'cn_templates'
			),
			array(
				'cn_templates.tmp_id',
				'cn_templates.tmp_name',
				'cn_assignments.tmp_weight'
			),
			array(
				'cn_notices.not_name' => $notice,
				'cn_notices.not_id = cn_assignments.not_id',
				'cn_assignments.tmp_id = cn_templates.tmp_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'cn_notices.not_id' )
		);

		// No banners found
		if ( $dbr->numRows( $res ) < 1 ) {
			return;
		}

		// Build Assigned banners HTML
		$htmlOut  = Html::hidden( 'change', 'weight' );
		$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-assigned-templates' ) );
		$htmlOut .= Xml::openElement( 'table',
			array(
				'cellpadding' => 9,
				'width' => '100%'
			)
		);
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				 wfMsg ( "centralnotice-remove" ) );
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
			 wfMsg ( "centralnotice-weight" ) );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '70%' ),
			 wfMsg ( "centralnotice-templates" ) );

		// Table rows
		foreach( $res as $row ) {

			$htmlOut .= Xml::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
					Xml::check( 'removeTemplates[]', false, array( 'value' => $row->tmp_name ) )
				);
			}

			// Weight
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->weightDropDown( "weight[$row->tmp_id]", $row->tmp_weight )
			);

			$viewPage = $this->getTitleFor( 'NoticeTemplate', 'view' );
			$render = new SpecialBannerLoader();
			$render->siteName = 'Wikipedia';
			global $wgRequest;
			$render->language = $wgRequest->getVal( 'wpUserLanguage' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$sk->makeLinkObj( $viewPage,
					htmlspecialchars( $row->tmp_name ),
					'template=' . urlencode( $row->tmp_name ) ) .
				Xml::fieldset( wfMsg( 'centralnotice-preview' ),
					$render->getHtmlNotice( $row->tmp_name ),
					array( 'class' => 'cn-bannerpreview')
				)
			);

			$htmlOut .= Xml::closeElement( 'tr' );
		}
		$htmlOut .= XMl::closeElement( 'table' );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		return $htmlOut;

	}
	
	function weightDropDown( $name, $selected ) {
		if ( $this->editable ) {
			return Xml::listDropDown( $name,
				$this->dropDownList( wfMsg( 'centralnotice-weight' ),
				range ( 0, 100, 5 ) ),
				'',
				$selected,
				'',
				1 );
		} else {
			return htmlspecialchars( $selected );
		}
	}

	/**
	 * Create form for adding banners to a campaign
	 */
	function addTemplatesForm( $notice ) {
		$pager = new CentralNoticePager( $this );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_templates', 'tmp_name', '', '', array( 'ORDER BY' => 'tmp_id' ) );
		
		if ( $dbr->numRows( $res ) > 0 ) {
			// Build HTML
			$htmlOut  = Xml::fieldset( wfMsg( "centralnotice-available-templates" ) );
			
			// Show paginated list of banners
			$htmlOut .= Xml::tags( 'div', 
				array( 'class' => 'cn-pager' ), 
				$pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div', 
				array( 'class' => 'cn-pager' ), 
				$pager->getNavigationBar() );
			
			$htmlOut .= Xml::closeElement( 'fieldset' );
		} else {
			// Nothing found
			return;
		}
		return $htmlOut;
	}

	/**
	 * Lookup function for active banners under a given language/project/location. This function is 
	 * called by SpecialBannerListLoader::getJsonList() in order to build the banner list JSON for
	 * each project.
	 * @return A 2D array of running banners with associated weights and settings
	 */
	static function selectNoticeTemplates( $project, $language, $location = null ) {
		global $wgCentralDBname;
		
		$campaigns = array();
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		
		// Pull non-geotargeted campaigns
		$campaignResults1 = $dbr->select(
			array(
				'cn_notices',
				'cn_notice_languages'
			),
			array(
				'not_id'
			),
			array(
				"not_start <= $encTimestamp",
				"not_end >= $encTimestamp",
				'not_enabled = 1', // enabled
				'not_geo = 0', // not geotargeted
				'nl_notice_id = cn_notices.not_id',
				'nl_language' => $language,
				'not_project' => array( '', $project )
			),
			__METHOD__
		);
		foreach ( $campaignResults1 as $row ) {
			$campaigns[] = $row->not_id;
		}
		if ( $location ) {
		
			// Normalize location parameter (should be an uppercase 2-letter country code)
			preg_match( '/[a-zA-Z][a-zA-Z]/', $location, $matches );
			if ( $matches ) {
				$location = strtoupper( $matches[0] );
			
				// Pull geotargeted campaigns
				$campaignResults2 = $dbr->select(
					array(
						'cn_notices',
						'cn_notice_languages',
						'cn_notice_countries'
					),
					array(
						'not_id'
					),
					array(
						"not_start <= $encTimestamp",
						"not_end >= $encTimestamp",
						'not_enabled = 1', // enabled
						'not_geo = 1', // geotargeted
						'nc_notice_id = cn_notices.not_id',
						'nc_country' => $location,
						'nl_notice_id = cn_notices.not_id',
						'nl_language' => $language,
						'not_project' => array( '', $project )
					),
					__METHOD__
				);
				foreach ( $campaignResults2 as $row ) {
					$campaigns[] = $row->not_id;
				}
			}
		}
		
		$templates = array();
		if ( $campaigns ) {
			// Pull all banners assigned to the campaigns
			$templates = CentralNoticeDB::selectTemplatesAssigned( $campaigns );
		}
		return $templates;
	}

	function addNotice( $noticeName, $enabled, $start, $projects, 
		$project_languages, $geotargeted, $geo_countries ) 
	{
		if ( $this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-notice-exists' );
			return;
		} elseif ( empty( $projects ) ) {
			$this->showError( 'centralnotice-no-project' );
			return;
		} elseif ( empty( $project_languages ) ) {
			$this->showError( 'centralnotice-no-language' );
			return;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$start['hour'] = substr( $start['hour'], 0 , 2 );
			$start['min'] = substr( $start['min'], 0 , 2 );
			if ( $start['month'] == 12 ) {
				$end['month'] = '01';
				$end['year'] = ( $start['year'] + 1 );
			} elseif ( $start['month'] == '09' ) {
				$end['month'] = '10';
				$end['year'] = $start['year'];
			} else {
				$end['month'] = 
					( substr( $start['month'], 0, 1 ) ) == 0 
					? 0 . ( intval( $start['month'] ) + 1 ) 
					: ( $start['month'] + 1 );
				$end['year'] = $start['year'];
			}

			$startTs = wfTimeStamp( TS_MW, "{$start['year']}:{$start['month']}:{$start['day']} " . 
				"{$start['hour']}:{$start['min']}:00" );
			$endTs = wfTimeStamp( TS_MW, "{$end['year']}:{$end['month']}:{$start['day']} " .
				"{$start['hour']}:{$start['min']}:00" );

			$res = $dbw->insert( 'cn_notices',
				array( 'not_name' => $noticeName,
					'not_enabled' => $enabled,
					'not_start' => $dbw->timestamp( $startTs ),
					'not_end' => $dbw->timestamp( $endTs ),
					'not_geo' => $geotargeted
				)
			);
			$not_id = $dbw->insertId();
			
			// Do multi-row insert for campaign projects
			$insertArray = array();
			foreach( $projects as $project ) {
				$insertArray[] = array( 'np_notice_id' => $not_id, 'np_project' => $project );
			}
			$res = $dbw->insert( 'cn_notice_projects', $insertArray, 
				__METHOD__, array( 'IGNORE' ) );
				
			// Do multi-row insert for campaign languages
			$insertArray = array();
			foreach( $project_languages as $code ) {
				$insertArray[] = array( 'nl_notice_id' => $not_id, 'nl_language' => $code );
			}
			$res = $dbw->insert( 'cn_notice_languages', $insertArray, 
				__METHOD__, array( 'IGNORE' ) );
			
			if ( $geotargeted ) {
				// Do multi-row insert for campaign countries
				$insertArray = array();
				foreach( $geo_countries as $code ) {
					$insertArray[] = array( 'nc_notice_id' => $not_id, 'nc_country' => $code );
				}
				$res = $dbw->insert( 'cn_notice_countries', $insertArray, 
					__METHOD__, array( 'IGNORE' ) );
			}
		
			$dbw->commit();
			return;
		}
	}

	function removeNotice( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			array( 'not_name' => $noticeName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			$this->showError( 'centralnotice-remove-notice-doesnt-exist' );
			return;
		}
		$row = $dbr->fetchObject( $res );
		if ( $row->not_locked == '1' ) {
			$this->showError( 'centralnotice-notice-is-locked' );
			return;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$noticeId = htmlspecialchars( $this->getNoticeId( $noticeName ) );
			$res = $dbw->delete( 'cn_assignments',  array ( 'not_id' => $noticeId ) );
			$res = $dbw->delete( 'cn_notices', array ( 'not_name' => $noticeName ) );
			$res = $dbw->delete( 'cn_notice_languages', array ( 'nl_notice_id' => $noticeId ) );
			$dbw->commit();
			return;
		}
	}

	function addTemplateTo( $noticeName, $templateName, $weight ) {
		$dbr = wfGetDB( DB_SLAVE );

		$eNoticeName = htmlspecialchars ( $noticeName );
		$noticeId = $this->getNoticeId( $eNoticeName );
		$templateId = $this->getTemplateId( $templateName );
		$res = $dbr->select( 'cn_assignments', 'asn_id',
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
		if ( $dbr->numRows( $res ) > 0 ) {
			$this->showError( 'centralnotice-template-already-exists' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$noticeId = $this->getNoticeId( $eNoticeName );
			$res = $dbw->insert( 'cn_assignments',
				array(
					'tmp_id' => $templateId,
					'tmp_weight' => $weight,
					'not_id' => $noticeId
				)
			);
			$dbw->commit();
		}
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	public static function getNoticeId( $noticeName ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 $eNoticeName = htmlspecialchars( $noticeName );
		 $row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		 if ( $row ) {
		 	return $row->not_id;
		 } else {
		 	return null;
		 }
	}
	
	function getNoticeProjects( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$projects = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_projects', 'np_project', 
				array( 'np_notice_id' => $row->not_id ) );
			foreach ( $res as $projectRow ) {
				$projects[] = $projectRow->np_project;
			}
		}
		return $projects;
	}

	function getNoticeLanguages( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$languages = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_languages', 'nl_language', 
				array( 'nl_notice_id' => $row->not_id ) );
			foreach ( $res as $langRow ) {
				$languages[] = $langRow->nl_language;
			}
		}
		return $languages;
	}
	
	function getNoticeCountries( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$countries = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_countries', 'nc_country', 
				array( 'nc_notice_id' => $row->not_id ) );
			foreach ( $res as $countryRow ) {
				$countries[] = $countryRow->nc_country;
			}
		}
		return $countries;
	}

	function getNoticeProjectName( $noticeName ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 $eNoticeName = htmlspecialchars( $noticeName );
		 $res = $dbr->select( 'cn_notices', 'not_project', array( 'not_name' => $eNoticeName ) );
		 $row = $dbr->fetchObject( $res );
		 return $row->not_project;
	}

	function getTemplateId( $templateName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$templateName = htmlspecialchars ( $templateName );
		$res = $dbr->select( 'cn_templates', 'tmp_id', array( 'tmp_name' => $templateName ) );
		$row = $dbr->fetchObject( $res );
		return $row->tmp_id;
	}

	function removeTemplateFor( $noticeName, $templateName ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$noticeId = $this->getNoticeId( $noticeName );
		$templateId = $this->getTemplateId( $templateName );
		$dbw->delete( 'cn_assignments', array ( 'tmp_id' => $templateId, 'not_id' => $noticeId ) );
		$dbw->commit();
	}

	function updateNoticeDate( $noticeName, $start, $end ) {
		$dbr = wfGetDB( DB_SLAVE );

		// Start/end don't line up
		if ( $start > $end || $end < $start ) {
			$this->showError( 'centralnotice-invalid-date-range' );
			return;
		}

		// Invalid campaign name
		if ( !$this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-notice-doesnt-exist' );
			return;
		}

		// Overlap over a date within the same project and language
		$startDate = $dbr->timestamp( $start );
		$endDate = $dbr->timestamp( $end );

 		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->update( 'cn_notices',
			array(
				'not_start' => $startDate,
				'not_end' => $endDate
			),
			array( 'not_name' => $noticeName )
		);
	}

	/**
	 * Update the enabled/disabled state of a campaign
	 */
	private function updateEnabled( $noticeName, $isEnabled ) {
		if ( !$this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-doesnt-exist' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update( 'cn_notices',
				array( 'not_enabled' => $isEnabled ),
				array( 'not_name' => $noticeName )
			);
		}
	}
	
	/**
	 * Update the preferred/not preferred state of a campaign
	 */
	function updatePreferred( $noticeName, $isPreferred ) {
		if ( !$this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-doesnt-exist' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update( 'cn_notices',
				array( 'not_preferred' => $isPreferred ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Update the geotargeted/not geotargeted state of a campaign
	 */
	function updateGeotargeted( $noticeName, $isGeotargeted ) {
		if ( !$this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-doesnt-exist' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update( 'cn_notices',
				array( 'not_geo' => $isGeotargeted ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Update the locked/unlocked state of a campaign
	 */
	function updateLock( $noticeName, $isLocked ) {
		if ( !$this->noticeExists( $noticeName ) ) {
			$this->showError( 'centralnotice-doesnt-exist' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update( 'cn_notices',
				array( 'not_locked' => $isLocked ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	function updateWeight( $noticeName, $templateId, $weight ) {
		 $dbw = wfGetDB( DB_MASTER );
		 $noticeId = $this->getNoticeId( $noticeName );
		 $dbw->update( 'cn_assignments',
		 	array ( 'tmp_weight' => $weight ),
		 	array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	function projectDropDownList( $selected = '' ) {
		global $wgNoticeProjects;
		
		if ( $this->editable ) {
			$htmlOut = Xml::openElement( 'select', array( 'name' => 'project_name' ) );
			$htmlOut .= Xml::option( 'All projects', '', ( $selected == '' ) );
			foreach ( $wgNoticeProjects as $value ) {
				$htmlOut .= Xml::option( $value, $value, ( $selected == $value ) );
			}
			$htmlOut .= Xml::closeElement( 'select' );
			return $htmlOut;
		} else {
			if ( $selected == '' ) {
				return 'All projects';
			} else {
				return htmlspecialchars( $selected );
			}
		}
	}
	
	/**
	 * Generates a multiple select list of all languages.
	 * @param $selected The language codes of the selected languages
	 * @param $customisedOnly If true only languages which have some content are listed
	 * @return multiple select list
	 */
	function languageMultiSelector( $selected = array(), $customisedOnly = true ) {
		global $wgContLanguageCode, $wgExtensionAssetsPath, $wgLang;
		$scriptPath = "$wgExtensionAssetsPath/CentralNotice";
		// Make sure the site language is in the list; a custom language code 
		// might not have a defined name...
		$languages = Language::getLanguageNames( $customisedOnly );
		if( !array_key_exists( $wgContLanguageCode, $languages ) ) {
			$languages[$wgContLanguageCode] = $wgContLanguageCode;
		}
		ksort( $languages );

		$options = "\n";
		foreach( $languages as $code => $name ) {
			$options .= Xml::option(
				wfMsg( 'centralnotice-language-listing', $code, $name ),
				$code,
				in_array( $code, $selected )
			) . "\n";
		}
		$htmlOut = '';
		if ( $this->editable ) {
			$htmlOut .= Xml::tags( 'select',
				array( 'multiple' => 'multiple', 'size' => 4, 'id' => 'project_languages[]', 'name' => 'project_languages[]' ),
				$options
			);
			$buttons = array();
			$buttons[] = '<a href="#" onclick="selectLanguages(true);return false;">' . 
				wfMsg( 'powersearch-toggleall' ) . '</a>';
			$buttons[] = '<a href="#" onclick="selectLanguages(false);return false;">' . 
				wfMsg( 'powersearch-togglenone' ) . '</a>';
			$buttons[] = '<a href="#" onclick="top10Languages();return false;">' . 
				wfMsg( 'centralnotice-top-ten-languages' ) . '</a>';
			$htmlOut .= Xml::tags( 'div',
				array( 'style' => 'margin-top: 0.2em;' ),
				'<img src="'.$scriptPath.'/up-arrow.png" style="vertical-align:baseline;"/>' . 
				wfMsg( 'centralnotice-select', $wgLang->commaList( $buttons ) )
			);
		} else {
			$htmlOut .= Xml::tags( 'select',
				array( 
					'multiple' => 'multiple', 
					'size' => 4, 
					'id' => 'project_languages[]', 
					'name' => 'project_languages[]', 
					'disabled' => 'disabled' 
				),
				$options
			);
		}
		return $htmlOut;
	}
	
	/**
	 * Generates a multiple select list of all project types.
	 * @param $selected The name of the selected project type
	 * @return multiple select list
	 */
	function projectMultiSelector( $selected = array() ) {
		global $wgNoticeProjects, $wgExtensionAssetsPath, $wgLang;
		$scriptPath = "$wgExtensionAssetsPath/CentralNotice";
		
		$options = "\n";
		foreach( $wgNoticeProjects as $project ) {
			$options .= Xml::option(
				$project,
				$project,
				in_array( $project, $selected )
			) . "\n";
		}
		$htmlOut = '';
		if ( $this->editable ) {
			$htmlOut .= Xml::tags( 'select',
				array( 'multiple' => 'multiple', 'size' => 4, 'id' => 'projects[]', 'name' => 'projects[]' ),
				$options
			);
			$buttons = array();
			$buttons[] = '<a href="#" onclick="selectProjects(true);return false;">' . 
				wfMsg( 'powersearch-toggleall' ) . '</a>';
			$buttons[] = '<a href="#" onclick="selectProjects(false);return false;">' . 
				wfMsg( 'powersearch-togglenone' ) . '</a>';
			$htmlOut .= Xml::tags( 'div',
				array( 'style' => 'margin-top: 0.2em;' ),
				'<img src="'.$scriptPath.'/up-arrow.png" style="vertical-align:baseline;"/>' . 
				wfMsg( 'centralnotice-select', $wgLang->commaList( $buttons ) )
			);
		} else {
			$htmlOut .= Xml::tags( 'select',
				array( 
					'multiple' => 'multiple', 
					'size' => 4, 
					'id' => 'projects[]', 
					'name' => 'projects[]', 
					'disabled' => 'disabled' 
				),
				$options
			);
		}
		return $htmlOut;
	}
	
	function getProjectName( $value ) {
		return $value; // @fixme -- use wfMsg()
	}

	function updateProjectName( $notice, $projectName ) {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->update( 'cn_notices',
			array ( 'not_project' => $projectName ),
			array(
				'not_name' => $notice
			)
		);
	}
	
	function updateProjects( $notice, $newProjects ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		
		// Get the previously assigned projects
		$oldProjects = $this->getNoticeProjects( $notice );
		
		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );
		
		// Add newly assigned projects
		$addProjects = array_diff( $newProjects, $oldProjects );
		$insertArray = array();
		foreach( $addProjects as $project ) {
			$insertArray[] = array( 'np_notice_id' => $row->not_id, 'np_project' => $project );
		}
		$res = $dbw->insert( 'cn_notice_projects', $insertArray, __METHOD__, array( 'IGNORE' ) );
		
		// Remove disassociated projects
		$removeProjects = array_diff( $oldProjects, $newProjects );
		if ( $removeProjects ) {
			$res = $dbw->delete( 'cn_notice_projects',
				array( 'np_notice_id' => $row->not_id, 'np_project' => $removeProjects )
			);
		}
		
		$dbw->commit();
	}

	function updateProjectLanguages( $notice, $newLanguages ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		
		// Get the previously assigned languages
		$oldLanguages = $this->getNoticeLanguages( $notice );
		
		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );
		
		// Add newly assigned languages
		$addLanguages = array_diff( $newLanguages, $oldLanguages );
		$insertArray = array();
		foreach( $addLanguages as $code ) {
			$insertArray[] = array( 'nl_notice_id' => $row->not_id, 'nl_language' => $code );
		}
		$res = $dbw->insert( 'cn_notice_languages', $insertArray, __METHOD__, array( 'IGNORE' ) );
		
		// Remove disassociated languages
		$removeLanguages = array_diff( $oldLanguages, $newLanguages );
		if ( $removeLanguages ) {
			$res = $dbw->delete( 'cn_notice_languages',
				array( 'nl_notice_id' => $row->not_id, 'nl_language' => $removeLanguages )
			);
		}
		
		$dbw->commit();
	}
	
	function updateCountries( $notice, $newCountries ) {
		$dbw = wfGetDB( DB_MASTER );
		
		// Get the previously assigned languages
		$oldCountries = $this->getNoticeCountries( $notice );
		
		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );
		
		// Add newly assigned countries
		$addCountries = array_diff( $newCountries, $oldCountries );
		$insertArray = array();
		foreach( $addCountries as $code ) {
			$insertArray[] = array( 'nc_notice_id' => $row->not_id, 'nc_country' => $code );
		}
		$res = $dbw->insert( 'cn_notice_countries', $insertArray, __METHOD__, array( 'IGNORE' ) );
		
		// Remove disassociated countries
		$removeCountries = array_diff( $oldCountries, $newCountries );
		if ( $removeCountries ) {
			$dbw->delete( 'cn_notice_countries',
				array( 'nc_notice_id' => $row->not_id, 'nc_country' => $removeCountries )
			);
		}
	}
	
	public static function noticeExists( $noticeName ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 $eNoticeName = htmlspecialchars( $noticeName );
		 $row = $dbr->selectRow( 'cn_notices', 'not_name', array( 'not_name' => $eNoticeName ) );
		 if ( $row ) {
		 	return true;
		 } else {
		 	return false;
		 }
	}

	public static function dropDownList( $text, $values ) {
		$dropDown = "* {$text}\n";
		foreach ( $values as $value ) {
			$dropDown .= "**{$value}\n";
		}
		return $dropDown;
	}

	function addZero( $text ) {
		// Prepend a 0 for text needing it
		if ( strlen( $text ) == 1 ) {
			$text = "0{$text}";
		}
		return $text;
	}
	
	function showError( $message ) {
		global $wgOut;
		$wgOut->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		$this->centralNoticeError = true;
	}
	
	/**
	 * Generates a multiple select list of all countries.
	 * @param $selected The country codes of the selected countries
	 * @return multiple select list
	 */
	function geoMultiSelector( $selected = array() ) {
		$countries = CentralNoticeDB::getCountriesList();
		$options = "\n";
		foreach( $countries as $code => $name ) {
			$options .= Xml::option(
				$name,
				$code,
				in_array( $code, $selected )
			) . "\n";
		}
		$htmlOut = '';
		if ( $this->editable ) {
			$htmlOut .= Xml::tags( 'select',
				array( 
					'multiple' => 'multiple', 
					'size' => 5, 
					'id' => 'geo_countries[]', 
					'name' => 'geo_countries[]' 
				),
				$options
			);
		} else {
			$htmlOut .= Xml::tags( 'select',
				array( 
					'multiple' => 'multiple', 
					'size' => 5, 
					'id' => 'geo_countries[]', 
					'name' => 'geo_countries[]', 
					'disabled' => 'disabled' 
				),
				$options
			);
		}
		return $htmlOut;
	}
}

class CentralNoticePager extends TemplatePager {
	var $viewPage, $special;
	var $editable;

	function __construct( $special ) {
		parent::__construct( $special );
	}
	
	/**
	 * Pull banners from the database
	 */
	function getQueryInfo() {
		$notice = $this->mRequest->getVal( 'notice' );
		$noticeId = CentralNotice::getNoticeId( $notice );
		if ( $noticeId ) {
			// Return all the banners not already assigned to the current campaign
			return array(
				'tables' => array( 'cn_assignments', 'cn_templates' ),
				'fields' => array( 'cn_templates.tmp_name', 'cn_templates.tmp_id' ),
				'conds' => array( 'cn_assignments.tmp_id IS NULL' ),
				'join_conds' => array(
					'cn_assignments' => array( 
						'LEFT JOIN',
						"cn_assignments.tmp_id = cn_templates.tmp_id " . 
						"AND cn_assignments.not_id = $noticeId"
					)
				)
			);
		} else {
			// Return all the banners in the database
			return array(
				'tables' => 'cn_templates',
				'fields' => array( 'tmp_name', 'tmp_id' ),
			);
		}
	}
	
	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 */
	function formatRow( $row ) {
	
		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );
		
		if ( $this->editable ) {
			// Add box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'addTemplates[]', '', array ( 'value' => $row->tmp_name ) )
			);
			// Weight select
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::listDropDown( "weight[$row->tmp_id]",
					CentralNotice::dropDownList( 
						wfMsg( 'centralnotice-weight' ), range ( 0, 100, 5 ) 
					) ,
					'',
					'25',
					'',
					'' )
			);
		}
		
		// Link and Preview
		$render = new SpecialBannerLoader();
		$render->siteName = 'Wikipedia';
		$render->language = $this->mRequest->getVal( 'wpUserLanguage' );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$this->getSkin()->makeLinkObj( $this->viewPage,
				htmlspecialchars( $row->tmp_name ),
				'template=' . urlencode( $row->tmp_name ) ) .
			Xml::fieldset( wfMsg( 'centralnotice-preview' ),
				$render->getHtmlNotice( $row->tmp_name ),
				array( 'class' => 'cn-bannerpreview')
			)
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
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				 wfMsg ( "centralnotice-add" )
			);
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				 wfMsg ( "centralnotice-weight" )
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			wfMsg ( 'centralnotice-templates' )
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
