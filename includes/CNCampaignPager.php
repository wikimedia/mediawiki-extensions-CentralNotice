<?php

/**
 * A pager for viewing lists of CentralNotice campaigns. Optionally allows
 * modification of some campaign properties. It is expected that this will only
 * be included on special pages that are subclasses of CentralNotice.
 *
 * This class is a reorganization of code formerly in
 * CentralNotice::listNotices().
 */
class CNCampaignPager extends TablePager {

	// For now, we want to make this display without paging on
	// meta.wikimedia.org, in line with the functionality that users currently
	// encounter.
	// This should be enuf--Meta has less than 500 campaigns.
	const DEFAULT_LIMIT = 5000;

	protected $onSpecialCN;
	protected $editable;
	protected $assignedBannerId;
	protected $fieldNames = null;

	/**
	 * @param CentralNotice $onSpecialCN The CentralNotice special page we're on
	 * @param string $editable Whether or not to make the list editable
	 * @param string $assignedBannerId Set this to show only the campaigns
	 *   associated with this banner id.
	 */
	public function __construct( CentralNotice $onSpecialCN,
		$editable = false, $assignedBannerId = null ) {

		$this->onSpecialCN = $onSpecialCN;
		$this->assignedBannerId = $assignedBannerId;
		$this->editable = $editable;

		parent::__construct( $onSpecialCN->getContext() );

		$req = $onSpecialCN->getRequest();

		// The 'limit' request param is used by the pager superclass.
		// If it's absent, we'll set the limit to our own default.
		$this->setLimit(
			$req->getVal( 'limit', null ) ?:
			self::DEFAULT_LIMIT );

		// If the request doesn't an order by value, set descending order.
		// This makes our order-by-id compatible with the previous default
		// ordering in the UI.
		if ( !$req->getVal( 'sort', null ) ) {
			$this->mDefaultDirection = true;
		}
	}

	/**
	 * @see IndexPager::getQueryInfo()
	 */
	public function getQueryInfo() {
		$pagerQuery = array(
			'tables' => array(
				'notices' => 'cn_notices',
			),
			'fields' => array(
				'notices.not_id',
				'not_name',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_throttle',
				'not_geo',
				'not_locked',
				'not_archived',
				$this->getDatabase()->buildGroupConcatField(
					',',
					'cn_notice_countries',
					'nc_country',
					'nc_notice_id = notices.not_id'
				) . ' AS countries',
				$this->getDatabase()->buildGroupConcatField(
					',',
					'cn_notice_languages',
					'nl_language',
					'nl_notice_id = notices.not_id'
				) . ' AS languages',
				$this->getDatabase()->buildGroupConcatField(
					',',
					'cn_notice_projects',
					'np_project',
					'np_notice_id = notices.not_id'
				) . ' AS projects',
			),
			'conds' => array(),
		);

		if ( $this->assignedBannerId ) {
			// Query for only campaigns associated with a specific banner id.
			$pagerQuery['tables']['assignments'] = 'cn_assignments';
			$pagerQuery['conds'] = array(
				'notices.not_id = assignments.not_id',
				'assignments.tmp_id = ' . (int)$this->assignedBannerId
			);
		}

		return $pagerQuery;
	}

	public function doQuery() {
		// group_concat output is limited to 1024 characters by default, increase
		// the limit temporarily so the list of all languages can be rendered.
		$this->getDatabase()->query( 'SET SESSION group_concat_max_len = 10000' );

		parent::doQuery();
	}


	/**
	 * @see TablePager::getFieldNames()
	 */
	public function getFieldNames() {

		if ( !$this->fieldNames ) {
			$this->fieldNames = array(
				'not_name' => $this->msg( 'centralnotice-notice-name' )->text(),
				'projects' => $this->msg( 'centralnotice-projects' )->text(),
				'languages' => $this->msg( 'centralnotice-languages' )->text(),
				'countries' => $this->msg( 'centralnotice-countries' )->text(),
				'not_start' => $this->msg( 'centralnotice-start-timestamp' )->text(),
				'not_end' => $this->msg( 'centralnotice-end-timestamp' )->text(),
				'not_enabled' => $this->msg( 'centralnotice-enabled' )->text(),
				'not_preferred' => $this->msg( 'centralnotice-preferred' )->text(),
				'not_throttle' => $this->msg( 'centralnotice-throttle' )->text(),
				'not_locked' => $this->msg( 'centralnotice-locked' )->text(),
				'not_archived' => $this->msg( 'centralnotice-archive-campaign' )->text()
			);
		}

		return $this->fieldNames;
	}

