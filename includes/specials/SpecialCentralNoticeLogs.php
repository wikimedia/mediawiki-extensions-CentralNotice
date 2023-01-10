<?php

class SpecialCentralNoticeLogs extends CentralNotice {
	/** @var string */
	public $logType = 'campaignsettings';

	public function __construct() {
		// Register special page
		SpecialPage::__construct( "CentralNoticeLogs" );
	}

	public function isListed() {
		return false;
	}

	/**
	 * Handle different types of page requests
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		global $wgExtensionAssetsPath;

		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->logType = $request->getText( 'log', 'campaignsettings' );

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( 'ext.centralNotice.adminUi' );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Allow users to add a custom nav bar (T138284)
		$navBar = $this->msg( 'centralnotice-navbar' )->inContentLanguage();
		if ( !$navBar->isDisabled() ) {
			$out->addHTML( $navBar->parseAsBlock() );
		}
		// Show summary
		$out->addWikiMsg( 'centralnotice-summary' );

		// Begin Banners tab content
		$out->addHTML( Xml::openElement( 'div', [ 'id' => 'preferences' ] ) );

		$htmlOut = '';

		// Begin log selection fieldset
		$htmlOut .= Xml::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		$title = SpecialPage::getTitleFor( 'CentralNoticeLogs' );
		$actionUrl = $title->getLocalURL();
		$htmlOut .= Xml::openElement( 'form', [ 'method' => 'get', 'action' => $actionUrl ] );
		$htmlOut .= Xml::element( 'h2', null, $this->msg( 'centralnotice-view-logs' )->text() );
		$htmlOut .= Xml::openElement( 'div', [ 'id' => 'cn-log-switcher' ] );
		$title = SpecialPage::getTitleFor( 'CentralNoticeLogs' );
		$fullUrl = wfExpandUrl( $title->getFullURL(), PROTO_CURRENT );

		// Build the radio buttons for switching the log type
		$htmlOut .= $this->getLogSwitcher( 'campaignsettings', 'campaignSettings',
			'centralnotice-campaign-settings', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannersettings', 'bannerSettings',
			'centralnotice-banner-settings', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannercontent', 'bannerContent',
			'centralnotice-banner-content', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannermessages', 'bannerMessages',
			'centralnotice-banner-messages', $fullUrl );

		$htmlOut .= Xml::closeElement( 'div' );

		if ( $this->logType == 'campaignsettings' ) {
			$reset = $request->getVal( 'centralnoticelogreset' );
			$campaign = $request->getVal( 'campaign' );
			$user = $request->getVal( 'user' );
			$start = $this->getDateValue( 'start' );
			$end = $this->getDateValue( 'end' );

			$htmlOut .= Xml::openElement( 'div', [ 'id' => 'cn-log-filters-container' ] );

			$collapsedImg = $this->getContext()->getLanguage()->isRTL() ?
				'/CentralNotice/resources/images/collapsed-rtl.png' :
				'/CentralNotice/resources/images/collapsed-ltr.png';

			if ( $campaign || $user || $start || $end ) { // filters on
				$htmlOut .= '<a href="javascript:toggleFilterDisplay()">' .
					'<img src="' . $wgExtensionAssetsPath . $collapsedImg . '" ' .
					'id="cn-collapsed-filter-arrow" ' .
					'style="display:none;position:relative;top:-2px;"/>' .
					'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
					'id="cn-uncollapsed-filter-arrow" ' .
					'style="display:inline-block;position:relative;top:-2px;"/>' .
					'</a>';
				$htmlOut .= Xml::tags( 'span', [ 'style' => 'margin-left: 0.3em;' ],
					$this->msg( 'centralnotice-filters' )->escaped() );
				$htmlOut .= Xml::openElement( 'div', [ 'id' => 'cn-log-filters' ] );
			} else { // filters off
				$htmlOut .= '<a href="javascript:toggleFilterDisplay()">' .
					'<img src="' . $wgExtensionAssetsPath . $collapsedImg . '" ' .
					'id="cn-collapsed-filter-arrow" ' .
					'style="display:inline-block;position:relative;top:-2px;"/>' .
					'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
					'id="cn-uncollapsed-filter-arrow" ' .
					'style="display:none;position:relative;top:-2px;"/>' .
					'</a>';
				$htmlOut .= Xml::tags( 'span', [ 'style' => 'margin-left: 0.3em;' ],
					$this->msg( 'centralnotice-filters' )->escaped() );
				$htmlOut .= Xml::openElement( 'div',
					[ 'id' => 'cn-log-filters', 'style' => 'display:none;' ] );
			}

			$htmlOut .= Xml::openElement( 'table' );
			$htmlOut .= Xml::openElement( 'tr' );

			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-start-date' )->text(), 'month',
				[ 'class' => 'cn-log-filter-label' ] );
			$htmlOut .= Xml::closeElement( 'td' );
			$htmlOut .= Xml::openElement( 'td' );
			if ( $reset ) {
				$htmlOut .= $this->dateSelector( 'start', true );
			} else {
				$htmlOut .= $this->dateSelector( 'start', true, $start );
			}
			$htmlOut .= Xml::closeElement( 'td' );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr' );

			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-end-date' )->text(), 'month',
				[ 'class' => 'cn-log-filter-label' ] );
			$htmlOut .= Xml::closeElement( 'td' );
			$htmlOut .= Xml::openElement( 'td' );
			if ( $reset ) {
				$htmlOut .= $this->dateSelector( 'end', true );
			} else {
				$htmlOut .= $this->dateSelector( 'end', true, $end );
			}
			$htmlOut .= Xml::closeElement( 'td' );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr' );

			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-notice' )->text(), 'campaign',
				[ 'class' => 'cn-log-filter-label' ] );
			$htmlOut .= Xml::closeElement( 'td' );
			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::input( 'campaign', 25, ( $reset || $campaign === null ? '' : $campaign ) );
			$htmlOut .= Xml::closeElement( 'span' );
			$htmlOut .= Xml::closeElement( 'td' );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr' );

			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::label(
				$this->msg( 'centralnotice-user' )->text(),
				'user',
				[ 'class' => 'cn-log-filter-label' ]
			);
			$htmlOut .= Xml::closeElement( 'td' );
			$htmlOut .= Xml::openElement( 'td' );
			$htmlOut .= Xml::input( 'user', 25, ( $reset || $user === null ? '' : $user ) );
			$htmlOut .= Xml::closeElement( 'span' );
			$htmlOut .= Xml::closeElement( 'td' );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::openElement( 'tr' );

			$htmlOut .= Xml::openElement( 'td', [ 'colspan' => 2 ] );
			$htmlOut .= Xml::submitButton( $this->msg( 'centralnotice-apply-filters' )->text(),
				[
					'id' => 'centralnoticesubmit',
					'name' => 'centralnoticesubmit',
					'class' => 'cn-filter-buttons',
				]
			);
			$link = $title->getLinkURL();
			$htmlOut .= Xml::submitButton( $this->msg( 'centralnotice-clear-filters' )->text(),
				[
					'id' => 'centralnoticelogreset',
					'name' => 'centralnoticelogreset',
					'class' => 'cn-filter-buttons',
					'onclick' => "location.href = " . Xml::encodeJsVar( $link ) . "; return false;",
				]
			);
			$htmlOut .= Xml::closeElement( 'td' );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::closeElement( 'div' );
			// @phan-suppress-next-line PhanPluginDuplicateAdjacentStatement
			$htmlOut .= Xml::closeElement( 'div' );
		}

		$htmlOut .= Xml::closeElement( 'form' );

		// End log selection fieldset
		// $htmlOut .= Xml::closeElement( 'fieldset' );

		$out->addHTML( $htmlOut );

		$this->showLog( $this->logType );

		// End Banners tab content
		$out->addHTML( Xml::closeElement( 'div' ) );
	}

	/**
	 * Render a field suitable for jquery.ui datepicker
	 * @param string $prefix
	 * @param bool $editable
	 * @param string|null $date
	 * @return string HTML
	 */
	protected function dateSelector( $prefix, $editable = true, $date = '' ) {
		$out = Html::element( 'input',
			[
				'id' => "{$prefix}Date",
				'name' => "{$prefix}Date",
				'type' => 'text',
				'class' => 'centralnotice-datepicker',
			]
		);
		$out .= Html::element( 'input',
			[
				'id' => "{$prefix}Date_timestamp",
				'name' => "{$prefix}Date_timestamp",
				'type' => 'hidden',
				'value' => (string)$date,
			]
		);
		return $out;
	}

