<?php

class CentralNotice extends SpecialPage {

	// Note: These values are not arbitrary. Higher priority is indicated by a
	// higher value.
	const LOW_PRIORITY = 0;
	const NORMAL_PRIORITY = 1;
	const HIGH_PRIORITY = 2;
	const EMERGENCY_PRIORITY = 3;

	public $editable, $centralNoticeError;

	protected $campaign;
	protected $campaignWarnings = array();

	function __construct() {
		// Register special page
		parent::__construct( 'CentralNotice' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		// Begin output
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$request = $this->getRequest();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( 'ext.centralNotice.adminUi.campaignManager' );

		// Check permissions
		$this->editable = $this->getUser()->isAllowed( 'centralnotice-admin' );

		// Initialize error variable
		$this->centralNoticeError = false;

		$subaction = $request->getVal( 'subaction' );

		// Switch to campaign detail interface if requested.
		// This will also handle post submissions from the detail interface.
		if ( $subaction === 'noticeDetail' ) {
			$notice = $request->getVal( 'notice' );
			$this->outputNoticeDetail( $notice );
			return;
		}

		// Handle form submissions from "Manage campaigns" or "Add a campaign" interface
		if ( $this->editable && $request->wasPosted() ) {
			if ( wfReadOnly() || CNDatabase::getDb( DB_MASTER )->isReadOnly() ) {
				throw new ReadOnlyError();
			}

			// Check authentication token
			if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

				// Handle adding a campaign or changing existing campaign settings
				// via the list interface. In either case, we'll retirect to the
				// list view.
				if ( $subaction === 'addCampaign' ) {
					$this->handleAddCampaignPost();
				} else {
					$this->handleNoticePostFromList();
				}

				// If there were no errors, reload the page to prevent duplicate form submission
				if ( !$this->centralNoticeError ) {
					$out->redirect( $this->getPageTitle()->getLocalUrl() );
					return;
				}
			} else {
				$this->showError( 'sessionfailure' );
			}
		}

		$this->outputListOfNotices();
	}

	/**
	 * Output the start tag for the enclosing div we use on all subactions
	 */
	protected function outputEnclosingDivStartTag() {
		$this->getOutput()->addHTML( Xml::openElement( 'div', array( 'id' => 'preferences' ) ) );
	}

	/**
	 * Output the end tag for the enclosing div we use on all subactions
	 */
	protected function outputEnclosingDivEndTag() {
		$this->getOutput()->addHTML( Xml::closeElement( 'div' ) );
	}

	/**
	 * Send the list of notices (campaigns) to output and, if appropriate,
	 * the "Add campaign" form.
	 */
	protected function outputListOfNotices() {

		$this->outputEnclosingDivStartTag();

		$out = $this->getOutput();

		$out->addHTML( Xml::element( 'h2',
			array( 'class' => 'cn-special-section' ),
			$this->msg( 'centralnotice-manage' )->text() ) );

		$out->addModules( 'ext.centralNotice.adminUi.campaignPager' );

		$pager = new CNCampaignPager( $this, $this->editable );
		$out->addHTML( $pager->getBody() );
		$out->addHTML( $pager->getNavigationBar() );

		// If the user has edit rights, show a form for adding a campaign
		if ( $this->editable ) {
			$this->addNoticeForm();
		}

		$this->outputEnclosingDivEndTag();
	}

	protected function handleNoticePostFromList() {

		$request = $this->getRequest();
		$changes = json_decode( $request->getText( 'changes' ), true );
		$summary = $this->getSummaryFromRequest( $request );

		// Make the changes requested
		foreach ( $changes as $campaignName => $campaignChanges ) {

			$initialSettings = Campaign::getCampaignSettings( $campaignName );

			// Next campaign if somehow this one doesn't exist
			if ( !$initialSettings ) {

				wfLogWarning( 'Change requested for non-existent campaign ' .
					$campaignName );

				continue;
			}

			// Set values as per $changes
			if ( isset( $campaignChanges['archived'] ) ) {

				Campaign::setBooleanCampaignSetting( $campaignName, 'archived',
					$campaignChanges['archived'] ? 1 : 0 );
			}

			if ( isset( $campaignChanges['locked'] ) ) {

				Campaign::setBooleanCampaignSetting( $campaignName, 'locked',
					$campaignChanges['locked'] ? 1 : 0 );
			}

			if ( isset( $campaignChanges['enabled'] ) ) {

				Campaign::setBooleanCampaignSetting( $campaignName, 'enabled',
					$campaignChanges['enabled'] ? 1 : 0 );
			}

			if ( isset( $campaignChanges['priority'] ) ) {

				Campaign::setNumericCampaignSetting(
					$campaignName,
					'preferred',
					intval( $campaignChanges['priority'] ),
					CentralNotice::EMERGENCY_PRIORITY,
					CentralNotice::LOW_PRIORITY
				);
			}

			// Log any differences in settings
			$newSettings = Campaign::getCampaignSettings( $campaignName );
			$diffs = array_diff_assoc( $initialSettings, $newSettings );

			if ( $diffs ) {
				$campaignId = Campaign::getNoticeId( $campaignName );
				Campaign::logCampaignChange(
					'modified',
					$campaignId,
					$this->getUser(),
					$initialSettings,
					$newSettings,
					array(), array(),
					$summary
				);
			}
		}
	}

	/**
	 * Render a field suitable for jquery.ui datepicker
	 */
	protected function dateSelector( $name, $editable, $timestamp = null ) {
		if ( $editable ) {
			// Normalize timestamp format. If no timestamp is passed, default to now. If -1 is
			// passed, set no defaults.
			if ( $timestamp === -1 ) {
				$ts = '';
			} else {
				$ts = wfTimestamp(TS_MW, $timestamp);
			}

			$out = Html::element( 'input',
				array(
					'id' => "{$name}Date",
					'name' => "{$name}Date",
					'type' => 'text',
					'class' => 'centralnotice-datepicker centralnotice-datepicker-limit_one_year',
				)
			);
			$out .= Html::element( 'input',
				array(
					'id' => "{$name}Date_timestamp",
					'name' => "{$name}Date_timestamp",
					'type' => 'hidden',
					'value' => $ts,
				)
			);
			return $out;
		} else {
			return $this->getLanguage()->date( $timestamp );
		}
	}