	/**
	 * @see TablePager::getStartBody()
	 */
	public function getStartBody() {

		$htmlOut = '';

		$htmlOut .= Xml::openElement(
			'fieldset',
			array(
				'class' => 'prefsection',
				'id' => 'cn-campaign-pager',
				'data-editable' => ( $this->editable ? 1 : 0 )
			)
		);

		// Filters
		$htmlOut .= Xml::openElement( 'div', array( 'class' => 'cn-formsection-emphasis' ) );
		$htmlOut .= Xml::checkLabel(
			$this->msg( 'centralnotice-archive-show' )->text(),
			'centralnotice-showarchived',
			'centralnotice-showarchived',
			false
		);
		$htmlOut .= Xml::closeElement( 'div' );

		return $htmlOut . parent::getStartBody();
	}

	public function formatValue( $fieldName, $value ) {

		// These are used in a few cases below.
		$rowIsEnabled = ( $this->mCurrentRow->not_enabled == '1' );
		$rowIsLocked = ( $this->mCurrentRow->not_locked == '1' );
		$rowIsArchived = ( $this->mCurrentRow->not_archived == '1' );
		$name = $this->mCurrentRow->not_name;
		$readonly = array( 'disabled' => 'disabled' );

		switch ( $fieldName ) {
			case 'not_name':
				return Linker::link(
					SpecialPage::getTitleFor( 'CentralNotice' ),
					htmlspecialchars( $value ),
					array(),
					array(
						'subaction' => 'noticeDetail',
						'notice' => $value
					)
				);

			case 'projects':
				$p = explode( ',', $this->mCurrentRow->projects );
				return $this->onSpecialCN->listProjects( $p );

			case 'languages':
				$l = explode( ',', $this->mCurrentRow->languages );
				return $this->onSpecialCN->listLanguages( $l );

			case 'countries':
				if ( $this->mCurrentRow->not_geo ) {
					$c = explode( ',', $this->mCurrentRow->countries );
				} else {
					// FIXME: this is silly.
					$c = array_keys( GeoTarget::getCountriesList( 'en' ) );
				}

				return $this->onSpecialCN->listCountries( $c );

			case 'not_start':
			case 'not_end':
				return date( '<\b>Y-m-d</\b> H:i', wfTimestamp( TS_UNIX, $value ) );

			// Note: Names of controls and data attributes must coordinate with
			// ext.centralNotice.adminUi.campaignPager.js

			case 'not_enabled':
				return Xml::check(
					'enabled',
					$rowIsEnabled,
					array_replace(
						( !$this->editable || $rowIsLocked || $rowIsArchived )
						? $readonly : array(),
						array(
							'data-campaign-name' => $name,
							'data-initial-value' => $rowIsEnabled,
							'class' => 'noshiftselect mw-cn-input-check-sort'
						)
					)
				);

			case 'not_preferred':
				return $this->onSpecialCN->prioritySelector(
					$name,
					$this->editable && !$rowIsLocked && !$rowIsArchived,
					$value
				);

			case 'not_throttle':
				if ( $value < 100) {
					return $value . "%";
				} else {
					return '';
				}

			case 'not_locked':
				return Xml::check(
					'locked',
					$rowIsLocked,
					array_replace(
						// Note: Lockability should always be modifiable
						// regardless of whether the camapgin is archived.
						// Otherwise we create a dead-end state of locked and
						// archived.
						( !$this->editable )
						? $readonly : array(),
						array(
							'data-campaign-name' => $name,
							'data-initial-value' => $rowIsLocked,
							'class' => 'noshiftselect mw-cn-input-check-sort'
						)
					)
				);

			case 'not_archived':
				return Xml::check(
					'archived',
					$rowIsArchived,
					array_replace(
						( !$this->editable || $rowIsLocked || $rowIsEnabled )
						? $readonly : array(),
						array(
							'data-campaign-name' => $name,
							'data-initial-value' => $rowIsArchived,
							'class' => 'noshiftselect mw-cn-input-check-sort'
						)
					)
				);
		}
	}