	/**
	 * Show a log of changes.
	 * @param string $logType which type of log to show
	 */
	private function showLog( $logType ) {
		switch ( $logType ) {
			case 'bannersettings':
				$pager = new CentralNoticeBannerLogPager( $this );
				break;
			case 'bannercontent':
			case 'bannermessages':
				$pager = new CentralNoticePageLogPager( $this, $logType );
				break;
			default:
				$pager = new CentralNoticeCampaignLogPager( $this );
		}

		$htmlOut = '';

		// Begin log fieldset
		// $htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// Show paginated list of log entries
		$htmlOut .= Xml::tags( 'div',
			[ 'class' => 'cn-pager' ],
			$pager->getNavigationBar() );
		$htmlOut .= $pager->getBody();
		$htmlOut .= Xml::tags( 'div',
			[ 'class' => 'cn-pager' ],
			$pager->getNavigationBar() );

		// End log fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Returns the jquery.ui datepicker value, or null if the field is blank.
	 * @param string $name
	 * @return null|string
	 */
	public function getDateValue( $name ) {
		$manual_entry = $this->getRequest()->getVal( "{$name}Date" );
		if ( !$manual_entry ) {
			return null;
		}

		return $this->getRequest()->getVal( "{$name}Date_timestamp" );
	}

	/**
	 * Build a radio button that switches the log type when you click it
	 * @param string $type
	 * @param string $id
	 * @param string $message
	 * @param string $fullUrl
	 * @return string HTML
	 */
	private function getLogSwitcher( $type, $id, $message, $fullUrl ) {
		$fullUrlEnc = Xml::encodeJsVar( $fullUrl );
		$typeEnc = Xml::encodeJsVar( $type );
		$htmlOut = '';
		$htmlOut .= Xml::radio(
			'log_type',
			$id,
			$this->logType == $type,
			[ 'onclick' => "switchLogs( " . $fullUrlEnc . ", " . $typeEnc . " )" ]
		);
		$htmlOut .= Xml::label( $this->msg( $message )->text(), $id );
		return $htmlOut;
	}
}