	protected function timeSelectorTd( $prefix, $editable, $timestamp = null ) {
		return Xml::tags(
			'td',
			array(
				'dir' => 'ltr', // Time is left-to-right in all languages
				'class' => 'cn-timepicker',
			),
			$this->timeSelector( $prefix, $editable, $timestamp )
		);
	}

	protected function timeSelector( $prefix, $editable, $timestamp = null ) {
		if ( $editable ) {
			$minutes = $this->paddedRange( 0, 59 );
			$hours = $this->paddedRange( 0, 23 );

			// Normalize timestamp format...
			$ts = wfTimestamp( TS_MW, $timestamp );

			$fields = array(
				array( "hour", "centralnotice-hours", $hours,   substr( $ts, 8, 2 ) ),
				array( "min",  "centralnotice-min",   $minutes, substr( $ts, 10, 2 ) ),
			);

			return $this->createSelector( $prefix, $fields );
		} else {
			return $this->getLanguage()->time( $timestamp );
		}
	}

	/**
	 * Construct the priority select list for a campaign
	 *
	 * @param string|bool $index The name of the campaign (or false if it isn't needed)
	 * @param boolean $editable Whether or not the form is editable by the user
	 * @param integer $priorityValue The current priority value for this campaign
	 *
	 * @return string HTML for the select list
	 */
	public function prioritySelector( $index, $editable, $priorityValue ) {
		$priorities = array(
			CentralNotice::LOW_PRIORITY => wfMessage( 'centralnotice-priority-low' )->escaped(),
			CentralNotice::NORMAL_PRIORITY => wfMessage( 'centralnotice-priority-normal' )->escaped(),
			CentralNotice::HIGH_PRIORITY => wfMessage( 'centralnotice-priority-high' )->escaped(),
			CentralNotice::EMERGENCY_PRIORITY => wfMessage( 'centralnotice-priority-emergency' )->escaped(),
		);

		if ( $editable ) {

			$options = ''; // The HTML for the select list options
			foreach ( $priorities as $key => $label ) {
				$options .= XML::option( $label, $key, $priorityValue == $key );
			}

			// Data attributes set below (data-campaign-name and
			// data-initial-value) must coordinate with CNCampaignPager and
			// ext.centralNotice.adminUi.campaignPager.js

			$selectAttribs = array(
				'name' => 'priority',
				'data-initial-value' => $priorityValue
			);

			if ( $index ) {
				$selectAttribs['data-campaign-name'] = $index;
			}

			return Xml::openElement( 'select', $selectAttribs )
				. "\n"
				. $options
				. "\n"
				. Xml::closeElement( 'select' );
		} else {
			return $priorities[$priorityValue];
		}
	}

	/**
	 * Build a set of select lists. Used by timeSelector.
	 * @param $prefix string to identify selector set, for example, 'start' or 'end'
	 * @param $fields array of select lists to build
	 * @return string
	 */
	protected function createSelector( $prefix, $fields ) {
		$out = '';
		foreach ( $fields as $data ) {
			list( $field, $label, $set, $current ) = $data;
			$out .= Xml::listDropDown( "{$prefix}[{$field}]",
				CentralNotice::dropDownList( $this->msg( $label )->text(), $set ),
				'',
				$current );
		}
		return $out;
	}

	/**
	 * Output a form for adding a campaign.
	 */
	protected function addNoticeForm() {
		$request = $this->getRequest();
		// If there was an error, we'll need to restore the state of the form
		if ( $request->wasPosted() && ( $request->getVal( 'subaction' ) === 'addCampaign' ) ) {
			$start = $this->getDateTime( 'start' );
			$noticeProjects = $request->getArray( 'projects', array() );
			$noticeLanguages = $request->getArray( 'project_languages', array() );
		} else { // Defaults
			$start = null;
			$noticeProjects = array();
			$noticeLanguages = array();
		}

		$htmlOut = '';

		// Section heading
		$htmlOut .= Xml::element( 'h2',
			array( 'class' => 'cn-special-section' ),
			$this->msg( 'centralnotice-add-notice' )->text() );

		// Begin Add a campaign fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// Form for adding a campaign
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
		$htmlOut .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );
		$htmlOut .= Html::hidden( 'subaction', 'addCampaign' );

		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

