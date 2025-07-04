<?php

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\Xml\Xml;

class SpecialCentralNoticeLogs extends CentralNotice {
	/** @var string */
	public $logType = 'campaignsettings';

	private UrlUtils $urlUtils;

	public function __construct(
		UrlUtils $urlUtils
	) {
		// Register special page
		SpecialPage::__construct( "CentralNoticeLogs" );
		$this->urlUtils = $urlUtils;
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
		$out->addHTML( Html::openElement( 'div', [ 'id' => 'preferences' ] ) );

		$htmlOut = '';

		// Begin log selection fieldset
		$htmlOut .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		$title = SpecialPage::getTitleFor( 'CentralNoticeLogs' );
		$actionUrl = $title->getLocalURL();
		$htmlOut .= Html::openElement( 'form', [ 'method' => 'get', 'action' => $actionUrl ] );
		$htmlOut .= Html::element( 'h2', [], $this->msg( 'centralnotice-view-logs' )->text() );
		$htmlOut .= Html::openElement( 'div', [ 'id' => 'cn-log-switcher' ] );
		$title = SpecialPage::getTitleFor( 'CentralNoticeLogs' );

		$fullUrl = $this->urlUtils->expand( $title->getFullURL(), PROTO_CURRENT ) ?? '';

		// Build the radio buttons for switching the log type
		$htmlOut .= $this->getLogSwitcher( 'campaignsettings', 'campaignSettings',
			'centralnotice-campaign-settings', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannersettings', 'bannerSettings',
			'centralnotice-banner-settings', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannercontent', 'bannerContent',
			'centralnotice-banner-content', $fullUrl );
		$htmlOut .= $this->getLogSwitcher( 'bannermessages', 'bannerMessages',
			'centralnotice-banner-messages', $fullUrl );

		$htmlOut .= Html::closeElement( 'div' );

		if ( $this->logType == 'campaignsettings' ) {
			$reset = $request->getVal( 'centralnoticelogreset' );
			$campaign = $request->getVal( 'campaign' );
			$user = $request->getVal( 'user' );
			$start = $this->getDateValue( 'start' );
			$end = $this->getDateValue( 'end' );

			$htmlOut .= Html::openElement( 'div', [ 'id' => 'cn-log-filters-container' ] );

			$collapsedImg = $this->getContext()->getLanguage()->isRTL() ?
				'/CentralNotice/resources/images/collapsed-rtl.png' :
				'/CentralNotice/resources/images/collapsed-ltr.png';

			if ( $campaign || $user || $start || $end ) {
				// filters on
				$htmlOut .= '<a href="javascript:toggleFilterDisplay()">' .
					'<img src="' . $wgExtensionAssetsPath . $collapsedImg . '" ' .
					'id="cn-collapsed-filter-arrow" ' .
					'style="display:none;position:relative;top:-2px;"/>' .
					'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
					'id="cn-uncollapsed-filter-arrow" ' .
					'style="display:inline-block;position:relative;top:-2px;"/>' .
					'</a>';
				$htmlOut .= Html::element( 'span', [ 'style' => 'margin-left: 0.3em;' ],
					$this->msg( 'centralnotice-filters' )->text() );
				$htmlOut .= Html::openElement( 'div', [ 'id' => 'cn-log-filters' ] );
			} else {
				// filters off
				$htmlOut .= '<a href="javascript:toggleFilterDisplay()">' .
					'<img src="' . $wgExtensionAssetsPath . $collapsedImg . '" ' .
					'id="cn-collapsed-filter-arrow" ' .
					'style="display:inline-block;position:relative;top:-2px;"/>' .
					'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
					'id="cn-uncollapsed-filter-arrow" ' .
					'style="display:none;position:relative;top:-2px;"/>' .
					'</a>';
				$htmlOut .= Html::element( 'span', [ 'style' => 'margin-left: 0.3em;' ],
					$this->msg( 'centralnotice-filters' )->text() );
				$htmlOut .= Html::openElement( 'div',
					[ 'id' => 'cn-log-filters', 'style' => 'display:none;' ] );
			}

			$htmlOut .= Html::openElement( 'table' );

			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-start-date' )->text(), 'month',
						[ 'class' => 'cn-log-filter-label' ] )
				) .
				Html::rawElement( 'td', [],
					$this->dateSelector( 'start', true, $reset ? '' : $start )
				)
			);

			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-end-date' )->text(), 'month',
						[ 'class' => 'cn-log-filter-label' ] )
				) .
				Html::rawElement( 'td', [],
					$this->dateSelector( 'end', true, $reset ? '' : $end )
				)
			);

			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-notice' )->text(), 'campaign',
						[ 'class' => 'cn-log-filter-label' ] )
				) .
				Html::rawElement( 'td', [],
					Html::input(
						'campaign',
						( $reset || $campaign === null ? '' : $campaign ),
						'text',
						[ 'size' => 25 ]
					)
				)
			);

			$htmlOut .= Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label( $this->msg( 'centralnotice-user' )->text(), 'user',
						[ 'class' => 'cn-log-filter-label' ] )
				) .
				Html::rawElement( 'td', [],
					Html::input( 'user', ( $reset || $user === null ? '' : $user ), 'text', [ 'size' => 25 ] )
				)
			);

			$htmlOut .= Html::openElement( 'tr' );

			$htmlOut .= Html::openElement( 'td', [ 'colspan' => 2 ] );
			$htmlOut .= Html::submitButton( $this->msg( 'centralnotice-apply-filters' )->text(),
				[
					'id' => 'centralnoticesubmit',
					'name' => 'centralnoticesubmit',
					'class' => 'cn-filter-buttons',
				]
			);
			$link = $title->getLinkURL();
			$htmlOut .= Html::submitButton( $this->msg( 'centralnotice-clear-filters' )->text(),
				[
					'id' => 'centralnoticelogreset',
					'name' => 'centralnoticelogreset',
					'class' => 'cn-filter-buttons',
					'onclick' => "location.href = " . Html::encodeJsVar( $link ) . "; return false;",
				]
			);
			$htmlOut .= Html::closeElement( 'td' );

			$htmlOut .= Html::closeElement( 'tr' );
			$htmlOut .= Html::closeElement( 'table' );
			$htmlOut .= Html::closeElement( 'div' );
			// @phan-suppress-next-line PhanPluginDuplicateAdjacentStatement
			$htmlOut .= Html::closeElement( 'div' );
		}

		$htmlOut .= Html::closeElement( 'form' );

		// End log selection fieldset
		// $htmlOut .= Html::closeElement( 'fieldset' );

		$out->addHTML( $htmlOut );

		$this->showLog( $this->logType );

		// End Banners tab content
		$out->addHTML( Html::closeElement( 'div' ) );
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
		// $htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// Show paginated list of log entries
		$htmlOut .= Html::rawElement( 'div',
			[ 'class' => 'cn-pager' ],
			$pager->getNavigationBar() );
		$htmlOut .= $pager->getBody();
		$htmlOut .= Html::rawElement( 'div',
			[ 'class' => 'cn-pager' ],
			$pager->getNavigationBar() );

		// End log fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

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
		$fullUrlEnc = Html::encodeJsVar( $fullUrl );
		$typeEnc = Html::encodeJsVar( $type );
		$htmlOut = '';
		$htmlOut .= Html::radio(
			'log_type',
			$this->logType == $type,
			[ 'value' => $id, 'onclick' => "switchLogs( " . $fullUrlEnc . ", " . $typeEnc . " )" ]
		);
		$htmlOut .= Xml::label( $this->msg( $message )->text(), $id );
		return $htmlOut;
	}
}
