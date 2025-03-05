<?php

use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Xml\Xml;

class CentralNotice extends UnlistedSpecialPage {

	// TODO review usage of Xml class and unnecessary openElement() and closeElement()
	// methods.

	// Note: These values are not arbitrary. Higher priority is indicated by a
	// higher value.
	public const LOW_PRIORITY = 0;
	public const NORMAL_PRIORITY = 1;
	public const HIGH_PRIORITY = 2;
	public const EMERGENCY_PRIORITY = 3;

	// String to use in drop-down to indicate no campaign type (repesented as null in DB)
	private const EMPTY_CAMPAIGN_TYPE_OPTION = 'empty-campaign-type-option';

	// When displaying a long list, display the complement "all except ~LIST"
	// past a threshold, given as a proportion of the "all" list length.
	private const LIST_COMPLEMENT_THRESHOLD = 0.75;

	/** @var bool|null */
	public $editable;
	/** @var bool|null */
	public $centralNoticeError;

	/**
	 * @var Campaign
	 */
	private $campaign;
	/** @var array */
	private $campaignWarnings = [];

	public function __construct() {
		// Register special page
		parent::__construct( 'CentralNotice' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Handle different types of page requests
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		// Begin output
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->addHelpLink(
			'//meta.wikimedia.org/wiki/Special:MyLanguage/Help:CentralNotice',
			true
		);

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
			if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly()
				|| CNDatabase::getDb( DB_PRIMARY )->isReadOnly()
			) {
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
					$out->redirect( $this->getPageTitle()->getLocalURL() );
					return;
				}
			} else {
				$this->showError( 'sessionfailure' );
			}
		}

		$this->outputListOfNotices( $subaction === 'showArchived' );
	}

	/**
	 * Output the start tag for the enclosing div we use on all subactions
	 */
	private function outputEnclosingDivStartTag() {
		$this->getOutput()->addHTML( Html::openElement( 'div', [ 'id' => 'preferences' ] ) );
	}

	/**
	 * Output the end tag for the enclosing div we use on all subactions
	 */
	private function outputEnclosingDivEndTag() {
		$this->getOutput()->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Send the list of notices (campaigns) to output and, if appropriate,
	 * the "Add campaign" form.
	 * @param bool|null $showArchived Set true to only show archived campaigns,
	 * 	 false to only show unarchived campaigns
	 */
	private function outputListOfNotices( $showArchived = null ) {
		$this->outputEnclosingDivStartTag();

		$out = $this->getOutput();
		$out->addModules( 'ext.centralNotice.adminUi' );

		$out->addHTML( Html::element( 'h2',
			[ 'class' => 'cn-special-section' ],
			$this->msg(
				$showArchived ? 'centralnotice-archived-campaigns' : 'centralnotice-manage'
			)->text()
		) );

		$out->addModules( 'ext.centralNotice.adminUi.campaignPager' );

		if ( $showArchived === false ) {
			$out->addHTML(
				$this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'CentralNotice' ),
					$this->msg( 'centralnotice-archive-show' )->text(),
					[],
					[ 'subaction' => 'showArchived' ]
				)
			);
		}

		$pager = new CNCampaignPager( $this, $this->editable, null, $showArchived );
		$popts = $out->parserOptions();
		$out->addHTML( $pager->getBodyOutput()->runOutputPipeline( $popts, [] )->getContentHolderText() );
		$out->addHTML( $pager->getNavigationBar() );

		// If the user has edit rights, show a form for adding a campaign
		if ( $this->editable && $showArchived !== true ) {
			$this->addNoticeForm();
		}

		$this->outputEnclosingDivEndTag();
	}

	private function handleNoticePostFromList() {
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
					$campaignChanges['archived'] );
			}

			if ( isset( $campaignChanges['locked'] ) ) {
				Campaign::setBooleanCampaignSetting( $campaignName, 'locked',
					$campaignChanges['locked'] );
			}

			if ( isset( $campaignChanges['enabled'] ) ) {
				Campaign::setBooleanCampaignSetting( $campaignName, 'enabled',
					$campaignChanges['enabled'] );
			}

			if ( isset( $campaignChanges['priority'] ) ) {
				Campaign::setNumericCampaignSetting(
					$campaignName,
					'preferred',
					intval( $campaignChanges['priority'] ),
					self::EMERGENCY_PRIORITY,
					self::LOW_PRIORITY
				);
			}

			if ( isset( $campaignChanges['campaign_type'] ) ) {
				$type = $campaignChanges['campaign_type'];
				$type = $type === self::EMPTY_CAMPAIGN_TYPE_OPTION ? null : $type;

				// Sanity check: does the requested campaign type exist?
				if ( $type && !CampaignType::getById( $type ) ) {
					$this->showError( 'centralnotice-non-existent-campaign-type-error' );
					return;
				}

				Campaign::setType( $campaignName, $type );
			}

			// Log any differences in settings
			$newSettings = Campaign::getCampaignSettings( $campaignName );
			$diffs = array_diff_assoc( $initialSettings, $newSettings );

			if ( $diffs ) {
				$campaignId = Campaign::getNoticeId( $campaignName );
				Campaign::processAfterCampaignChange(
					'modified',
					$campaignId,
					$campaignName,
					$this->getUser(),
					$initialSettings,
					$newSettings,
					$summary
				);
			}
		}
	}

	/**
	 * Render a field suitable for jquery.ui datepicker
	 * @param string $name
	 * @param bool $editable
	 * @param string|null $timestamp
	 * @return string
	 */
	protected function dateSelector( $name, $editable, $timestamp = null ) {
		if ( $editable ) {
			// Normalize timestamp format. If no timestamp is passed, default to now. If -1 is
			// passed, set no defaults.
			if ( $timestamp === -1 ) {
				$ts = '';
			} else {
				$ts = wfTimestamp( TS_MW, $timestamp );
			}

			$out = Html::element( 'input',
				[
					'id' => "{$name}Date",
					'name' => "{$name}Date",
					'type' => 'text',
					'class' => 'centralnotice-datepicker centralnotice-datepicker-limit_one_year',
				]
			);
			$out .= Html::element( 'input',
				[
					'id' => "{$name}Date_timestamp",
					'name' => "{$name}Date_timestamp",
					'type' => 'hidden',
					'value' => $ts,
				]
			);
			return $out;
		} else {
			return htmlspecialchars( $this->getLanguage()->date( $timestamp ) );
		}
	}

	/**
	 * @param string $prefix
	 * @param bool $editable
	 * @param string|null $timestamp
	 * @return string
	 */
	private function timeSelectorTd( $prefix, $editable, $timestamp = null ) {
		return Html::rawElement(
			'td',
			[
				'dir' => 'ltr', // Time is left-to-right in all languages
				'class' => 'cn-timepicker',
			],
			$this->timeSelector( $prefix, $editable, $timestamp )
		);
	}

	/**
	 * @param string $prefix
	 * @param bool $editable
	 * @param string|null $timestamp
	 * @return string
	 */
	private function timeSelector( $prefix, $editable, $timestamp = null ) {
		if ( $editable ) {
			$minutes = $this->paddedRange( 0, 59 );
			$hours = $this->paddedRange( 0, 23 );

			// Normalize timestamp format...
			$ts = wfTimestamp( TS_MW, $timestamp );

			$fields = [
				[ "hour", "centralnotice-hours", $hours, substr( $ts, 8, 2 ) ],
				[ "min", "centralnotice-min", $minutes, substr( $ts, 10, 2 ) ],
			];

			return $this->createSelector( $prefix, $fields );
		} else {
			return htmlspecialchars( $this->getLanguage()->time( $timestamp ) );
		}
	}

	/**
	 * @param bool $editable
	 * @param string|null $selectedTypeId
	 * @param string|null $index The name of the campaign (used when selector is included
	 *   in a list of campaigns by CNCampaignPager).
	 * @return string
	 */
	public function campaignTypeSelector( $editable, $selectedTypeId, $index = null ) {
		$types = CampaignType::getTypes();
		if ( $editable ) {
			$options = Xml::option(
				$this->msg( 'centralnotice-empty-campaign-type-option' )->plain(),
				self::EMPTY_CAMPAIGN_TYPE_OPTION
			);

			foreach ( $types as $type ) {
				$message = $this->msg( $type->getMessageKey() );
				$text = $message->exists() ? $message->text() : $type->getId();
				$options .= Xml::option(
					$text,
					$type->getId(),
					$selectedTypeId === $type->getId()
				);
			}

			// Handle the case of a type removed from config but still assigned to
			// a campaign in the DB.
			if ( $selectedTypeId && !CampaignType::getById( $selectedTypeId ) ) {
				$options .= Xml::option(
					$this->msg(
						'centralntoice-deleted-campaign-type',
						$selectedTypeId
					)->text(),
					$selectedTypeId,
					true
				);
			}

			// Data attributes set below (data-campaign-name and
			// data-initial-value) must coordinate with CNCampaignPager and
			// ext.centralNotice.adminUi.campaignPager.js

			$selectAttribs = [
				'name' => 'campaign_type',
			];

			if ( $selectedTypeId ) {
				$selectAttribs['data-initial-value'] = $selectedTypeId;
			}
			if ( $index ) {
				$selectAttribs['data-campaign-name'] = $index;
			}

			return Html::rawElement( 'select', $selectAttribs, $options );

		} else {
			if ( $selectedTypeId ) {
				$type = CampaignType::getById( $selectedTypeId );
				// We might get a null type if the DB has type identifiers that are
				// not currently in the configuraiton.
				if ( $type ) {
					$message = $this->msg( $type->getMessageKey() );
					return $message->exists()
						? $message->escaped()
						: htmlspecialchars( $type->getId() );
				} else {
					return htmlspecialchars( $selectedTypeId );
				}
			}
			return $this->msg( 'centralnotice-empty-campaign-type-option' )->escaped();
		}
	}

	/**
	 * Construct the priority select list for a campaign
	 *
	 * @param string|bool $index The name of the campaign (or false if it isn't needed)
	 * @param bool $editable Whether or not the form is editable by the user
	 * @param int $priorityValue The current priority value for this campaign
	 *
	 * @return string HTML for the select list
	 */
	public function prioritySelector( $index, $editable, $priorityValue ) {
		$priorities = [
			self::LOW_PRIORITY => $this->msg( 'centralnotice-priority-low' ),
			self::NORMAL_PRIORITY =>
				$this->msg( 'centralnotice-priority-normal' ),
			self::HIGH_PRIORITY => $this->msg( 'centralnotice-priority-high' ),
			self::EMERGENCY_PRIORITY =>
				$this->msg( 'centralnotice-priority-emergency' ),
		];

		if ( $editable ) {
			$options = ''; // The HTML for the select list options
			foreach ( $priorities as $key => $labelMsg ) {
				$options .= Xml::option( $labelMsg->text(), (string)$key, $priorityValue == $key );
			}

			// Data attributes set below (data-campaign-name and
			// data-initial-value) must coordinate with CNCampaignPager and
			// ext.centralNotice.adminUi.campaignPager.js

			$selectAttribs = [
				'name' => 'priority',
				'data-initial-value' => $priorityValue
			];

			if ( $index ) {
				$selectAttribs['data-campaign-name'] = $index;
			}

			return Html::rawElement( 'select', $selectAttribs, $options );
		} else {
			return $priorities[$priorityValue]->escaped();
		}
	}

	/**
	 * Build a set of select lists. Used by timeSelector.
	 * @param string $prefix string to identify selector set, for example, 'start' or 'end'
	 * @param array $fields array of select lists to build
	 * @return string
	 */
	private function createSelector( $prefix, $fields ) {
		$out = '';
		foreach ( $fields as [ $field, $label, $set, $current ] ) {
			$out .= Xml::listDropdown( "{$prefix}[{$field}]",
				self::dropdownList( $this->msg( $label )->text(), $set ),
				'',
				$current );
		}
		return $out;
	}

	/**
	 * Output a form for adding a campaign.
	 *
	 */
	private function addNoticeForm() {
		$request = $this->getRequest();
		$start = null;
		$campaignType = null;
		$noticeProjects = [];
		$noticeLanguages = [];
		// If there was an error, we'll need to restore the state of the form
		if ( $request->wasPosted() && ( $request->getVal( 'subaction' ) === 'addCampaign' ) ) {
			$start = $this->getDateTime( 'start' );
			$noticeLanguages = $request->getArray( 'project_languages', [] );
			$noticeProjects = $request->getArray( 'projects', [] );
			$campaignType = $request->getText( 'campaign_type' );
		}
		'@phan-var array $noticeLanguages';
		'@phan-var array $noticeProjects';

		$htmlOut = '';

		// Section heading
		$htmlOut .= Html::element( 'h2',
			[ 'class' => 'cn-special-section' ],
			$this->msg( 'centralnotice-add-notice' )->text() );

		// Begin Add a campaign fieldset
		$htmlOut .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		// Form for adding a campaign
		$htmlOut .= Html::openElement( 'form', [ 'method' => 'post' ] );
		$htmlOut .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );
		$htmlOut .= Html::hidden( 'subaction', 'addCampaign' );

		$htmlOut .= Html::openElement( 'table', [ 'cellpadding' => 9 ] );

		// Name
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::element( 'td', [],
				$this->msg( 'centralnotice-notice-name' )->text()
			) .
			Html::rawElement( 'td', [],
				Xml::input( 'noticeName', 25, $request->getVal( 'noticeName', '' ) )
			)
		);

		// Campaign type selector
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::rawElement( 'td', [],
				Xml::label( $this->msg( 'centralnotice-campaign-type' )->text(), 'campaign_type' )
			) .
			Html::rawElement( 'td', [],
				$this->campaignTypeSelector( $this->editable, $campaignType )
			)
		);

		// Start Date
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::element( 'td', [],
				$this->msg( 'centralnotice-start-date' )->text()
			) .
			Html::rawElement( 'td', [], $this->dateSelector( 'start', $this->editable, $start ) )
		);
		// Start Time
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::element( 'td', [],
				$this->msg( 'centralnotice-start-time' )->text()
			) .
			$this->timeSelectorTd( 'start', $this->editable, $start )
		);
		// Project
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::element( 'td', [ 'valign' => 'top' ],
				$this->msg( 'centralnotice-projects' )->text()
			) .
			Html::rawElement( 'td', [], $this->projectMultiSelector( $noticeProjects ) )
		);
		// Languages
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::element( 'td', [ 'valign' => 'top' ],
				$this->msg( 'centralnotice-languages' )->text()
			) .
			Html::rawElement( 'td', [], $this->languageMultiSelector( $noticeLanguages ) )
		);
		// Countries
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Html::rawElement( 'td', [],
			Xml::label( $this->msg( 'centralnotice-geo' )->text(), 'geotargeted' ) );
		$htmlOut .= Html::rawElement( 'td', [],
			Html::check( 'geotargeted', false, [ 'value' => 1, 'id' => 'geotargeted' ] ) );
		$htmlOut .= Html::closeElement( 'tr' );

		// Locations multi-selector
		$htmlOut .= Html::openElement( 'tr', [ 'id' => 'centralnotice-geo-region-multiselector' ] );
		$htmlOut .= Html::element( 'td', [ 'valign' => 'top' ],
			$this->msg( 'centralnotice-location' )->text() );
		$htmlOut .= Html::rawElement( 'td', [], $this->geoMultiSelectorTree() );
		$htmlOut .= Html::closeElement( 'tr' );

		$htmlOut .= Html::closeElement( 'table' );
		$htmlOut .= Html::hidden( 'change', 'weight' );
		$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

		// Submit button
		$htmlOut .= Html::rawElement( 'div',
			[ 'class' => 'cn-buttons' ],
			$this->makeSummaryField( true ) .
			Html::submitButton( $this->msg( 'centralnotice-modify' )->text() )
		);

		$htmlOut .= Html::closeElement( 'form' );

		// End Add a campaign fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		// Output HTML
		$this->getOutput()->addHTML( $htmlOut );
	}

	private function handleAddCampaignPost() {
		$request = $this->getRequest();
		$noticeName = $request->getVal( 'noticeName' );
		$start = $this->getDateTime( 'start' );
		$projects = $request->getArray( 'projects' );
		$project_languages = $request->getArray( 'project_languages' );
		$geotargeted = $request->getCheck( 'geotargeted' );

		$geo_countries = $request->getVal( 'geo_countries' );
		if ( $geo_countries ) {
			$geo_countries = explode( ',', $geo_countries );
		} else {
			$geo_countries = [];
		}

		$geo_regions = $request->getVal( 'geo_regions' );
		if ( $geo_regions ) {
			$geo_regions = explode( ',', $geo_regions );
		} else {
			$geo_regions = [];
		}

		$campaignType = $request->getText( 'campaign_type' );
		$campaignType =
			$campaignType === self::EMPTY_CAMPAIGN_TYPE_OPTION ? null : $campaignType;

		// Sanity check: does the requested campaign type exist?
		if ( $campaignType && !CampaignType::getById( $campaignType ) ) {
			$this->showError( 'centralnotice-non-existent-campaign-type-error' );
			return;
		}

		if ( $noticeName == '' ) {
			$this->showError( 'centralnotice-null-string' );
		} else {
			$result = Campaign::addCampaign(
				$noticeName,
				false,
				$start,
				$projects,
				$project_languages,
				$geotargeted,
				$geo_countries,
				$geo_regions,
				100,
				self::NORMAL_PRIORITY,
				$this->getUser(),
				$campaignType,
				$this->getSummaryFromRequest( $request )
			);
			if ( is_string( $result ) ) {
				// TODO Better error handling
				$this->showError( $result );
			}
		}
	}

	/**
	 * Retrieve jquery.ui.datepicker date and homebrew time,
	 * and return as a MW timestamp string.
	 * @param string $prefix
	 * @return null|string
	 */
	private function getDateTime( $prefix ) {
		$request = $this->getRequest();
		// Check whether the user left the date field blank.
		// Interpret any form of "empty" as a blank value.
		$manual_entry = $request->getVal( "{$prefix}Date" );
		if ( !$manual_entry ) {
			return null;
		}

		$datestamp = $request->getVal( "{$prefix}Date_timestamp" );
		$timeArray = $request->getArray( $prefix );
		$timestamp = substr( $datestamp, 0, 8 ) .
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$timeArray[ 'hour' ] .
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$timeArray[ 'min' ] . '00';
		return $timestamp;
	}

	/**
	 * Show the interface for viewing/editing an individual campaign
	 *
	 * @param string $notice The name of the campaign to view
	 *
	 */
	private function outputNoticeDetail( $notice ) {
		$out = $this->getOutput();

		// Output specific ResourceLoader module
		$out->addModules( 'ext.centralNotice.adminUi.campaignManager' );

		// Output ResourceLoader modules for campaign mixins with custom controls
		foreach ( $this->getConfig()->get( 'CentralNoticeCampaignMixins' ) as $mixinConfig ) {
			if ( !empty( $mixinConfig['customAdminUIControlsModule'] ) ) {
				$out->addModules( $mixinConfig['customAdminUIControlsModule'] );
			}
		}

		$this->outputEnclosingDivStartTag();

		// Todo: Convert the rest of this page to use this object
		$this->campaign = new Campaign( $notice );
		try {
			if ( $this->campaign->isArchived() || $this->campaign->isLocked() ) {
				$out->setSubtitle( $this->msg( 'centralnotice-archive-edit-prevented' ) );
				$this->editable = false; // Todo: Fix this gross hack to prevent editing
			}
			$out->addSubtitle(
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CentralNoticeLogs' ),
					$this->msg( 'centralnotice-campaign-view-logs' )->text(),
					[],
					[
						'log_type' => 'campaignSettings',
						'campaign' => $notice
					]
				)
			);
		} catch ( CampaignExistenceException $ex ) {
			throw new ErrorPageError( 'centralnotice', 'centralnotice-notice-doesnt-exist' );
		}

		if ( $this->editable && $this->getRequest()->wasPosted() ) {
			$this->handleNoticeDetailPost( $notice );
		}

		$htmlOut = '';

		// Begin Campaign detail fieldset
		$htmlOut .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		if ( $this->editable ) {
			$htmlOut .= Html::openElement( 'form',
				[
					'method' => 'post',
					'id' => 'centralnotice-notice-detail',
					'autocomplete' => 'off',
					'action' => $this->getPageTitle()->getLocalURL( [
						'subaction' => 'noticeDetail',
						'notice' => $notice
					] )
				]
			);
		}

		$output_detail = $this->noticeDetailForm( $notice );
		$output_assigned = $this->assignedTemplatesForm( $notice );
		$output_templates = $this->addTemplatesForm();

		$htmlOut .= $output_detail;

		// Catch for no banners so that we don't double message
		if ( $output_assigned == '' && $output_templates == '' ) {
			$htmlOut .= $this->msg( 'centralnotice-no-templates' )->escaped();
			$htmlOut .= Html::element( 'p' );
			$newPage = $this->getTitleFor( 'NoticeTemplate', 'add' );
			$htmlOut .= $this->getLinkRenderer()->makeLink(
				$newPage,
				$this->msg( 'centralnotice-add-template' )->text()
			);
			$htmlOut .= Html::element( 'p' );
		} elseif ( $output_assigned == '' ) {
			$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-assigned-templates' )->text() );
			$htmlOut .= $this->msg( 'centralnotice-no-templates-assigned' )->escaped();
			$htmlOut .= Html::closeElement( 'fieldset' );
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
			$htmlOut .= Html::rawElement( 'div',
				[ 'class' => 'cn-buttons' ],
				Html::submitButton(
					$this->msg( 'centralnotice-modify' )->text(),
					[ 'id' => 'noticeDetailSubmit' ]
				)
			);
		}

		if ( $this->editable ) {
			$htmlOut .= Html::closeElement( 'form' );
		}
		$htmlOut .= Html::closeElement( 'fieldset' );

		$this->displayCampaignWarnings();

		$out->addHTML( $htmlOut );
		$this->outputEnclosingDivEndTag();
	}

	/**
	 * Process a post request from the campaign (notice) detail subaction. Make
	 * changes to the campaign based on the post parameters.
	 *
	 * @param string $notice
	 */
	private function handleNoticeDetailPost( $notice ) {
		$request = $this->getRequest();

		// If what we're doing is actually serious (ie: not updating the banner
		// filter); process the request. Recall that if the serious request
		// succeeds, the page will be reloaded again.
		if ( !$request->getCheck( 'template-search' ) ) {
			// Check authentication token
			if ( $this->getUser()->matchEditToken( $request->getVal( 'authtoken' ) ) ) {
				// Handle removing campaign
				if ( $request->getVal( 'archive' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'archived', true );
				}

				$initialCampaignSettings = Campaign::getCampaignSettings( $notice );

				// Handle locking/unlocking campaign
				Campaign::setBooleanCampaignSetting(
					$notice, 'locked', $request->getCheck( 'locked' )
				);

				// Handle enabling/disabling campaign
				Campaign::setBooleanCampaignSetting(
					$notice, 'enabled', $request->getCheck( 'enabled' )
				);

				// Set campaign traffic throttle
				if ( $request->getCheck( 'throttle-enabled' ) ) {
					$throttle = $request->getInt( 'throttle-cur', 100 );
				} else {
					$throttle = 100;
				}
				Campaign::setNumericCampaignSetting( $notice, 'throttle', $throttle, 100, 0 );

				$config = $this->getConfig();
				$noticeNumberOfBuckets = $config->get( 'NoticeNumberOfBuckets' );
				// Handle user bucketing setting for campaign
				$numCampaignBuckets = min( $request->getInt( 'buckets', 1 ),
					$noticeNumberOfBuckets );
				$numCampaignBuckets = pow( 2, floor( log( $numCampaignBuckets, 2 ) ) );

				Campaign::setNumericCampaignSetting(
					$notice,
					'buckets',
					$numCampaignBuckets,
					$noticeNumberOfBuckets,
					1
				);

				// Handle setting campaign priority
				Campaign::setNumericCampaignSetting(
					$notice,
					'preferred',
					$request->getInt( 'priority', self::NORMAL_PRIORITY ),
					self::EMERGENCY_PRIORITY,
					self::LOW_PRIORITY
				);

				// Handle setting campaign type

				$type = $request->getText( 'campaign_type' );
				$type = $type === self::EMPTY_CAMPAIGN_TYPE_OPTION ? null : $type;

				// Sanity check: does the requested campaign type exist?
				if ( $type && !CampaignType::getById( $type ) ) {
					$this->showError( 'centralnotice-non-existent-campaign-type-error' );
					return;
				}

				Campaign::setType( $notice, $type );

				// Handle updating geotargeting
				if ( $request->getCheck( 'geotargeted' ) ) {
					Campaign::setBooleanCampaignSetting( $notice, 'geo', true );

					$countries = $this->listToArray( $request->getVal( 'geo_countries' ) );
					Campaign::updateCountries( $notice, $countries );

					// Regions in format CountryCode_RegionCode
					$regions = $this->listToArray( $request->getVal( 'geo_regions' ) );
					Campaign::updateRegions( $notice, $regions );

				} else {
					Campaign::setBooleanCampaignSetting( $notice, 'geo', false );
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
							// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
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
				foreach ( $config->get( 'CentralNoticeCampaignMixins' ) as $mixinName => $mixinDef ) {
					$mixinControlName = self::makeNoticeMixinControlName( $mixinName );

					if ( $request->getCheck( $mixinControlName ) ) {
						$params = [];

						foreach ( $mixinDef['parameters'] as $paramName => $paramDef ) {
							$requestParamName =
								self::makeNoticeMixinControlName( $mixinName, $paramName );

							switch ( $paramDef['type'] ) {
								case 'string':
								case 'json':
									$paramVal = Sanitizer::removeSomeTags(
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

								default:
									throw new DomainException(
										"Unknown parameter type: '{$paramDef['type']}'" );
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

				Campaign::processAfterCampaignChange(
					'modified', $campaignId, $notice, $this->getUser(),
					$initialCampaignSettings, $finalCampaignSettings,
					$summary );

				// If there were no errors, reload the page to prevent duplicate form submission
				if ( !$this->centralNoticeError ) {
					$this->getOutput()->redirect( $this->getPageTitle()->getLocalURL( [
						'subaction' => 'noticeDetail',
						'notice' => $notice
					] ) );
					return;
				}

				ChoiceDataProvider::invalidateCache();
			} else {
				$this->showError( 'sessionfailure' );
			}
		}
	}

	/**
	 * Output stored campaign warnings
	 */
	private function displayCampaignWarnings() {
		foreach ( $this->campaignWarnings as $message ) {
			$this->getOutput()->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		}
	}

	/**
	 * Create form for managing campaign settings (start date, end date, languages, etc.)
	 * @param string $notice
	 * @return string HTML
	 */
	private function noticeDetailForm( $notice ) {
		if ( $this->editable ) {
			$readonly = [];
		} else {
			$readonly = [ 'disabled' => 'disabled' ];
		}

		$campaign = Campaign::getCampaignSettings( $notice );

		if ( $campaign ) {
			// If there was an error, we'll need to restore the state of the form
			$request = $this->getRequest();

			if ( $request->wasPosted() ) {
				$start = $this->getDateTime( 'start' );
				$end = $this->getDateTime( 'end' );
				$isEnabled = $request->getCheck( 'enabled' );
				$priority = $request->getInt( 'priority', self::NORMAL_PRIORITY );
				$throttle = $request->getInt( 'throttle', 100 );
				$isLocked = $request->getCheck( 'locked' );
				$isArchived = $request->getCheck( 'archived' );
				$noticeProjects = $request->getArray( 'projects', [] );
				$noticeLanguages = $request->getArray( 'project_languages', [] );
				$isGeotargeted = $request->getCheck( 'geotargeted' );
				$numBuckets = $request->getInt( 'buckets', 1 );
				$countries = $this->listToArray( $request->getVal( 'geo_countries' ) );
				$regions = $this->listToArray( $request->getVal( 'geo_regions' ) );
				$type = $request->getText( 'type' );
			} else { // Defaults
				$start = $campaign[ 'start' ];
				$end = $campaign[ 'end' ];
				$isEnabled = (bool)$campaign['enabled'];
				$priority = $campaign[ 'preferred' ];
				$throttle = intval( $campaign[ 'throttle' ] );
				$isLocked = (bool)$campaign['locked'];
				$isArchived = (bool)$campaign['archived'];
				$noticeProjects = Campaign::getNoticeProjects( $notice );
				$noticeLanguages = Campaign::getNoticeLanguages( $notice );
				$isGeotargeted = (bool)$campaign['geo'];
				$numBuckets = intval( $campaign[ 'buckets' ] );
				$countries = Campaign::getNoticeCountries( $notice );
				$regions = Campaign::getNoticeRegions( $notice );
				$type = $campaign['type'];
			}
			'@phan-var array $noticeLanguages';
			'@phan-var array $noticeProjects';
			$isThrottled = ( $throttle < 100 );
			$type = $type === self::EMPTY_CAMPAIGN_TYPE_OPTION ? null : $type;

			// Build Html
			$htmlOut = '';
			$htmlOut .= Html::rawElement( 'h2', [],
				$this->msg( 'centralnotice-notice-heading', $notice )->parse() );
			$htmlOut .= Html::openElement( 'table', [ 'cellpadding' => 9 ] );

			// Rows
			// Campaign type selector
			$htmlOut .= Html::openElement( 'tr' );
			$htmlOut .= Html::rawElement( 'td', [],
				Xml::label( $this->msg( 'centralnotice-campaign-type' )->text(), 'campaign_type' ) );
			$htmlOut .= Html::rawElement( 'td', [],
				$this->campaignTypeSelector( $this->editable, $type ) );
			$htmlOut .= Html::closeElement( 'tr' );

			// Start Date
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [],
					$this->msg( 'centralnotice-start-date' )->text()
				) .
				Html::rawElement( 'td', [],
					$this->dateSelector( 'start', $this->editable, $start )
				)
			);
			// Start Time
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [],
					$this->msg( 'centralnotice-start-time' )->text()
				) .
				$this->timeSelectorTd( 'start', $this->editable, $start )
			);
			// End Date
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [],
					$this->msg( 'centralnotice-end-date' )->text()
				) .
				Html::rawElement( 'td', [],
					$this->dateSelector( 'end', $this->editable, $end )
				)
			);
			// End Time
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [],
					$this->msg( 'centralnotice-end-time' )->text()
				) .
				$this->timeSelectorTd( 'end', $this->editable, $end )
			);
			// Project
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [ 'valign' => 'top' ],
					$this->msg( 'centralnotice-projects' )->text()
				) .
				Html::rawElement( 'td', [], $this->projectMultiSelector( $noticeProjects ) )
			);
			// Languages
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::element( 'td', [ 'valign' => 'top' ],
					$this->msg( 'centralnotice-languages' )->text()
				) .
				Html::rawElement( 'td', [], $this->languageMultiSelector( $noticeLanguages ) )
			);
			// Countries
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-geo' )->text(), 'geotargeted' )
				) .
				Html::rawElement( 'td', [],
					Html::check( 'geotargeted', $isGeotargeted,
						array_replace( $readonly, [ 'value' => $notice, 'id' => 'geotargeted' ] )
					)
				)
			);

			// Locations multi-selector
			$htmlOut .= Html::rawElement( 'tr',
				[ 'id' => 'centralnotice-geo-region-multiselector' ],
				Html::element( 'td', [ 'valign' => 'top' ],
					$this->msg( 'centralnotice-location' )->text()
				) .
				Html::rawElement( 'td', [], $this->geoMultiSelectorTree( $countries, $regions ) )
			);

			$config = $this->getConfig();
			// User bucketing
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-buckets' )->text(), 'buckets' )
				) .
				Html::rawElement( 'td', [],
					$this->numBucketsDropdown( $config->get( 'NoticeNumberOfBuckets' ), $numBuckets )
				)
			);
			// Enabled
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-enabled' )->text(), 'enabled' )
				) .
				Html::rawElement( 'td', [],
					Html::check( 'enabled', $isEnabled,
						array_replace( $readonly, [ 'value' => $notice, 'id' => 'enabled' ] )
					)
				)
			);
			// Preferred / Priority
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-preferred' )->text(), 'priority' )
				) .
				Html::rawElement( 'td', [],
					$this::prioritySelector( false, $this->editable, $priority )
				)
			);
			// Throttle impressions
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-throttle' )->text(), 'throttle-enabled' )
				) .
				Html::rawElement( 'td', [],
					Html::check( 'throttle-enabled', $isThrottled,
						array_replace( $readonly, [ 'value' => $notice, 'id' => 'throttle-enabled' ] )
					)
				)
			);
			// Throttle value
			$htmlOut .= Html::openElement( 'tr', [ 'class' => 'cn-throttle-amount' ] );
			$htmlOut .= Html::rawElement( 'td', [],
				Xml::label( $this->msg( 'centralnotice-throttle-amount' )->text(), 'throttle' ) );
			$throttleLabel = $this->msg( 'percent' )->numParams( $throttle )->text();
			if ( $this->editable ) {
				$htmlOut .= Html::rawElement( 'td', [],
					Html::element( 'span',
						[ 'class' => 'cn-throttle', 'id' => 'centralnotice-throttle-echo' ],
						$throttleLabel ) .
					Html::hidden( 'throttle-cur', $throttle,
						[ 'id' => 'centralnotice-throttle-cur' ] ) .
					Html::rawElement( 'div', [ 'id' => 'centralnotice-throttle-amount' ], '' ) );
			} else {
				$htmlOut .= Html::element( 'td', [], $throttleLabel );
			}
			$htmlOut .= Html::closeElement( 'tr' );
			// Locked
			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-locked' )->text(), 'locked' )
				) .
				Html::rawElement( 'td', [],
					Html::check( 'locked', $isLocked,
						array_replace( $readonly, [ 'value' => $notice, 'id' => 'locked' ] )
					)
				)
			);
			if ( $this->editable ) {
				// Locked
				$htmlOut .= Html::rawElement( 'tr', [],
					Html::rawElement( 'td', [],
						Xml::label( $this->msg( 'centralnotice-archive-campaign' )->text(), 'archive' )
					) .
					Html::rawElement( 'td', [],
						Html::check( 'archive', $isArchived, [ 'value' => $notice, 'id' => 'archive' ] )
					)
				);
			}
			$htmlOut .= Html::closeElement( 'table' );

			// Create controls for campaign-associated mixins (if there are any)
			$centralNoticeCampaignMixins = $config->get( 'CentralNoticeCampaignMixins' );
			if ( $centralNoticeCampaignMixins ) {
				$mixinsThisNotice = Campaign::getCampaignMixins( $notice );

				$htmlOut .= Xml::fieldset(
					$this->msg( 'centralnotice-notice-mixins-fieldset' )->text() );

				foreach ( $centralNoticeCampaignMixins as $mixinName => $mixinDef ) {
					$mixinControlName = self::makeNoticeMixinControlName( $mixinName );

					$attribs = [
						'value' => $notice,
						'class' => 'noticeMixinCheck',
						'id' => $mixinControlName,
						'data-mixin-name' => $mixinName
					];

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

					$htmlOut .= Html::openElement( 'div' );

					$htmlOut .= Html::check(
						$mixinControlName,
						$checked,
						array_replace( $readonly, $attribs )
					);

					$htmlOut .= Xml::label(
						$this->msg( $mixinDef['nameMsg'] )->text(),
						$mixinControlName,
						[ 'for' => $mixinControlName ]
					);

					if ( !empty( $mixinDef['helpMsg'] ) ) {
						$htmlOut .= Html::element( 'div',
							[ 'class' => 'htmlform-help' ],
							$this->msg( $mixinDef['helpMsg'] )->text()
						);
					}

					$htmlOut .= Html::closeElement( 'div' );

				}

				$htmlOut .= Html::closeElement( 'fieldset' );
			}

			return $htmlOut;
		} else {
			return '';
		}
	}

	private static function makeNoticeMixinControlName(
		string $mixinName, ?string $mixinParam = null
	): string {
		return 'notice-mixin-' . $mixinName .
			( $mixinParam ? '-' . $mixinParam : '' );
	}

	/**
	 * Create form for managing banners assigned to a campaign
	 *
	 * Common campaign misconfigurations will cause warnings to appear
	 * at the top of this form.
	 * @param string $notice
	 * @return string HTML
	 */
	private function assignedTemplatesForm( $notice ) {
		$dbr = CNDatabase::getDb();
		$res = $dbr->newSelectQueryBuilder()
			// Aliases are needed to avoid problems with table prefixes
			->select( [
				'templates.tmp_id',
				'templates.tmp_name',
				'assignments.tmp_weight',
				'assignments.asn_bucket',
				'notices.not_buckets',
			] )
			->from( 'cn_notices', 'notices' )
			->join( 'cn_assignments', 'assignments', 'notices.not_id = assignments.not_id' )
			->join( 'cn_templates', 'templates', 'assignments.tmp_id = templates.tmp_id' )
			->where( [
				'notices.not_name' => $notice,
			] )
			->orderBy( [ 'assignments.asn_bucket', 'notices.not_id' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// No banners found
		if ( $res->numRows() < 1 ) {
			return '';
		}

		if ( $this->editable ) {
			$readonly = [];
		} else {
			$readonly = [ 'disabled' => 'disabled' ];
		}

		$weights = [];

		$banners = [];
		foreach ( $res as $row ) {
			$banners[] = $row;

			$weights[] = $row->tmp_weight;
		}
		$isBalanced = ( count( array_unique( $weights ) ) === 1 );

		// Build Assigned banners HTML

		$htmlOut = Html::hidden( 'change', 'weight' );

		// Prepare data about assigned banners to provide to client-side code, and
		// make it available within the fieldsset element.

		$bannersForJS = array_map(
			static function ( $banner ) {
				return [
					'bannerName' => $banner->tmp_name,
					'bucket' => $banner->asn_bucket
				];
			},
			$banners
		);

		$htmlOut .= Xml::fieldset(
			$this->msg( 'centralnotice-assigned-templates' )->text(),
			false,
			[
				'data-assigned-banners' => json_encode( $bannersForJS ),
				'id' => 'centralnotice-assigned-banners'
			]
		);

		// Equal weight banners
		$htmlOut .= Html::rawElement( 'tr', [],
			Html::rawElement( 'td', [],
				Xml::label( $this->msg( 'centralnotice-balanced' )->text(), 'balanced' )
			) .
			Html::rawElement( 'td', [],
				Html::check( 'balanced', $isBalanced,
					array_replace( $readonly, [ 'value' => $notice, 'id' => 'balanced' ] )
				)
			)
		);

		$htmlOut .= Html::openElement( 'table',
			[
				'cellpadding' => 9,
				'width'       => '100%'
			]
		);
		if ( $this->editable ) {
			$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
				$this->msg( "centralnotice-remove" )->text() );
		}
		$htmlOut .= Html::element( 'th',
			[ 'align' => 'left', 'width' => '5%', 'class' => 'cn-weight' ],
			$this->msg( 'centralnotice-weight' )->text() );
		$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
			$this->msg( 'centralnotice-bucket' )->text() );
		$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'width' => '70%' ],
			$this->msg( 'centralnotice-templates' )->text() );

		// Table rows
		$noticeNumberOfBuckets = $this->getConfig()->get( 'NoticeNumberOfBuckets' );
		foreach ( $banners as $row ) {
			$htmlOut .= Html::openElement( 'tr' );

			if ( $this->editable ) {
				// Remove
				$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
					Html::check( 'removeTemplates[]', false, [
						'value' => $row->tmp_name,
						'class' => 'bannerRemoveCheckbox'
					] )
				);
			}

			// Weight
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'cn-weight' ],
				$this->weightDropdown( "weight[$row->tmp_id]", $row->tmp_weight )
			);

			// Bucket
			$numCampaignBuckets = min( intval( $row->not_buckets ), $noticeNumberOfBuckets );
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
				$this->bucketDropdown(
					"bucket[$row->tmp_id]",
					( $numCampaignBuckets == 1 ? null : intval( $row->asn_bucket ) ),
					$numCampaignBuckets,
					$row->tmp_name
				)
			);

			// Banner
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
				BannerRenderer::linkToBanner( $row->tmp_name )
			);

			$htmlOut .= Html::closeElement( 'tr' );
		}
		$htmlOut .= Html::closeElement( 'table' );
		$htmlOut .= Html::closeElement( 'fieldset' );

		// Sneak in some extra processing, to detect errors in bucket assignment.
		// Test for campaign buckets without an assigned banner or with multiple banners.
		$assignedBuckets = [];
		$numBuckets = $this->campaign->getBuckets();
		foreach ( $banners as $banner ) {
			$bannerBucket = $banner->asn_bucket;
			$bannerName = $banner->tmp_name;

			$assignedBuckets[$bannerBucket] = $bannerName;
		}
		// Do any buckets not have a banner assigned?
		if ( count( $assignedBuckets ) < $numBuckets ) {
			$this->campaignWarnings[] = [
				'centralnotice-banner-empty-bucket'
			];
		}

		return $htmlOut;
	}

	/**
	 * @param string $name
	 * @param int|null $selected
	 * @return string
	 */
	private function weightDropdown( $name, $selected ) {
		$selected = intval( $selected );

		if ( $this->editable ) {
			$html = Html::openElement( 'select', [ 'name' => $name ] );
			foreach ( range( 5, 100, 5 ) as $value ) {
				$html .= Xml::option( $value, (string)$value, $value === $selected );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			return htmlspecialchars( (string)$selected );
		}
	}

	/**
	 * @param string $name
	 * @param int|null $selected
	 * @param int $numberCampaignBuckets
	 * @param string $bannerName
	 * @return string
	 */
	private function bucketDropdown( $name, $selected, $numberCampaignBuckets, $bannerName ) {
		$bucketLabel = static function ( $val ) {
			return chr( $val + ord( 'A' ) );
		};

		if ( $this->editable ) {
			if ( $selected === null ) {
				$selected = 0; // default to bucket 'A'
			}
			$selected %= $numberCampaignBuckets;

			// bucketSelector class is for all bucket selectors (for assigned or
			// unassigned banners). Coordinate with CentralNoticePager::bucketDropdown().
			$html = Html::openElement( 'select', [
				'name' => $name,
				'class' => 'bucketSelector bucketSelectorForAssignedBanners',
				'data-banner-name' => $bannerName
			] );

			foreach ( range( 0, $this->getConfig()->get( 'NoticeNumberOfBuckets' ) - 1 ) as $value ) {
				$attribs = [];
				if ( $value >= $numberCampaignBuckets ) {
					$attribs['disabled'] = 'disabled';
				}
				$html .= Xml::option(
					$bucketLabel( $value ), $value, $value === $selected, $attribs
				);
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

	/**
	 * @param int $numBuckets
	 * @param int|null $selected
	 * @return string
	 */
	private function numBucketsDropdown( $numBuckets, $selected ) {
		if ( $selected === null ) {
			$selected = 1;
		}

		if ( $this->editable ) {
			$html = Html::openElement( 'select', [ 'name' => 'buckets', 'id' => 'buckets' ] );
			foreach ( range( 0, intval( log( $numBuckets, 2 ) ) ) as $value ) {
				$value = pow( 2, $value );
				$html .= Xml::option( (string)$value, (string)$value, $value === $selected );
			}
			$html .= Html::closeElement( 'select' );
			return $html;
		} else {
			return htmlspecialchars( (string)$selected );
		}
	}

	/**
	 * Create form for adding banners to a campaign
	 * @return string
	 */
	private function addTemplatesForm() {
		// Sanitize input on search key and split out terms
		$searchTerms = $this->sanitizeSearchTerms( $this->getRequest()->getText( 'tplsearchkey' ) );

		$pager = new CentralNoticePager( $this, $searchTerms );

		// Build HTML
		$htmlOut = Xml::fieldset( $this->msg( 'centralnotice-available-templates' )->text() );

		// Banner search box
		$htmlOut .= Html::openElement( 'fieldset', [ 'id' => 'cn-template-searchbox' ] );
		$htmlOut .= Html::element(
			'legend', [], $this->msg( 'centralnotice-filter-template-banner' )->text()
		);

		$htmlOut .= Html::element( 'label', [ 'for' => 'tplsearchkey' ],
			$this->msg( 'centralnotice-filter-template-prompt' )->text() );
		$htmlOut .= Html::input( 'tplsearchkey', $searchTerms );
		$htmlOut .= Html::element(
			'input',
			[
				'type' => 'submit',
				'name' => 'template-search',
				'value' => $this->msg( 'centralnotice-filter-template-submit' )->text()
			]
		);

		$htmlOut .= Html::closeElement( 'fieldset' );

		// And now the banners, if any
		if ( $pager->getNumRows() > 0 ) {
			// Show paginated list of banners
			$htmlOut .= Html::rawElement( 'div',
				[ 'class' => 'cn-pager' ],
				$pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Html::rawElement( 'div',
				[ 'class' => 'cn-pager' ],
				$pager->getNavigationBar() );

		} else {
			$htmlOut .= $this->msg( 'centralnotice-no-templates' )->escaped();
		}
		$htmlOut .= Html::closeElement( 'fieldset' );

		return $htmlOut;
	}

	/**
	 * Generates a multiple select list of all languages.
	 *
	 * @param array $selected The language codes of the selected languages
	 *
	 * @return string multiple select list
	 */
	private function languageMultiSelector( $selected = [] ) {
		// Retrieve the list of languages in user's language
		$languages = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageNames( $this->getLanguage()->getCode() );

		// Make sure the site language is in the list; a custom language code
		// might not have a defined name...
		$languageCode = $this->getConfig()->get( MainConfigNames::LanguageCode );
		if ( !array_key_exists( $languageCode, $languages ) ) {
			$languages[$languageCode] = $languageCode;
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

		$properties = [
			'multiple' => 'multiple',
			'id' => 'project_languages',
			'name' => 'project_languages[]',
			'class' => 'cn-multiselect',
			'autocomplete' => 'off'
		];
		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		return Html::rawElement( 'select', $properties, $options );
	}

	/**
	 * Generates a multiple select list of all project types.
	 *
	 * @param array $selected The name of the selected project type
	 *
	 * @return string multiple select list
	 */
	private function projectMultiSelector( $selected = [] ) {
		$options = "\n";
		foreach ( $this->getConfig()->get( 'NoticeProjects' ) as $project ) {
			$options .= Xml::option(
				$project,
				$project,
				in_array( $project, $selected )
			) . "\n";
		}

		$properties = [
			'multiple' => 'multiple',
			'id' => 'projects',
			'name' => 'projects[]',
			'class' => 'cn-multiselect',
			'autocomplete' => 'off'
		];
		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		return Html::rawElement( 'select', $properties, $options );
	}

	/**
	 * @param string $text
	 * @param string[] $values
	 * @return string
	 */
	public static function dropdownList( $text, $values ) {
		$dropdown = "*{$text}\n";
		foreach ( $values as $value ) {
			$dropdown .= "**{$value}\n";
		}
		return $dropdown;
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

		return Html::element( 'label',
				[ 'class' => 'cn-change-summary-label' ],
				$this->msg( 'centralnotice-change-summary-label' )->text()
			) . Html::element( 'input',
				[
					'class' => 'cn-change-summary-input',
					'placeholder' => $this->msg( $placeholderMsg )->text(),
					'size' => 45,
					'name' => 'changeSummary'
				]
			);
	}

	private function getSummaryFromRequest( WebRequest $request ): string {
		return static::truncateSummaryField( $request->getVal( 'changeSummary' ) );
	}

	/**
	 * @param int $begin
	 * @param int $end
	 * @return string[]
	 */
	private function paddedRange( $begin, $end ) {
		$unpaddedRange = range( $begin, $end );
		$paddedRange = [];
		foreach ( $unpaddedRange as $number ) {
			$paddedRange[] = sprintf( "%02d", $number ); // pad number with 0 if needed
		}
		return $paddedRange;
	}

	private function showError( string $message ) {
		$this->getOutput()->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		$this->centralNoticeError = true;
	}

	/**
	 * Generates a multiple select list of all countries.
	 *
	 * @param array $selectedCountries The country codes of the selected countries
	 * @param array $selectedRegions The unique region codes of the selected regions
	 *                               in format CountryCode_RegionCode
	 *
	 * @return string multiple select list
	 */
	private function geoMultiSelectorTree( $selectedCountries = [], $selectedRegions = [] ) {
		$userLanguageCode = $this->getLanguage()->getCode();
		$countries = GeoTarget::getCountriesList( $userLanguageCode );
		$locationElements = "\n";
		foreach ( $countries as $countryCode => $country ) {

			$regions = '';
			if ( $country->getRegions() ) {
				foreach ( $country->getRegions() as $regionCode => $name ) {
					$uniqueRegionCode = GeoTarget::makeUniqueRegionCode(
						$countryCode, $regionCode
					);
					$isSelected = in_array( $uniqueRegionCode, $selectedRegions );
					$data = [
						'type' => 'region',
						'code' => $regionCode,
						'opened' => $isSelected,
						'selected' => $isSelected
					];
					$regions .= Html::element(
						'li',
						[
							'id' => $uniqueRegionCode,
							'data-jstree' => json_encode( $data )
						],
						$this->msg(
							'centralnotice-location-name-and-code',
							$name,
							$regionCode
						)->text()
					);
				}
			}

			$isSelected = in_array( $countryCode, $selectedCountries );
			$data = [
				'type' => 'country',
				'code' => $countryCode,
				'opened' => $isSelected,
				'selected' => $isSelected
			];

			$countryNameAndCode = $this->msg(
				'centralnotice-location-name-and-code',
				$country->getName(),
				$countryCode
			)->escaped();

			$locationElements .= Html::rawElement(
				'li',
				[
					'data-jstree' => json_encode( $data ),
					'id' => $countryCode
				],
				$countryNameAndCode . ( $regions ? Html::rawElement( 'ul', [], $regions ) : '' )
			);
		}

		$properties = [
			'id'       => 'geo_locations',
			'class'    => 'cn-tree'
		];

		if ( !$this->editable ) {
			$properties['disabled'] = 'disabled';
		}

		$search = Html::rawElement(
			'input',
			[
				'type' => 'text',
				'class' => 'cn-tree-search'
			],
			''
		);
		$searchClear = Html::element(
			'button',
			[
				'class' => 'cn-tree-clear'
			],
			$this->msg( 'centralnotice-location-filter-clear' )->text()
		);
		$searchLabelText = $this->msg( 'centralnotice-location-filter' )->escaped();
		$searchLabel = Html::rawElement(
			'label',
			[
				'class' => 'cn-tree-search-label'
			],
			$searchLabelText . $search . $searchClear
		);

		$statusText = Html::rawElement( 'div', [ 'class' => 'cn-tree-status' ], '' );

		$tree = Html::rawElement(
			'div',
			$properties,
			Html::rawElement(
				'ul',
				[],
				$locationElements
			)
		);

		$hiddenInputs = Xml::input(
			'geo_countries',
			false,
			implode( ',', $selectedCountries ),
			[ 'type' => 'hidden', 'id' => 'geo_countries_value' ]
		);

		$hiddenInputs .= Xml::input(
			'geo_regions',
			false,
			implode( ',', $selectedRegions ),
			[ 'type' => 'hidden', 'id' => 'geo_regions_value' ]
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'cn-tree-wrapper' ],
			$searchLabel . $tree . $statusText . $hiddenInputs
		);
	}

	/**
	 * Sanitizes template search terms by removing non alpha and ensuring space delimiting.
	 *
	 * @param string $terms Search terms to sanitize
	 *
	 * @return string Space delimited string
	 */
	public function sanitizeSearchTerms( $terms ) {
		preg_match_all( '/([\w-]+)\S*/s', $terms, $matches );
		return implode( ' ', $matches[1] );
	}

	/**
	 * Truncate the summary field in a linguistically appropriate way.
	 * @param string|null $summary
	 * @return string
	 */
	public static function truncateSummaryField( $summary ) {
		return MediaWikiServices::getInstance()->getContentLanguage()
			->truncateForDatabase( $summary ?? '', 255 );
	}

	/**
	 * Provides names of sub-pages of the CentralNotice admin interface.
	 *
	 * @inheritDoc
	 */
	public function getAssociatedNavigationLinks(): array {
		// Keys of (default value of $wgNoticeTabifyPages) are the names of the special
		// pages of the admin interface without the initial 'Special:'.
		// TODO Make $wgNoticeTabifyPages a constant rather than a config variable.
		return array_map( static function ( $name ) {
			return 'Special:' . $name;
		}, array_keys( $this->getConfig()->get( 'NoticeTabifyPages' ) ) );
	}

	/**
	 * Return the localized text used for subpages of the CN admin interface (on skins
	 * that support navigation links).
	 *
	 * @inheritDoc
	 */
	public function getShortDescription( string $path = '' ): string {
		$noticeTabifyPages = $this->getConfig()->get( 'NoticeTabifyPages' );
		return $this->msg( $noticeTabifyPages[ $path ][ 'message' ] )->parse();
	}

	/**
	 * Loads a CentralNotice variable from session data.
	 *
	 * @param string $variable Name of the variable
	 * @param mixed|null $default Default value of the variable
	 *
	 * @return mixed Stored variable or default
	 */
	public function getCNSessionVar( $variable, $default = null ) {
		$val = $this->getRequest()->getSessionData( "centralnotice-$variable" );
		if ( $val === null ) {
			$val = $default;
		}

		return $val;
	}

	/**
	 * Sets a CentralNotice session variable. Note that this will fail silently if a
	 * session does not exist for the user.
	 *
	 * @param string $variable Name of the variable
	 * @param mixed $value Value for the variable
	 */
	public function setCNSessionVar( $variable, $value ) {
		$this->getRequest()->setSessionData( "centralnotice-{$variable}", $value );
	}

	/**
	 * @param string[] $projects
	 * @return string
	 */
	public function listProjects( $projects ) {
		return $this->makeShortList( $this->getConfig()->get( 'NoticeProjects' ), $projects );
	}

	/**
	 * @param string[] $countries
	 * @param string[] $regions
	 * @return string
	 */
	public function listCountriesRegions( array $countries, array $regions ) {
		$allCountries = array_keys( GeoTarget::getCountriesList() );
		$list = $this->makeShortList( $allCountries, $countries );
		$regionsByCountry = [];
		foreach ( $regions as $region ) {
			$countryCode = substr( $region, 0, 2 );
			$regionCode = substr( $region, 3 );
			$regionsByCountry[$countryCode][] = $regionCode;
		}
		if ( $list !== '' && count( $regionsByCountry ) > 0 ) {
			$list .= '; ';
		}
		$regionsByCountryList = [];
		foreach ( $regionsByCountry as $countryCode => $regions ) {
			$all = array_keys( GeoTarget::getRegionsList( $countryCode ) );
			$regionList = $this->makeShortList( $all, $regions );
			$regionsByCountryList[] = "$countryCode: ($regionList)";
		}
		$list .= $this->getContext()->getLanguage()->listToText( $regionsByCountryList );

		return $list;
	}

	/**
	 * @param string[] $languages
	 * @return string
	 */
	public function listLanguages( $languages ) {
		$all = array_keys( MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageNames( 'en' ) );
		return $this->makeShortList( $all, $languages );
	}

	/**
	 * @param string[] $all
	 * @param string[] $list
	 * @return string
	 */
	private function makeShortList( $all, $list ) {
		// TODO ellipsis and js/css expansion
		if ( count( $list ) == count( $all ) ) {
			return $this->getContext()->msg( 'centralnotice-all' )->text();
		}
		if ( count( $list ) > self::LIST_COMPLEMENT_THRESHOLD * count( $all ) ) {
			$inverse = array_values( array_diff( $all, $list ) );
			$txt = $this->getContext()->getLanguage()->listToText( $inverse );
			return $this->getContext()->msg( 'centralnotice-all-except', $txt )->text();
		}
		return $this->getContext()->getLanguage()->listToText( array_values( $list ) );
	}

	/**
	 * Convert comma separated list to array
	 * @param string $list
	 * @return array
	 */
	private function listToArray( $list ) {
		if ( $list ) {
			$array = explode( ',', $list );
		} else {
			$array = [];
		}
		return $array;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wiki';
	}

	/** @inheritDoc */
	public function outputHeader( $summaryMsg = '' ) {
		// Allow users to add a custom nav bar (T138284)
		$navBar = $this->msg( 'centralnotice-navbar' )->inContentLanguage();
		if ( !$navBar->isDisabled() ) {
			$this->getOutput()->addHTML( $navBar->parseAsBlock() );
		}
		return parent::outputHeader( $summaryMsg );
	}
}
