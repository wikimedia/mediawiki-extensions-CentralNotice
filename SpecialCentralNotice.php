<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class CentralNotice extends SpecialPage {

	/* Functions */

	function CentralNotice() {
		// Register special page
		parent::SpecialPage( 'CentralNotice' );

		// Internationalization
		wfLoadExtensionMessages( 'CentralNotice' );
	}

	function execute( $sub ) {
		global $wgOut, $wgUser, $wgRequest;

		// Begin output
		$this->setHeaders();

		// Check permissions
		$this->editable = $wgUser->isAllowed( 'centralnotice-admin' );

		// Show summary
		$wgOut->addWikiText( wfMsg( 'centralnotice-summary' ) );

		// Show header
		$this->printHeader( $sub );

		$method = $wgRequest->getVal( 'method' );
		// Handle form sumissions
		if ( $this->editable && $wgRequest->wasPosted() ) {

			// Handle removing
			$toRemove = $wgRequest->getArray( 'removeNotices' );
			if ( isset( $toRemove ) ) {
				// Remove notices in list
				foreach ( $toRemove as $template ) {
					$this->removeNotice( $template );
				}

				// Show list of notices
				$this->listNotices();
				return;
			}

			// Handle locking/unlocking
			$lockedNotices = $wgRequest->getArray( 'locked' );
			if ( isset( $lockedNotices ) ) {
 				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
					$this->updateLock( $notice, '1' );
				} else {
					// Build list of notices to lock
					$unlockedNotices = array_diff( $this->getNoticesName(), $lockedNotices );

					// Set locked/unlocked flag accordingly
					foreach ( $lockedNotices as $notice ) {
						$this->updateLock( $notice, '1' );
					}
					foreach ( $unlockedNotices as $notice ) {
						$this->updateLock( $notice, '0' );
					}
				}
			}

			// Handle enabling/disabling
			$enabledNotices = $wgRequest->getArray( 'enabled' );
			if ( isset( $enabledNotices ) ) {
				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
					$this->updateEnabled( $notice, '1' );
				} else {
					// Build list of notices to disable
					$disabledNotices = array_diff( $this->getNoticesName(), $enabledNotices );

					// Set enabled/disabled flag accordingly
					foreach ( $enabledNotices as $notice ) {
						$this->updateEnabled( $notice, '1' );
					}
					foreach ( $disabledNotices as $notice ) {
						$this->updateEnabled( $notice, '0' );
					}
				}
			}

			// Handle setting preferred
			$preferredNotices = $wgRequest->getArray( 'preferred' );
			if ( isset( $preferredNotices ) ) {
				// Set since this is a single display
				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
					CentralNoticeDB::updatePreferred( $notice, '1' );
				}
				else {
					// Build list of notices to unset 
					$unsetNotices = array_diff( $this->getNoticesName(), $preferredNotices );

					// Set flag accordingly
					foreach ( $preferredNotices as $notice ) {
						CentralNoticeDB::updatePreferred( $notice, '1' );
					}
					foreach ( $unsetNotices as $notice ) {
						CentralNoticeDB::updatePreferred( $notice, '0' );
					}
				}
			}

			$noticeName = $wgRequest->getVal( 'notice' );

			// Handle range setting
			$start = $wgRequest->getArray( 'start' );
			$end = $wgRequest->getArray( 'end' );
			if ( isset( $start ) && isset( $end ) ) {
				$updatedStart = sprintf( "%04d%02d%02d%02d%02d00",
					$start['year'],
					$start['month'],
					$start['day'],
					$start['hour'],
					$start['min'] );
				$updatedEnd = sprintf( "%04d%02d%02d000000",
					$end['year'],
					$end['month'],
					$end['day'] );
				$this->updateNoticeDate( $noticeName, $updatedStart, $updatedEnd );
			}

			// Handle updates if no post content came through
			if ( !isset( $lockedNotices ) && $method !== 'addNotice' ) {
				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
						$this->updateLock( $notice, 0 );
				} else {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						$this->updateLock( $notice, '0' );
					}
				}
			}

			if ( !isset( $enabledNotices ) && $method !== 'addNotice' ) {
				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
						$this->updateEnabled( $notice, 0 );
				} else {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						$this->updateEnabled( $notice, '0' );
					}
				}
			}

			if ( !isset( $preferredNotices ) && $method !== 'addNotice' ) {
				if ( $method == 'listNoticeDetail' ) {
					$notice = $wgRequest->getVal ( 'notice' );
						CentralNoticeDB::updatePreferred( $notice, 0 );
				} else {
					$allNotices = $this->getNoticesName();
					foreach ( $allNotices as $notice ) {
						CentralNoticeDB::updatePreferred( $notice, '0' );
					}
				}
			}
			// Handle weight change
			$updatedWeights = $wgRequest->getArray( 'weight' );
			if ( isset( $updatedWeights ) ) {
				foreach ( $updatedWeights as $templateName => $weight ) {
					$this->updateWeight( $noticeName, $templateName, $weight );
				}
			}
		}

		// Handle adding of notice
		$this->showAll = $wgRequest->getVal( 'showAll' );
		if ( $this->editable && $method == 'addNotice' ) {
			$noticeName        = $wgRequest->getVal( 'noticeName' );
			$start             = $wgRequest->getArray( 'start' );
			$project_name      = $wgRequest->getVal( 'project_name' );
			$project_languages = $wgRequest->getArray( 'project_languages' );
			if ( $noticeName == '' ) {
				$wgOut->addWikiMsg ( 'centralnotice-null-string' );
			}
			else {
				$this->addNotice( $noticeName, '0', $start, $project_name, $project_languages );
			}
		}

		// Handle removing of notice
		if ( $this->editable && $method == 'removeNotice' ) {
			$noticeName = $wgRequest->getVal ( 'noticeName' );
			$this->removeNotice ( $noticeName );
		}

		// Handle adding of template
		if ( $this->editable && $method == 'addTemplateTo' ) {
			$noticeName = $wgRequest->getVal( 'noticeName' );
			$templateName = $wgRequest->getVal( 'templateName' );
			$templateWeight = $wgRequest->getVal ( 'weight' );
			$this->addTemplateTo( $noticeName, $templateName, $weight );
			$this->listNoticeDetail( $noticeName );
			return;
		}

		// Handle removing of template
		if ( $this->editable && $method == 'removeTemplateFor' ) {
			$noticeName = $wgRequest->getVal ( 'noticeName' );
			$templateName = $wgRequest->getVal ( 'templateName ' );
			$this->removeTemplateFor( $noticeName , $templateName );
		}

		// Handle showing detail
		if ( $method == 'listNoticeDetail' ) {
			$notice = $wgRequest->getVal ( 'notice' );
			$this->listNoticeDetail( $notice );
			return;
		}

		// Show list of notices
		$this->listNotices();
	}

	// Update the enabled/disabled state of notice
	private function updateEnabled( $notice, $state ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$res = $dbw->update( 'cn_notices',
			array( 'not_enabled' => $state ),
			array( 'not_name' => $notice )
		);
		$dbw->commit();
	}

	static public function printHeader() {
		global $wgOut, $wgTitle, $wgUser;
		$sk = $wgUser->getSkin();

		$pages = array(
			'CentralNotice' => wfMsg( 'centralnotice-notices' ),
			'NoticeTemplate' => wfMsg ( 'centralnotice-templates' )
		);
		$htmlOut = Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		foreach ( $pages as $page => $msg ) {
			$title = SpecialPage::getTitleFor( $page );

			$style = array( 'style' => 'border-bottom:solid 1px silver;' );
			if ( $page == $wgTitle->getText() ) {
				$style = array( 'style' => 'border-bottom:solid 1px black;' );
			}

			$htmlOut .= Xml::tags( 'td', $style,
				$sk->makeLinkObj( $title, htmlspecialchars( $msg ) )
			);
		}
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::closeElement( 'table' );

		$wgOut->addHTML( $htmlOut );
	}

	function getNoticesName() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_notices', 'not_name' );
		$notices = array();
		while ( $row = $dbr->fetchObject( $res ) ) {
			array_push( $notices, $row->not_name );
		}
		return $notices;
	}

	function tableRow( $fields, $element = 'td' ) {
		$cells = array();
		foreach ( $fields as $field ) {
			$cells[] = Xml::tags( $element, array(), $field );
		}
		return Xml::tags( 'tr', array(), implode( "\n", $cells ) ) . "\n";
	}

	function dateSelector( $prefix, $timestamp = null ) {
		if ( $this->editable ) {
			// Default ranges...
			$years = range( 2007, 2012 );
			$months = range( 1, 12 );
			$months = array_map( array( $this, 'addZero' ), $months );
			$days = range( 1 , 31 );
			$days = array_map( array( $this, 'addZero' ), $days );
	
			// Normalize timestamp format...
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

	/*
	 * listNotices
	 *
	 * Print out all campaigns found in db
	 */

	function listNotices() {
		global $wgOut, $wgUser, $wgUserLang;

		// Get connection
		$dbr = wfGetDB( DB_SLAVE );
		$sk = $wgUser->getSkin();
		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		/*
		 * This is temporarily hard-coded
		 */
		$this->showAll = 'Y';

		// If all languages should be shown
		if ( isset( $this->showAll ) ) {
			// Get notices for all languages
			$res = $dbr->select( 'cn_notices',
				array(
					'not_name',
					'not_start',
					'not_end',
					'not_enabled',
					'not_preferred',
					'not_project',
					'not_locked'
				),
				null,
				__METHOD__,
				array( 'ORDER BY' => 'not_id' )
			);
		} else {
			// Get only notices for this language
			$res = $dbr->select( 'cn_notices',
				array(
					'not_name',
					'not_start',
					'not_end',
					'not_enabled',
					'not_preferred',
					'not_project',
					'not_locked'
				),
				array ( 'not_language' => $wgUserLang ),
				__METHOD__,
				array( 'ORDER BY' => 'not_id' )
			);
		}

		// Build HTML
		$htmlOut = '';
		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => SpecialPage::getTitleFor( 'CentralNotice' )->getFullUrl()
				 )
			);
		}
		$htmlOut .= Xml::fieldset( wfMsg( "centralnotice-manage" ) );
		$htmlOut .= Xml::openElement( 'table',
			array(
				'cellpadding' => 9,
				'width' => '100%'
			)
		);

		// Headers
		$headers = array(
			wfMsgHtml( 'centralnotice-notice-name' ),
			wfMsgHtml( 'centralnotice-project-name' ),
			wfMsgHtml( 'centralnotice-project-lang' ),
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

		// Rows
		if ( $dbr->numRows( $res ) >= 1 ) {
			while ( $row = $dbr->fetchObject( $res ) ) {
				$fields = array();

				// Name
				$fields[] = $sk->makeLinkObj( $this->getTitle(),
						htmlspecialchars( $row->not_name ),
						'method=listNoticeDetail&notice=' . urlencode( $row->not_name ) );

				// Project
				$fields[] = htmlspecialchars( $this->getProjectName( $row->not_project ) );

				// Languages
				$project_langs = array();
				$project_langs = $this->getNoticeLanguages( $row->not_name );
				$language_count = count( $project_langs );
				if ( $language_count > 3 ) {
					$languageList = "multiple ($language_count)";
				} elseif ( $language_count > 0 ) {
					$languageList = implode(', ',$project_langs);
				} else {
					$languageList = '';
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

				// Start
				$fields[] = "{$start_year}/{$start_month}/{$start_day} {$start_hour}:{$start_min}";

				// End
				$fields[] = "{$end_year}/{$end_month}/{$end_day}";

				// Enabled
				$fields[] =
					Xml::check( 'enabled[]', ( $row->not_enabled == '1' ),
					wfArrayMerge( $readonly,
						array( 'value' => $row->not_name ) ) );

				// Preferred
				$fields[] =
				Xml::check( 'preferred[]', ( $row->not_preferred == '1' ),
				wfArrayMerge( $readonly,
				array( 'value' => $row->not_name ) ) );

				// Locked
				$fields[] =
					Xml::check( 'locked[]', ( $row->not_locked == '1' ),
					wfArrayMerge( $readonly,
						array( 'value' => $row->not_name ) ) );

				if ( $this->editable ) {
					// Remove
					$fields[] = Xml::check( 'removeNotices[]', false,
						array( 'value' => $row->not_name ) );
				}

				$htmlOut .= $this->tableRow( $fields );
			}
			if ( $this->editable ) {
				$htmlOut .= $this->tableRow(
					array(
						Xml::submitButton( wfMsg( 'centralnotice-modify' ),
							array(
								'id' => 'centralnoticesubmit',
								'name' => 'centralnoticesubmit'
							)
						)
					)
				);
			}

			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::closeElement( 'fieldset' );
			if ( $this->editable ) {
				$htmlOut .= Xml::closeElement( 'form' );
			}


		// No notices to show
		} else {
			$htmlOut = wfMsg( 'centralnotice-no-notices-exist' );
		}

		if ( $this->editable ) {
			// Notice Adding
			$htmlOut .= Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' =>  SpecialPage::getTitleFor( 'CentralNotice' )->getLocalUrl()
				)
			);
			$htmlOut .= Xml::openElement( 'fieldset' );
			$htmlOut .= Xml::element( 'legend', null, wfMsg( 'centralnotice-add-notice' ) );
			$htmlOut .= Xml::hidden( 'title', $this->getTitle()->getPrefixedText() );
			$htmlOut .= Xml::hidden( 'method', 'addNotice' );
	
			$htmlOut .= Xml::openElement( 'table', array ( 'cellpadding' => 9 ) );
			
			// Name
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-notice-name' ) );
			$htmlOut .= Xml::tags( 'td', array(), Xml::inputLabel( '', 'noticeName',  'noticeName', 25 ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start' ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-hour' ) . "(GMT)" );
			$htmlOut .= Xml::tags( 'td', array(), $this->timeSelector( 'start' ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Project
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-project-name' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->projectDropDownList() );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Languages
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), wfMsgHtml( 'yourlanguage' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->languageMultiSelector() );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Submit
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'colspan' => '2' ), Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
	
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::hidden( 'change', 'weight' );
			$htmlOut .= Xml::closeElement( 'fieldset' );
			$htmlOut .= Xml::closeElement( 'form' );
		}
		
		// Output HTML
		$wgOut->addHTML( $htmlOut );
	}

	function listNoticeDetail( $notice ) {
		global $wgOut, $wgRequest, $wgUser;
		if ( $wgRequest->wasPosted() ) {
			// Handle removing of templates
			$templateToRemove = $wgRequest->getArray( 'removeTemplates' );
			if ( isset( $templateToRemove ) ) {
				foreach ( $templateToRemove as $template ) {
					$this->removeTemplateFor( $notice, $template );
				}
			}

			// Handle new project name
			$projectName = $wgRequest->getVal( 'project_name' );
			if ( isset( $projectName ) ) {
				$this->updateProjectName ( $notice, $projectName );
			}

			// Handle new project languages
			$projectLangs = $wgRequest->getArray( 'project_languages' );
			if ( isset( $projectLangs ) ) {
				$this->updateProjectLanguages( $notice, $projectLangs );
			}

			// Handle adding of templates
			$templatesToAdd = $wgRequest->getArray( 'addTemplates' );
			if ( isset( $templatesToAdd ) ) {
				$weight = $wgRequest->getArray( 'weight' );
				foreach ( $templatesToAdd as $template ) {
					$this->addTemplateTo( $notice, $template, $weight[$template] );
				}
			}
			$wgOut->redirect( SpecialPage::getTitleFor( 'CentralNotice' )->getLocalUrl( "method=listNoticeDetail&notice=$notice" ) );
			return;
		}

		$htmlOut = '';
		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => SpecialPage::getTitleFor( 'CentralNotice' )->getLocalUrl( "method=listNoticeDetail&notice=$notice" )
				)
			);
		}

		/*
		 * Temporarily hard coded
		 */
		$this->showAll = 'Y';

		$output_detail = $this->noticeDetailForm( $notice );
		$output_assigned = $this->assignedTemplatesForm( $notice );
		$output_templates = $this->addTemplatesForm( $notice );

		// No notices returned
		if ( $output_detail == '' ) {
			$htmlOut .= wfMsg( 'centralnotice-no-notices-exist' );
		} else {
			$htmlOut .= $output_detail;
		}

		// Catch for no templates so that we dont' double message
		if ( $output_assigned == '' && $output_templates == '' ) {
			$htmlOut .= wfMsg( 'centralnotice-no-templates' );
			$htmlOut .= Xml::element( 'p' );
			$newPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'add' );
			$sk = $wgUser->getSkin();
			$htmlOut .= $sk->makeLinkObj( $newPage, wfMsgHtml( 'centralnotice-add-template' ) );
			$htmlOut .= Xml::element( 'p' );
		} elseif ( $output_assigned == '' ) {
			$htmlOut .= wfMsg( 'centralnotice-no-templates-assigned' );
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
			$htmlOut .= Xml::tags( 'tr', null,
				Xml::tags( 'td', array( 'collspan' => 2 ),
					Xml::submitButton( wfMsg( 'centralnotice-modify' ) )
				)
			);
			$htmlOut .= Xml::closeElement( 'form' );
		}
		$wgOut->addHTML( $htmlOut );
	}

	function noticeDetailForm( $notice ) {
		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}
		$dbr = wfGetDB( DB_SLAVE );

		$row = $dbr->selectRow( 'cn_notices',
			array(
				'not_id',
				'not_name',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_project',
				'not_locked'
			),
			array( 'not_name' => $notice ),
			__METHOD__
		);
		$res = $dbr->select( 'cn_notice_languages',
			'not_language',
			array( 'not_id' => $row->not_id ),
			__METHOD__
		);
		$project_languages = array();
		foreach ( $res as $langRow ) {
			$project_languages[] = $langRow->not_language;
		}

		if ( $row ) {
			// Build Html
			$htmlOut  = Xml::fieldset( $notice );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

			// Rows
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $row->not_start ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-start-hour' ) . "(GMT)" );
			$htmlOut .= Xml::tags( 'td', array(), $this->timeSelector( 'start', $row->not_start, "[$row->not_name]" ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// End Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-end-date' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'end', $row->not_end, "[$row->not_name]" ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Project
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), wfMsgHtml( 'centralnotice-project-name' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->projectDropDownList( $row->not_project ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Languages
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ), wfMsgHtml( 'yourlanguage' ) );
			$htmlOut .= Xml::tags( 'td', array(), $this->languageMultiSelector( $project_languages ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Enabled
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), Xml::label( wfMsgHtml( 'centralnotice-enabled' ), 'enabled[]' ) );
			$htmlOut .= Xml::tags( 'td', array(), Xml::check( 'enabled[]', ( $row->not_enabled == '1' ), wfArrayMerge( $readonly, array( 'value' => $row->not_name, 'id' => 'enabled[]' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Preferred
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), Xml::label( wfMsgHtml( 'centralnotice-preferred' ), 'preferred[]' ) );
			$htmlOut .= Xml::tags( 'td', array(), Xml::check( 'preferred[]', ( $row->not_preferred == '1' ), wfArrayMerge( $readonly, array( 'value' => $row->not_name, 'id' => 'preferred[]' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Locked
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), Xml::label( wfMsgHtml( 'centralnotice-locked' ), 'locked[]' ) );
			$htmlOut .= Xml::tags( 'td', array(), Xml::check( 'locked[]', ( $row->not_locked == '1' ), wfArrayMerge( $readonly, array( 'value' => $row->not_name, 'id' => 'locked[]' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			if ( $this->editable ) {
				// Locked
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(), Xml::label( wfMsgHtml( 'centralnotice-remove' ), 'removeNotices[]' ) );
				$htmlOut .= Xml::tags( 'td', array(), Xml::check( 'removeNotices[]', false, array( 'value' => $row->not_name, 'id' => 'removeNotices[]' ) ) );
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::closeElement( 'fieldset' ) ;
			return $htmlOut;
		} else {
			return '';
		}
	}


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

		// No templates found
		if ( $dbr->numRows( $res ) < 1 ) {
			return;
		}

		// Build Assigned Template HTML
		$htmlOut  = Xml::hidden( 'change', 'weight' );
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

		// Rows
		while ( $row = $dbr->fetchObject( $res ) ) {

			$htmlOut .= Xml::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
					Xml::check( 'removeTemplates[]', false, array( 'value' => $row->tmp_name ) )
				);
			}

			// Weight
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->weightDropDown( "weight[$row->tmp_name]", $row->tmp_weight )
			);

			$viewPage = SpecialPage::getTitleFor( 'NoticeTemplate/view' );
			$render = new SpecialNoticeText();
			$render->project = 'wikipedia';
			global $wgRequest;
			$render->language = $wgRequest->getVal( 'wpUserLanguage' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$sk->makeLinkObj( $viewPage,
					htmlspecialchars( $row->tmp_name ),
					'template=' . urlencode( $row->tmp_name ) ) .
				Xml::fieldset( wfMsg( 'centralnotice-preview' ),
					$render->getHtmlNotice( $row->tmp_name )
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


	function addTemplatesForm( $notice ) {
		global $wgUser;
		$sk = $wgUser->getSkin();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_templates', 'tmp_name', '', '', array( 'ORDER BY' => 'tmp_id' ) );

		$res_assignments = $dbr->select(
			array(
				'cn_notices',
				'cn_assignments',
				'cn_templates'
			),
			array(
			 	'cn_templates.tmp_name',
			),
			array(
				'cn_notices.not_name' => $notice,
				'cn_notices.not_id = cn_assignments.not_id',
				'cn_assignments.tmp_id = cn_templates.tmp_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'cn_notices.not_id' )
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			// Build HTML
			$htmlOut  = Xml::fieldset( wfMsg( "centralnotice-available-templates" ) );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );


			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				 wfMsg ( "centralnotice-add" ) );
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				 wfMsg ( "centralnotice-weight" ) );
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '70%' ),
				 wfMsg ( "centralnotice-templates" ) );
	
			// Find dups
			$templatesAssigned = $this->selectTemplatesAssigned( $notice );

			// Build rows
			while ( $row = $dbr->fetchObject( $res ) ) {
				if ( !in_array ( $row->tmp_name, $templatesAssigned ) ) {
					$htmlOut .= Xml::openElement( 'tr' );

					// Add
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						Xml::check( 'addTemplates[]', '', array ( 'value' => $row->tmp_name ) )
					);

					// Weight
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						Xml::listDropDown( "weight[$row->tmp_name]",
							$this->dropDownList( wfMsg( 'centralnotice-weight' ), range ( 0, 100, 5 ) ) ,
							'',
							'25',
							'',
							'' )
					);

					// Render preview
					$viewPage = SpecialPage::getTitleFor( 'NoticeTemplate/view' );
					$render = new SpecialNoticeText();
					$render->project = 'wikipedia';
					global $wgRequest;
					$render->language = $wgRequest->getVal( 'wpUserLanguage' );
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						$sk->makeLinkObj( $viewPage,
							htmlspecialchars( $row->tmp_name ),
							'template=' . urlencode( $row->tmp_name ) ) .
						Xml::fieldset( wfMsg( 'centralnotice-preview' ),
							$render->getHtmlNotice( $row->tmp_name )
						)
					);

					$htmlOut .= Xml::closeElement( 'tr' );
				}
			}

			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::closeElement( 'fieldset' );
		} else {
			// Nothing found
			return;
		}
		return $htmlOut;
	}


	function selectTemplatesAssigned ( $notice ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array(
				'cn_notices',
				'cn_assignments',
				'cn_templates'
			),
			array(
				'cn_templates.tmp_name',
			),
			array(
				'cn_notices.not_name' => $notice,
				'cn_notices.not_id = cn_assignments.not_id',
				'cn_assignments.tmp_id = cn_templates.tmp_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'cn_notices.not_id' )
		);
		$templateNames = array();
		foreach ( $res as $row ) {
			array_push( $templateNames, $row->tmp_name ) ;
		}
		return $templateNames;
	}

	/**
	 * Lookup function for active notice under a given language and project
	 * Returns an array of running template names with associated weights
	 */
	static function selectNoticeTemplates( $project, $language ) {
		$dbr = wfGetDB( DB_SLAVE );
		$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		$res = $dbr->select(
			array(
				'cn_notices',
				'cn_notice_languages',
				'cn_assignments',
				'cn_templates'
			),
			array(
				'tmp_name',
				'SUM(tmp_weight) AS total_weight'
			),
			array (
				"not_start <= $encTimestamp",
				"not_end >= $encTimestamp",
				"not_enabled = 1",
				'cn_notice_languages.not_id = cn_notices.not_id',
				"cn_notice_languages.not_language = '$language'",
				"not_project" => array( '', $project ),
				'cn_notices.not_id=cn_assignments.not_id',
				'cn_assignments.tmp_id=cn_templates.tmp_id'
			),
			__METHOD__,
			array(
				'GROUP BY' => 'tmp_name'
			)
		);
		$templateWeights = array();
		foreach ( $res as $row ) {
			$name = $row->tmp_name;
			$weight = intval( $row->total_weight );
			$templateWeights[$name] = $weight;
		}
		return $templateWeights;
	}

	function addNotice( $noticeName, $enabled, $start, $project_name, $project_languages ) {
		global $wgOut;

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_notices', 'not_name', array( 'not_name' => $noticeName ) );
		if ( $dbr->numRows( $res ) > 0 ) {
			$wgOut->addWikiMsg( 'centralnotice-notice-exists' );
			return;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$start['hour'] = substr( $start['hour'], 0 , 2 );
			if ( $start['month'] == 12 ) {
				$end['month'] = '01';
				$end['year'] = ( $start['year'] + 1 );
			} elseif ( $start['month'] == '09' ) {
				$end['month'] = '10';
				$end['year'] = $start['year'];
			} else {
				$end['month'] = ( substr( $start['month'], 0, 1 ) ) == 0 ? 0 . ( intval( $start['month'] ) + 1 ) : ( $start['month'] + 1 );
				$end['year'] = $start['year'];
			}

			$startTs = wfTimeStamp( TS_MW, "{$start['year']}:{$start['month']}:{$start['day']} {$start['hour']}:00:00" );
			$endTs = wfTimeStamp( TS_MW, "{$end['year']}:{$end['month']}:{$start['day']} {$start['hour']}:00:00" );

			$res = $dbw->insert( 'cn_notices',
				array( 'not_name' => $noticeName,
					'not_enabled' => $enabled,
					'not_start' => $dbr->timestamp( $startTs ),
					'not_end' => $dbr->timestamp( $endTs ),
					'not_project' => $project_name
				)
			);
			$not_id = $dbw->insertId();
			foreach( $project_languages as $code ) {
				$res = $dbw->insert( 'cn_notice_languages',
					array( 'not_id' => $not_id,
						'not_language' => $code
					)
				);
			}
			$dbw->commit();
			return;
		}
	}

	function removeNotice( $noticeName ) {
		global $wgOut;
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			array( 'not_name' => $noticeName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			 $wgOut->addWikiMsg( 'centralnotice-notice-doesnt-exist' );
			 return;
		}
		$row = $dbr->fetchObject( $res );
		if ( $row->not_locked == '1' ) {
			 $wgOut->addWikiMsg( 'centralnotice-notice-is-locked' );
			 return;
		} else {
			 $dbw = wfGetDB( DB_MASTER );
			 $dbw->begin();
			 $noticeId = htmlspecialchars( $this->getNoticeId( $noticeName ) );
			 $res = $dbw->delete( 'cn_assignments',  array ( 'not_id' => $noticeId ) );
			 $res = $dbw->delete( 'cn_notices', array ( 'not_name' => $noticeName ) );
			 $res = $dbw->delete( 'cn_notice_languages', array ( 'not_id' => $noticeId ) );
			 $dbw->commit();
			 return;
		}
	}

	function addTemplateTo( $noticeName, $templateName, $weight ) {
		global $wgOut;

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
			$wgOut->addWikiMsg( 'centralnotice-template-already-exists' );
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

	function getNoticeId ( $noticeName ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 $eNoticeName = htmlspecialchars( $noticeName );
		 $res = $dbr->select( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		 $row = $dbr->fetchObject( $res );
		 return $row->not_id;
	}

	function getNoticeLanguages ( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$res = $dbr->select( 'cn_notice_languages', 'not_language', array( 'not_id' => $row->not_id ) );
		$languages = array();
		foreach ( $res as $langRow ) {
			$languages[] = $langRow->not_language;
		}
		return $languages;
	}

	function getNoticeProjectName ( $noticeName ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 $eNoticeName = htmlspecialchars( $noticeName );
		 $res = $dbr->select( 'cn_notices', 'not_project', array( 'not_name' => $eNoticeName ) );
		 $row = $dbr->fetchObject( $res );
		 return $row->not_project;
	}

	function getTemplateId ( $templateName ) {
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
		$res = $dbw->delete( 'cn_assignments', array ( 'tmp_id' => $templateId, 'not_id' => $noticeId ) );
		$dbw->commit();
	}

	function updateNoticeDate ( $noticeName, $start, $end ) {
		global $wgOut;

		$dbr = wfGetDB( DB_SLAVE );

		// Start / end dont line up
		if ( $start > $end || $end < $start ) {
			 $wgOut->addWikiMsg( 'centralnotice-invalid-date-range3' );
			 return;
		}

		// Invalid notice name
		$res = $dbr->select( 'cn_notices', 'not_name', array( 'not_name' => $noticeName ) );
		if ( $dbr->numRows( $res ) < 1 ) {
			$wgOut->addWikiMsg( 'centralnotice-doesnt-exist' );
		}

		// Overlap over a date within the same project and language
		$startDate = $dbr->timestamp( $start );
		$endDate = $dbr->timestamp( $end );

 		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$res = $dbw->update( 'cn_notices',
			array(
				'not_start' => $startDate,
				'not_end' => $endDate
			),
			array( 'not_name' => $noticeName )
		);
		$dbw->commit();
	}

	function updateLock ( $noticeName, $isLocked ) {
		global $wgOut;

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_notices', 'not_name',
			array( 'not_name' => $noticeName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			$wgOut->addWikiMsg( 'centralnotice-doesnt-exist' );
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$res = $dbw->update( 'cn_notices',
				array( 'not_locked' => $isLocked ),
				array( 'not_name' => $noticeName )
			);
			$dbw->commit();
		}
	}

	function updateWeight ( $noticeName, $templateName, $weight ) {
		 $dbw = wfGetDB( DB_MASTER );
		 $dbw->begin();
		 $noticeId = $this->getNoticeId( $noticeName );
		 $templateId = $this->getTemplateId( $templateName );
		 $res = $dbw->update( 'cn_assignments',
		 	array ( 'tmp_weight' => $weight ),
		 	array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
		$dbw->commit();
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
		global $wgContLanguageCode;
		global $wgScriptPath;
		$scriptPath = "$wgScriptPath/extensions/CentralNotice";
		/**
		 * Make sure the site language is in the list; a custom language code
		 * might not have a defined name...
		 */
		$languages = Language::getLanguageNames( $customisedOnly );
		if( !array_key_exists( $wgContLanguageCode, $languages ) ) {
			$languages[$wgContLanguageCode] = $wgContLanguageCode;
		}
		ksort( $languages );

		$options = "\n";
		foreach( $languages as $code => $name ) {
			$options .= Xml::option( "$code - $name", $code, in_array( $code, $selected ) ) . "\n";
		}
		$htmlOut = "
<script type=\"text/javascript\">
function selectLanguages(selectAll) {
	var selectBox = document.getElementById(\"project_languages[]\");
	var firstSelect = selectBox.options.length - 1;
	for (var i = firstSelect; i >= 0; i--) {
		selectBox.options[i].selected = selectAll;
	}
}
function top10Languages() {
	var selectBox = document.getElementById(\"project_languages[]\");
	var top10 = new Array('en','de','fr','it','pt','ja','es','pl','ru','nl');
	for (var i = 0; i < selectBox.options.length; i++) {
		var lang = selectBox.options[i].value;
		if (top10.toString().indexOf(lang)!==-1) {
			selectBox.options[i].selected = true;
		}
	}
}
</script>";
		$htmlOut .=
			Xml::tags( 'select',
				array( 'multiple' => 'multiple', 'size' => 4, 'id' => 'project_languages[]', 'name' => 'project_languages[]' ),
				$options
			).
			Xml::tags( 'div',
				array( 'style' => 'margin-top: 0.2em;' ),
				'<img src="'.$scriptPath.'/arrow.png" style="vertical-align:baseline;"/>Select: <a href="#" onclick="selectLanguages(true);return false;">All</a>, <a href="#" onclick="selectLanguages(false);return false;">None</a>, <a href="#" onclick="top10Languages();return false;">Top 10 Languages</a>'
			);
		return $htmlOut;
	}
	
	function getProjectName( $value ) {
		return $value; // @fixme -- use wfMsg()
	}

	function updateProjectName( $notice, $projectName ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$res = $dbw->update( 'cn_notices',
			array ( 'not_project' => $projectName ),
			array(
				'not_name' => $notice
			)
		);
		$dbw->commit();
	}

	function updateProjectLanguages( $notice, $newLanguages ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		
		// Get the previously assigned languages
		$oldLanguages = array();
		$oldLanguages = $this->getNoticeLanguages( $notice );
		
		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );
		
		// Add newly assigned languages
		$addLanguages = array_diff($newLanguages, $oldLanguages);
		foreach( $addLanguages as $code ) {
			$res = $dbw->insert( 'cn_notice_languages',
				array( 'not_id' => $row->not_id, 'not_language' => $code )
			);
		}
		
		// Remove disassociated languages
		$removeLanguages = array_diff($oldLanguages, $newLanguages);
		foreach( $removeLanguages as $code ) {
			$res = $dbw->delete( 'cn_notice_languages',
				array( 'not_id' => $row->not_id, 'not_language' => $code )
			);
		}
		
		$dbw->commit();
	}

	function dropDownList ( $text, $values ) {
		$dropDown = "* {$text}\n";
		foreach ( $values as $value ) {
			$dropDown .= "**{$value}\n";
		}
		return $dropDown;
	}

	function addZero ( $text ) {
		// Prepend a 0 for text needing it
		if ( strlen( $text ) == 1 ) {
			$text = "0{$text}";
		}
		return $text;
	}
}