	/**
	 * Set special CSS classes for active and archived campaigns.
	 *
	 * @see TablePager::getRowClass()
	 */
	public function getRowClass( $row ) {

		$enabled = ( $row->not_enabled == '1' );
		$archived = ( $row->not_archived == '1' );

		$now = wfTimestamp();
		$started = $now >= wfTimestamp( TS_UNIX, $row->not_start );
		$notEnded = $now <= wfTimestamp( TS_UNIX, $row->not_end );

		$cssClass = parent::getRowClass( $row );

		if ( $enabled && $started && $notEnded ) {
			$cssClass .= ' cn-active-campaign';
		}

		if ( $archived ) {
			$cssClass .= ' cn-archived-item';
		}

		return $cssClass;
	}

	/**
	 * @see TablePager::getCellAttrs()
	 */
	public function getCellAttrs( $field, $value ) {

		$attrs = parent::getCellAttrs( $field, $value );

		switch ( $field ) {
			case 'not_start':
			case 'not_end':
				// Set css class, or add to the class(es) set by parent
				$attrs['class'] =
					( isset( $attrs['class'] ) ? $attrs['class'] . ' ' : '' ) .
					'cn-date-column';
				break;

			case 'not_enabled':
			case 'not_preferred':
			case 'not_throttle':
			case 'not_locked':
			case 'not_archived':
				// These fields use the extra sort-value attribute for JS
				// sorting.
				$attrs['data-sort-value'] = $value;
		}

		return $attrs;
	}

	/**
	 * @see TablePager::getEndBody()
	 */
	public function getEndBody() {

		$htmlOut = '';

		if ( $this->editable ) {
			$htmlOut .=
				Xml::openElement( 'div',
				array( 'class' => 'cn-buttons cn-formsection-emphasis' ) );

			$htmlOut .= $this->onSpecialCN->makeSummaryField();

			$htmlOut .= Xml::input(
				'centralnoticesubmit',
				false,
				$this->msg( 'centralnotice-modify' )->text(),
				array(
					'type' => 'button',
					'id' => 'cn-campaign-pager-submit'
				)
			);

			$htmlOut .= Xml::closeElement( 'div' );
		}

		$htmlOut .= Xml::closeElement( 'fieldset' );

		return parent::getEndBody() . $htmlOut;
	}

	/**
	 * @see TablePager::isFieldSortable()
	 */
	public function isFieldSortable( $field ) {

		// If this is the only page shown, we'll sort via JS, which works on all
		// columns.
		if ( $this->isWithinLimit() ) {
			return false;
		}

		// Because of how paging works, it seems that only unique columns can be
		// ordered if there's more than one page of results.
		// TODO If paging is ever needed in the UI, it should be possible to
		// partially address this by using the id as a secondary field for
		// ordering and for the paging offset. Some fields still won't be
		// sortable via the DB because of how values are munged in the UI (for
		// example, "All" and "All except..." for languages and countries).
		// If needed, filters could be added for such fields, though.
		if ( $field === 'not_name' ) {
			return true;
		}

		return false;
	}

	/**
	 * @see TablePager::getDefaultSort()
	 */
	public function getDefaultSort() {
		return $this->assignedBannerId === null ?
			'not_id' : 'notices.not_id';
	}

	/**
	 * Returns true if this is the only page of results there is to show.
	 */
	protected function isWithinLimit() {
		return $this->mIsFirst && $this->mIsLast;
	}

	/**
	 * @see IndexPager::extractResultInfo()
	 */
	function extractResultInfo( $isFirst, $limit, ResultWrapper $res ) {

		parent::extractResultInfo( $isFirst, $limit, $res );

		// Disable editing if there's more than one page. (This is a legacy
		// requirement; it might work even with paging now.)
		if ( !$this->isWithinLimit() ) {
			$this->editable = false;
		}
	}

	/**
	 * @see TablePager::getTableClass()
	 */
	public function getTableClass() {
		$jsSortable = $this->isWithinLimit() ? ' sortable' : '';
		return parent::getTableClass() . ' wikitable' . $jsSortable;
	}
}