		// Name
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-notice-name' )->escaped() );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::input( 'noticeName', 25, $request->getVal( 'noticeName' ) ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		// Start Date
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-start-date' )->escaped() );
		$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $this->editable, $start ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		// Start Time
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-start-time' )->escaped() );
		$htmlOut .= $this->timeSelectorTd( 'start', $this->editable, $start );
		$htmlOut .= Xml::closeElement( 'tr' );
		// Project
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$this->msg( 'centralnotice-projects' )->escaped() );
		$htmlOut .= Xml::tags( 'td', array(), $this->projectMultiSelector( $noticeProjects ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		// Languages
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$this->msg( 'centralnotice-languages' )->escaped() );
		$htmlOut .= Xml::tags( 'td', array(),
			$this->languageMultiSelector( $noticeLanguages ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		// Countries
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::label( $this->msg( 'centralnotice-geo' )->text(), 'geotargeted' ) );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::check( 'geotargeted', false, array( 'value' => 1, 'id' => 'geotargeted' ) ) );
		$htmlOut .= Xml::closeElement( 'tr' );
		$htmlOut .= Xml::openElement( 'tr',
			array( 'id'=> 'geoMultiSelector', 'style'=> 'display:none;' ) );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$this->msg( 'centralnotice-countries' )->escaped() );
		$htmlOut .= Xml::tags( 'td', array(), $this->geoMultiSelector() );
		$htmlOut .= Xml::closeElement( 'tr' );

		$htmlOut .= Xml::closeElement( 'table' );
		$htmlOut .= Html::hidden( 'change', 'weight' );
		$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

		// Submit button
		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'cn-buttons' ),
			$this->makeSummaryField( true ) .
			Xml::submitButton( $this->msg( 'centralnotice-modify' )->text() )
		);

		$htmlOut .= Xml::closeElement( 'form' );

		// End Add a campaign fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		// Output HTML
		$this->getOutput()->addHTML( $htmlOut );
	}

	protected function handleAddCampaignPost() {
		$request = $this->getRequest();
		$noticeName = $request->getVal( 'noticeName' );
		$start = $this->getDateTime( 'start' );
		$projects = $request->getArray( 'projects' );
		$project_languages = $request->getArray( 'project_languages' );
		$geotargeted = $request->getCheck( 'geotargeted' );
		$geo_countries = $request->getArray( 'geo_countries' );
		if ( $noticeName == '' ) {
			$this->showError( 'centralnotice-null-string' );
		} else {
			$result = Campaign::addCampaign( $noticeName, '0', $start, $projects,
				$project_languages, $geotargeted, $geo_countries,
				100, CentralNotice::NORMAL_PRIORITY, $this->getUser(),
				$this->getSummaryFromRequest( $request ) );
			if ( is_string( $result ) ) {
				// TODO Better error handling
				$this->showError( $result );
			}
		}
	}

	/**
	 * Retrieve jquery.ui.datepicker date and homebrew time,
	 * and return as a MW timestamp string.
	 */
	function getDateTime( $prefix ) {
		global $wgRequest;
		// Check whether the user left the date field blank.
		// Interpret any form of "empty" as a blank value.
		$manual_entry = $wgRequest->getVal( "{$prefix}Date" );
		if ( !$manual_entry ) {
			return null;
		}

		$datestamp = $wgRequest->getVal( "{$prefix}Date_timestamp" );
		$timeArray = $wgRequest->getArray( $prefix );
		$timestamp = substr( $datestamp, 0, 8 ) .
			$timeArray[ 'hour' ] .
			$timeArray[ 'min' ] . '00';
		return $timestamp;
	}

	/**
	 * Show the interface for viewing/editing an individual campaign
	 *
	 * @param $notice string The name of the campaign to view
	 */
	function outputNoticeDetail( $notice ) {
		$this->outputEnclosingDivStartTag();

		$this->campaign = new Campaign( $notice ); // Todo: Convert the rest of this page to use this object
		try {
			if ( $this->campaign->isArchived() || $this->campaign->isLocked() ) {
				$this->getOutput()->setSubtitle( $this->msg( 'centralnotice-archive-edit-prevented' ) );
				$this->editable = false; // Todo: Fix this gross hack to prevent editing
			}
		} catch ( CampaignExistenceException $ex ) {
			throw new ErrorPageError( 'centralnotice', 'centralnotice-notice-doesnt-exist' );
		}

		if ( $this->editable && $this->getRequest()->wasPosted() ) {
			$this->handleNoticeDetailPost( $notice );
		}

		$htmlOut = '';

		// Begin Campaign detail fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalUrl( array(
						'subaction' => 'noticeDetail',
						'notice' => $notice
					) )
				)
			);
		}

		$output_detail = $this->noticeDetailForm( $notice );
		$output_assigned = $this->assignedTemplatesForm( $notice );
		$output_templates = $this->addTemplatesForm( $notice );

		$htmlOut .= $output_detail;

		// Catch for no banners so that we don't double message
		if ( $output_assigned == '' && $output_templates == '' ) {
			$htmlOut .= $this->msg( 'centralnotice-no-templates' )->escaped();
			$htmlOut .= Xml::element( 'p' );
			$newPage = $this->getTitleFor( 'NoticeTemplate', 'add' );
			$htmlOut .= Linker::link(
				$newPage,
				$this->msg( 'centralnotice-add-template' )->escaped()
			);
			$htmlOut .= Xml::element( 'p' );
		} elseif ( $output_assigned == '' ) {
			$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-assigned-templates' )->text() );
			$htmlOut .= $this->msg( 'centralnotice-no-templates-assigned' )->escaped();
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
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

			$htmlOut .= $this->makeSummaryField();

			// Submit button
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'cn-buttons' ),
				Xml::submitButton(
					$this->msg( 'centralnotice-modify' )->text(),
					array( 'id' => 'noticeDetailSubmit' )
				)
			);
		}

		if ( $this->editable ) {
			$htmlOut .= Xml::closeElement( 'form' );
		}
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$this->displayCampaignWarnings();

		$this->getOutput()->addHTML( $htmlOut );
		$this->outputEnclosingDivEndTag();
	}

	/**
	 * Process a post request from the campaign (notice) detail subaction. Make
	 * changes to the campaign based on the post parameters.
	 *
	 * @param string $notice
	 */
	protected function handleNoticeDetailPost( $notice ) {
		global $wgNoticeNumberOfBuckets, $wgCentralNoticeCampaignMixins;
		$request = $this->getRequest();

		// If what we're doing is actually serious (ie: not updating the banner
		// filter); process the request. Recall that if the serious request
		// succeeds, the page will be reloaded again.
		if ( $request->getCheck( 'template-search' ) == false ) {

			// Check authentication token
			if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

				// Handle removing campaign
				if ( $request->getVal( 'archive' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'archived', 1 );
				}

				$initialCampaignSettings = Campaign::getCampaignSettings( $notice );

				// Handle locking/unlocking campaign
				if ( $request->getCheck( 'locked' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'locked', 1 );
				} else {
					Campaign::setBooleanCampaignSetting( $notice, 'locked', 0 );
				}

				// Handle enabling/disabling campaign
				if ( $request->getCheck( 'enabled' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'enabled', 1 );
				} else {
					Campaign::setBooleanCampaignSetting( $notice, 'enabled', 0 );
				}

				// Set campaign traffic throttle
				if ( $request->getCheck( 'throttle-enabled' ) ) {
					$throttle = $request->getInt( 'throttle-cur', 100 );
				} else {
					$throttle = 100;
				}
				Campaign::setNumericCampaignSetting( $notice, 'throttle', $throttle, 100, 0 );

				// Handle user bucketing setting for campaign
				$numCampaignBuckets = min( $request->getInt( 'buckets', 1 ), $wgNoticeNumberOfBuckets );
				$numCampaignBuckets = pow( 2, floor( log( $numCampaignBuckets, 2 ) ) );

				Campaign::setNumericCampaignSetting(
					$notice,
					'buckets',
					$numCampaignBuckets,
					$wgNoticeNumberOfBuckets,
					1
				);

				// Handle setting campaign priority
				Campaign::setNumericCampaignSetting(
					$notice,
					'preferred',
					$request->getInt( 'priority', CentralNotice::NORMAL_PRIORITY ),
					CentralNotice::EMERGENCY_PRIORITY,
					CentralNotice::LOW_PRIORITY
				);

				// Handle updating geotargeting
				if ( $request->getCheck( 'geotargeted' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'geo', 1 );
					$countries = $request->getArray( 'geo_countries' );
					if ( $countries ) {
						Campaign::updateCountries( $notice, $countries );
					}
				} else {
					Campaign::setBooleanCampaignSetting( $notice, 'geo', 0 );
				}

				// Handle updating the start and end settings
				$start = $this->getDateTime( 'start' );
				$end = $this->getDateTime( 'end' );
				if ( $start && $end ) {
					Campaign::updateNoticeDate( $notice, $start, $end );
				}

				// Handle adding of banners to the campaign
				$templatesToAdd = $request->getArray( 'addTemplates' );
				if ( $templatesToAdd ) {
					$weight = $request->getArray( 'weight' );
					foreach ( $templatesToAdd as $templateName ) {
						$templateId = Banner::fromName( $templateName )->getId();
						$bucket = $request->getInt( "bucket-{$templateName}" );
						$result = Campaign::addTemplateTo(
							$notice, $templateName, $weight[$templateId], $bucket
						);
						if ( $result !== true ) {
							$this->showError( $result );
						}
					}
				}

				// Handle removing of banners from the campaign
				$templateToRemove = $request->getArray( 'removeTemplates' );
				if ( $templateToRemove ) {
					foreach ( $templateToRemove as $template ) {
						Campaign::removeTemplateFor( $notice, $template );
					}
				}

				// Handle weight changes
				$updatedWeights = $request->getArray( 'weight' );
				$balanced = $request->getCheck( 'balanced' );
				if ( $updatedWeights ) {
					foreach ( $updatedWeights as $templateId => $weight ) {
						if ( $balanced ) {
							$weight = 25;
						}
						Campaign::updateWeight( $notice, $templateId, $weight );
					}
				}

				// Handle bucket changes - keep in mind that the number of campaign buckets
				// might have changed simultaneously (and might have happened server side)
				$updatedBuckets = $request->getArray( 'bucket' );
				if ( $updatedBuckets ) {
					foreach ( $updatedBuckets as $templateId => $bucket ) {
						Campaign::updateBucket(
							$notice,
							$templateId,
							intval( $bucket ) % $numCampaignBuckets
						);
					}
				}

				// Handle new projects
				$projects = $request->getArray( 'projects' );
				if ( $projects ) {
					Campaign::updateProjects( $notice, $projects );
				}

				// Handle new project languages
				$projectLangs = $request->getArray( 'project_languages' );
				if ( $projectLangs ) {
					Campaign::updateProjectLanguages( $notice, $projectLangs );
				}

				// Handle campaign-associated mixins
				foreach ( $wgCentralNoticeCampaignMixins
					as $mixinName => $mixinDef
				) {

					$mixinControlName = self::makeNoticeMixinControlName( $mixinName );

					if ( $request->getCheck( $mixinControlName ) ) {

						$params = array();

						foreach ( $mixinDef['parameters'] as $paramName => $paramDef ) {

							$requestParamName =
								self::makeNoticeMixinControlName( $mixinName, $paramName );

							switch( $paramDef['type'] ) {
								case 'string':
									$paramVal = Sanitizer::removeHTMLtags(
										$request->getText( $requestParamName )
									);
									break;

								case 'integer':
									$paramVal = $request->getInt( $requestParamName );
									break;

								case 'float':
									$paramVal = $request->getFloat( $requestParamName );
									break;

								case 'boolean':
									$paramVal = $request->getCheck( $requestParamName );
								break;
							}

							$params[$paramName] = $paramVal;
						}

						Campaign::updateCampaignMixins(
							$notice, $mixinName, true, $params );

					} else {
						Campaign::updateCampaignMixins( $notice, $mixinName, false );
					}
				}

				$finalCampaignSettings = Campaign::getCampaignSettings( $notice );
				$campaignId = Campaign::getNoticeId( $notice );

				$summary = $this->getSummaryFromRequest( $request );

				Campaign::logCampaignChange(
					'modified', $campaignId, $this->getUser(),
					$initialCampaignSettings, $finalCampaignSettings,
					array(), array(),
					$summary );

				// If there were no errors, reload the page to prevent duplicate form submission
				if ( !$this->centralNoticeError ) {
					$this->getOutput()->redirect( $this->getPageTitle()->getLocalUrl( array(
						'subaction' => 'noticeDetail',
						'notice' => $notice
					) ) );
					return;
				}
			} else {
				$this->showError( 'sessionfailure' );
			}
		}
	}

	/**
	 * Output stored campaign warnings
	 */
	function displayCampaignWarnings() {
		foreach ( $this->campaignWarnings as $message ) {
			$this->getOutput()->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		}
	}

	/**
	 * Create form for managing campaign settings (start date, end date, languages, etc.)
	 */
	function noticeDetailForm( $notice ) {
		global $wgNoticeNumberOfBuckets, $wgCentralNoticeCampaignMixins;

		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		$campaign = Campaign::getCampaignSettings( $notice );

		if ( $campaign ) {
			// If there was an error, we'll need to restore the state of the form
			$request = $this->getRequest();

			if ( $request->wasPosted() ) {
				$start = $this->getDateTime( 'start' );
				$end = $this->getDateTime( 'end' );
				$isEnabled = $request->getCheck( 'enabled' );
				$priority = $request->getInt( 'priority', CentralNotice::NORMAL_PRIORITY );
				$throttle = $request->getInt( 'throttle', 100 );
				$isLocked = $request->getCheck( 'locked' );
				$isArchived = $request->getCheck( 'archived' );
				$noticeProjects = $request->getArray( 'projects', array() );
				$noticeLanguages = $request->getArray( 'project_languages', array() );
				$isGeotargeted = $request->getCheck( 'geotargeted' );
				$numBuckets = $request->getInt( 'buckets', 1 );
				$countries = $request->getArray( 'geo_countries', array() );
			} else { // Defaults
				$start = $campaign[ 'start' ];
				$end = $campaign[ 'end' ];
				$isEnabled = ( $campaign[ 'enabled' ] == '1' );
				$priority = $campaign[ 'preferred' ];
				$throttle = intval( $campaign[ 'throttle' ] );
				$isLocked = ( $campaign[ 'locked' ] == '1' );
				$isArchived = ( $campaign[ 'archived' ] == '1' );
				$noticeProjects = Campaign::getNoticeProjects( $notice );
				$noticeLanguages = Campaign::getNoticeLanguages( $notice );
				$isGeotargeted = ( $campaign[ 'geo' ] == '1' );
				$numBuckets = intval( $campaign[ 'buckets' ] );
				$countries = Campaign::getNoticeCountries( $notice );
			}
			$isThrottled = ($throttle < 100);

			// Build Html
			$htmlOut = '';
			$htmlOut .= Xml::tags( 'h2', null, $this->msg( 'centralnotice-notice-heading', $notice )->text() );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );

			// Rows
			// Start Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-start-date' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'start', $this->editable, $start ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Start Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-start-time' )->escaped() );
			$htmlOut .= $this->timeSelectorTd( 'start', $this->editable, $start );
			$htmlOut .= Xml::closeElement( 'tr' );
			// End Date
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-end-date' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(), $this->dateSelector( 'end', $this->editable, $end ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// End Time
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-end-time' )->escaped() );
			$htmlOut .= $this->timeSelectorTd( 'end', $this->editable, $end );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Project
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->msg( 'centralnotice-projects' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(),
				$this->projectMultiSelector( $noticeProjects ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Languages
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->msg( 'centralnotice-languages' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(),
				$this->languageMultiSelector( $noticeLanguages ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Countries
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-geo' )->text(), 'geotargeted' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::check( 'geotargeted', $isGeotargeted,
					array_replace(
						$readonly,
						array( 'value' => $notice, 'id' => 'geotargeted' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr', array( 'id'=> 'geoMultiSelector' ) );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->msg( 'centralnotice-countries' )->escaped() );
			$htmlOut .= Xml::tags( 'td', array(), $this->geoMultiSelector( $countries ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// User bucketing
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-buckets' )->text(), 'buckets' ) );
			$htmlOut .= Xml::tags( 'td', array(),
			$this->numBucketsDropDown( $wgNoticeNumberOfBuckets, $numBuckets ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Enabled
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-enabled' )->text(), 'enabled' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::check( 'enabled', $isEnabled,
					array_replace( $readonly,
						array( 'value' => $notice, 'id' => 'enabled' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Preferred / Priority
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-preferred' )->text(), 'priority' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				$this::prioritySelector( false, $this->editable, $priority ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Throttle impressions
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-throttle' )->text(), 'throttle-enabled' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::check( 'throttle-enabled', $isThrottled,
					array_replace( $readonly,
						array( 'value' => $notice, 'id' => 'throttle-enabled' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			// Throttle value
			$htmlOut .= Xml::openElement( 'tr', array( 'class' => 'cn-throttle-amount' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-throttle-amount' )->text(), 'throttle' ) );
			$throttleLabel = strval( $throttle ) . "%";
			if ( $this->editable ) {
				$htmlOut .= Xml::tags( 'td', array(),
					Xml::span( $throttleLabel, 'cn-throttle', array( 'id' => 'centralnotice-throttle-echo' ) ) .
					Html::hidden( 'throttle-cur', $throttle, array( 'id' => 'centralnotice-throttle-cur' ) ) .
					Xml::tags( 'div', array( 'id' => 'centralnotice-throttle-amount' ), '' ) );
			} else {
				$htmlOut .= Xml::tags( 'td', array(), $throttleLabel );
			}
			$htmlOut .= Xml::closeElement( 'tr' );
			// Locked
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::label( $this->msg( 'centralnotice-locked' )->text(), 'locked' ) );
			$htmlOut .= Xml::tags( 'td', array(),
				Xml::check( 'locked', $isLocked,
					array_replace( $readonly,
						array( 'value' => $notice, 'id' => 'locked' ) ) ) );
			$htmlOut .= Xml::closeElement( 'tr' );
			if ( $this->editable ) {
				// Locked
				$htmlOut .= Xml::openElement( 'tr' );
				$htmlOut .= Xml::tags( 'td', array(),
					Xml::label( $this->msg( 'centralnotice-archive-campaign' )->text(), 'archive' ) );
				$htmlOut .= Xml::tags( 'td', array(),
					Xml::check( 'archive', $isArchived,
						array( 'value' => $notice, 'id' => 'archive' ) ) );
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );

			// Create controls for campaign-associated mixins (if there are any)
			if ( count( $wgCentralNoticeCampaignMixins > 0 ) ) {

				$mixinsThisNotice = Campaign::getCampaignMixins( $notice );

				$htmlOut .= Xml::fieldset(
					$this->msg( 'centralnotice-notice-mixins-fieldset' )->text() );

				foreach ( $wgCentralNoticeCampaignMixins
					as $mixinName => $mixinDef ) {

					$mixinControlName = self::makeNoticeMixinControlName( $mixinName );

					$attribs = array(
						'value' => $notice,
						'class' => 'noticeMixinCheck',
						'id' => $mixinControlName,
						'data-mixin-name' => $mixinName
					);

					if ( isset( $mixinsThisNotice[$mixinName] ) ) {

						// We have data on the mixin for this campaign, though
						// it may not have been enabled.

						$checked = $mixinsThisNotice[$mixinName]['enabled'];

						$attribs['data-mixin-param-values'] =
							FormatJson::encode(
							$mixinsThisNotice[$mixinName]['parameters'] );

					} else {

						// No data; it's never been enabled for this campaign
						// before. Note: default settings values are set on the
						// client.
						$checked = false;
					}

					$htmlOut .= Xml::openElement( 'div' );

					$htmlOut .= Xml::check(
						$mixinControlName,
						$checked,
						array_replace( $readonly, $attribs )
					);

					$htmlOut .= Xml::label(
						$this->msg( $mixinDef['nameMsg'] )->text(),
						$mixinControlName,
						array( 'for' => $mixinControlName )
					);

					if ( !empty( $mixinDef['helpMsg'] ) ) {
						$htmlOut .= Html::element( 'div',
							array( 'class' => 'htmlform-help' ),
							$this->msg( $mixinDef['helpMsg'] )->text()
						);
					}

					$htmlOut .= Xml::closeElement( 'div' );

				}

				$htmlOut .= Xml::closeElement( 'fieldset' );
			}

			return $htmlOut;
		} else {
			return '';
		}
	}

	protected static function makeNoticeMixinControlName(
			$mixinName, $mixinParam=null ) {

		return 'notice-mixin-' . $mixinName .
			( $mixinParam ? '-' . $mixinParam : '' );
	}

	/**
	 * Create form for managing banners assigned to a campaign
	 *
	 * Common campaign misconfigurations will cause warnings to appear
	 * at the top of this form.
	 */
	function assignedTemplatesForm( $notice ) {
		global $wgNoticeNumberOfBuckets;

		$dbr = CNDatabase::getDb();
		$res = $dbr->select(
			// Aliases are needed to avoid problems with table prefixes
			array(
				'notices' => 'cn_notices',
				'assignments' => 'cn_assignments',
				'templates' => 'cn_templates'
			),
			array(
				'templates.tmp_id',
				'templates.tmp_name',
				'assignments.tmp_weight',
				'assignments.asn_bucket',
				'notices.not_buckets',
			),
			array(
				'notices.not_name' => $notice,
				'notices.not_id = assignments.not_id',
				'assignments.tmp_id = templates.tmp_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'assignments.asn_bucket, notices.not_id' )
		);

		// No banners found
		if ( $dbr->numRows( $res ) < 1 ) {
			return '';
		}

		if ( $this->editable ) {
			$readonly = array();
		} else {
			$readonly = array( 'disabled' => 'disabled' );
		}

		$weights = array();

		$banners = array();
		foreach ( $res as $row ) {
			$banners[] = $row;

			$weights[] = $row->tmp_weight;
		}
		$isBalanced = ( count( array_unique( $weights ) ) === 1 );

		// Build Assigned banners HTML
		$htmlOut = Html::hidden( 'change', 'weight' );
		$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-assigned-templates' )->text() );

		// Equal weight banners
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::label( $this->msg( 'centralnotice-balanced' )->text(), 'balanced' ) );
		$htmlOut .= Xml::tags( 'td', array(),
			Xml::check( 'balanced', $isBalanced,
				array_replace( $readonly,
					array( 'value' => $notice, 'id' => 'balanced' ) ) ) );
		$htmlOut .= Xml::closeElement( 'tr' );

		$htmlOut .= Xml::openElement( 'table',
			array(
				'cellpadding' => 9,
				'width'       => '100%'
			)
		);
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( "centralnotice-remove" )->text() );
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%', 'class' => 'cn-weight' ),
			$this->msg( 'centralnotice-weight' )->text() );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
			$this->msg( 'centralnotice-bucket' )->text() );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '70%' ),
			$this->msg( 'centralnotice-templates' )->text() );

		// Table rows
		foreach ( $banners as $row ) {
			$htmlOut .= Xml::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
					Xml::check( 'removeTemplates[]', false, array( 'value' => $row->tmp_name ) )
				);
			}

			// Weight
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'cn-weight' ),
				$this->weightDropDown( "weight[$row->tmp_id]", $row->tmp_weight )
			);

			// Bucket
			$numCampaignBuckets = min( intval( $row->not_buckets ), $wgNoticeNumberOfBuckets );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->bucketDropDown(
					"bucket[$row->tmp_id]",
					( $numCampaignBuckets == 1 ? null : intval( $row->asn_bucket ) ),
					$numCampaignBuckets
				)
			);

			// Banner
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				BannerRenderer::linkToBanner( $row->tmp_name )
			);

			$htmlOut .= Xml::closeElement( 'tr' );
		}
		$htmlOut .= XMl::closeElement( 'table' );
		$htmlOut .= Xml::closeElement( 'fieldset' );

		// Sneak in some extra processing, to detect errors in bucket assignment.
		// Test for campaign buckets without an assigned banner or with multiple banners.
		$assignedBuckets = array();
		$numBuckets = $this->campaign->getBuckets();
		foreach ( $banners as $banner ) {
			$bannerBucket = $banner->asn_bucket;
			$bannerName = $banner->tmp_name;

			// If this campaign uses bucketing, is more than one banner
			// assigned to any bucket?
			if ( $numBuckets > 1
				&& array_key_exists( $bannerBucket, $assignedBuckets )
			) {
				$this->campaignWarnings[] = array(
					'centralnotice-banner-overflowing-bucket', chr( $bannerBucket + ord( 'A' ) )
				);
			}
			$assignedBuckets[$bannerBucket] = $bannerName;
		}
		// Do any buckets not have a banner assigned?
		if ( count( $assignedBuckets ) < $numBuckets ) {
			$this->campaignWarnings[] = array(
				'centralnotice-banner-empty-bucket'
			);
		}

		return $htmlOut;
	}

	function weightDropDown( $name, $selected ) {
		$selected = intval($selected);

		if ( $this->editable ) {
			$html = Html::openElement( 'select', array( 'name' => $name ) );
			foreach ( range( 5, 100, 5 ) as $value ) {
				$html .= Xml::option( $value, $value, $value === $selected );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			return htmlspecialchars( $selected );
		}
	}

	function bucketDropDown( $name, $selected, $numberCampaignBuckets ) {
		global $wgNoticeNumberOfBuckets;

		$bucketLabel = function ( $val ) {
			return chr( $val + ord( 'A' ) );
		};

		if ( $this->editable ) {
			if ( $selected === null ) {
				$selected = 0; // default to bucket 'A'
			}
			$selected = $selected % $numberCampaignBuckets;

			$html = Html::openElement( 'select', array( 'name' => $name, 'id' => 'bucketSelector' ) );
			foreach ( range( 0, $wgNoticeNumberOfBuckets - 1 ) as $value ) {
				$attribs = array();
				if ( $value >= $numberCampaignBuckets ) {
					$attribs['disabled'] = 'disabled';
				}
				$html .= Xml::option( $bucketLabel( $value ), $value, $value === $selected, $attribs );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			if ( $selected === null ) {
				return '-';
			}
			return htmlspecialchars( $bucketLabel( $selected ) );
		}
	}

	function numBucketsDropDown( $numBuckets, $selected ) {
		if ( $selected === null ) {
			$selected = 1;
		}

		if ( $this->editable ) {
			$html = Html::openElement( 'select', array( 'name' => 'buckets', 'id' => 'buckets' ) );
			foreach ( range( 0, intval( log( $numBuckets, 2 ) ) ) as $value ) {
				$value = pow( 2, $value );
				$html .= Xml::option( $value, $value, $value === $selected );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			return htmlspecialchars( $selected );
		}
	}

	/**
	 * Create form for adding banners to a campaign
	 */
	function addTemplatesForm( $notice ) {
		// Sanitize input on search key and split out terms
		$searchTerms = $this->sanitizeSearchTerms( $this->getRequest()->getText( 'tplsearchkey' ) );

		$pager = new CentralNoticePager( $this, $searchTerms );

		// Build HTML
		$htmlOut = Xml::fieldset( $this->msg( 'centralnotice-available-templates' )->text() );

		// Banner search box
		$htmlOut .= Html::openElement( 'fieldset', array( 'id' => 'cn-template-searchbox' ) );
		$htmlOut .= Html::element( 'legend', null, $this->msg( 'centralnotice-filter-template-banner' )->text() );

		$htmlOut .= Html::element( 'label', array( 'for' => 'tplsearchkey' ), $this->msg( 'centralnotice-filter-template-prompt' )->text() );
		$htmlOut .= Html::input( 'tplsearchkey', $searchTerms );
		$htmlOut .= Html::element(
			'input',
			array(
				'type'=> 'submit',
				'name'=> 'template-search',
				'value' => $this->msg( 'centralnotice-filter-template-submit' )->text()
			)
		);

		$htmlOut .= Html::closeElement( 'fieldset' );

		// And now the banners, if any
		if ( $pager->getNumRows() > 0 ) {

			// Show paginated list of banners
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'cn-pager' ),
				$pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'cn-pager' ),
				$pager->getNavigationBar() );

		} else {
			$htmlOut .= $this->msg( 'centralnotice-no-templates' )->escaped();
		}
		$htmlOut .= Xml::closeElement( 'fieldset' );

		return $htmlOut;
	}

	/**
	 * Generates a multiple select list of all languages.
	 *
	 * @param $selected array The language codes of the selected languages
	 * @param $customisedOnly bool If true only languages which have some content are listed
	 *
	 * @return multiple select list
	 */
	function languageMultiSelector( $selected = array(), $customisedOnly = true ) {
		global $wgLanguageCode;

		// Retrieve the list of languages in user's language
		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );

		// Make sure the site language is in the list; a custom language code
		// might not have a defined name...
		if ( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}
		ksort( $languages );

		$options = "\n";
		foreach ( $languages as $code => $name ) {
			$options .= Xml::option(
				$this->msg( 'centralnotice-language-listing', $code, $name )->text(),
				$code,
				in_array( $code, $selected )
			) . "\n";
		}

		$properties = array(
			'multiple' => 'multiple',
			'id' =>'project_languages',
			'name' => 'project_languages[]',
			'class' => 'cn-multiselect'
		);
		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		return Xml::tags( 'select', $properties, $options );
	}

	/**
	 * Generates a multiple select list of all project types.
	 *
	 * @param $selected array The name of the selected project type
	 *
	 * @return multiple select list
	 */
	function projectMultiSelector( $selected = array() ) {
		global $wgNoticeProjects;

		$options = "\n";
		foreach ( $wgNoticeProjects as $project ) {
			$options .= Xml::option(
				$project,
				$project,
				in_array( $project, $selected )
			) . "\n";
		}

		$properties = array(
			'multiple' => 'multiple',
			'id' =>'projects',
			'name' => 'projects[]',
			'class' => 'cn-multiselect'
		);
		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		return Xml::tags( 'select', $properties, $options );
	}

	public static function dropDownList( $text, $values ) {
		$dropDown = "*{$text}\n";
		foreach ( $values as $value ) {
			$dropDown .= "**{$value}\n";
		}
		return $dropDown;
	}

	/**
	 * Create a  string with summary label and text field.
	 *
	 * @param bool $action If true, use a placeholder message appropriate for
	 *   a single action (such as creating a campaign).
	 * @return string
	 */
	public function makeSummaryField( $action = false ) {

		$placeholderMsg = $action ? 'centralnotice-change-summary-action-prompt'
			: 'centralnotice-change-summary-prompt';

		return
			Xml::element( 'label',
				array( 'class' => 'cn-change-summary-label' ),
				$this->msg( 'centralnotice-change-summary-label' )->escaped()
			) . Xml::element( 'input',
				array(
					'class' => 'cn-change-summary-input',
					'placeholder' => $this->msg( $placeholderMsg )->escaped(),
					'size' => 45,
					'name' => 'changeSummary'
				)
			);
	}

	protected function getSummaryFromRequest( $request ) {
		return static::truncateSummaryField( $request->getVal( 'changeSummary' ) );
	}

	protected function paddedRange( $begin, $end ) {
		$unpaddedRange = range( $begin, $end );
		$paddedRange = array();
		foreach ( $unpaddedRange as $number ) {
			$paddedRange[ ] = sprintf( "%02d", $number ); // pad number with 0 if needed
		}
		return $paddedRange;
	}

	function showError( $message ) {
		$this->getOutput()->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		$this->centralNoticeError = true;
	}

	/**
	 * Generates a multiple select list of all countries.
	 *
	 * @param $selected array The country codes of the selected countries
	 *
	 * @return multiple select list
	 */
	function geoMultiSelector( $selected = array() ) {
		$userLanguageCode = $this->getLanguage()->getCode();
		$countries = GeoTarget::getCountriesList( $userLanguageCode );
		$options = "\n";
		foreach ( $countries as $code => $name ) {
			$options .= Xml::option(
				$name,
				$code,
				in_array( $code, $selected )
			) . "\n";
		}

		$properties = array(
			'multiple' => 'multiple',
			'id'       => 'geo_countries',
			'name'     => 'geo_countries[]',
			'class'    => 'cn-multiselect'
		);
		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		return Xml::tags( 'select', $properties, $options );
	}

	/**
	 * Sanitizes template search terms by removing non alpha and ensuring space delimiting.
	 *
	 * @param $terms string Search terms to sanitize
	 *
	 * @return string Space delimited string
	 */
	public static function sanitizeSearchTerms( $terms ) {
		$retval = ' '; // The space is important... it gets trimmed later

		foreach ( preg_split( '/\s+/', $terms ) as $term ) {
			preg_match( '/[0-9a-zA-Z_\-]+/', $term, $matches );
			if ( $matches ) {
				$retval .= $matches[ 0 ];
				$retval .= ' ';
			}
		}

		return trim( $retval );
	}

	/**
	 * Truncate the summary field in a linguistically appropriate way.
	 */
	public static function truncateSummaryField( $summary ) {
		global $wgContLang;
		return $wgContLang->truncate( $summary, 255 );
	}

	/**
	 * Adds CentralNotice specific navigation tabs to the UI.
	 * Implementation of SkinTemplateNavigation::SpecialPage hook.
	 *
	 * @param Skin  $skin Reference to the Skin object
	 * @param array $tabs Any current skin tabs
	 *
	 * @return boolean
	 */
	public static function addNavigationTabs( Skin $skin, array &$tabs ) {
		global $wgNoticeTabifyPages;

		$title = $skin->getTitle();
		list( $alias, $sub ) = SpecialPageFactory::resolveAlias( $title->getText() );

		if ( !array_key_exists( $alias, $wgNoticeTabifyPages ) ) {
			return true;
		}

		// Clear the special page tab that's there already
		$tabs['namespaces'] = array();

		// Now add our own
		foreach ( $wgNoticeTabifyPages as $page => $keys ) {
			$tabs[ $keys[ 'type' ] ][ $page ] = array(
				'text' => wfMessage( $keys[ 'message' ] ),
				'href' => SpecialPage::getTitleFor( $page )->getFullURL(),
				'class' => ( $alias === $page ) ? 'selected' : '',
			);
		}

		return true;
	}

	/**
	 * Loads a CentralNotice variable from session data.
	 *
	 * @param string $variable Name of the variable
	 * @param object $default Default value of the variable
	 *
	 * @return object Stored variable or default
	 */
	public function getCNSessionVar( $variable, $default = null ) {
		$val = $this->getRequest()->getSessionData( "centralnotice-$variable" );
		if ( is_null( $val ) ) {
			$val = $default;
		}

		return $val;
	}

	/**
	 * Sets a CentralNotice session variable. Note that this will fail silently if a
	 * session does not exist for the user.
	 *
	 * @param string $variable Name of the variable
	 * @param object $value    Value for the variable
	 */
	public function setCNSessionVar( $variable, $value ) {
		$this->getRequest()->setSessionData( "centralnotice-{$variable}", $value );
	}

	public function listProjects( $projects ) {
		global $wgNoticeProjects;
		return $this->makeShortList( $wgNoticeProjects, $projects );
	}

	public function listCountries( $countries ) {
		$all = array_keys( GeoTarget::getCountriesList( 'en' ) );
		return $this->makeShortList( $all, $countries );
	}

	public function listLanguages( $languages ) {
		$all = array_keys( Language::fetchLanguageNames( 'en' ) );
		return $this->makeShortList( $all, $languages );
	}

	protected function makeShortList( $all, $list ) {
		global $wgNoticeListComplementThreshold;
		//TODO ellipsis and js/css expansion
		if ( count($list) == count($all)  ) {
			return $this->getContext()->msg( 'centralnotice-all' )->text();
		}
		if ( count($list) > $wgNoticeListComplementThreshold * count($all) ) {
			$inverse = array_values( array_diff( $all, $list ) );
			$txt = $this->getContext()->getLanguage()->listToText( $inverse );
			return $this->getContext()->msg( 'centralnotice-all-except', $txt )->text();
		}
		return $this->getContext()->getLanguage()->listToText( array_values( $list ) );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
