<?php

class CentralNoticeBannerLogPager extends CentralNoticeCampaignLogPager {
	var $special;

	function __construct( $special ) {
		$this->special = $special;
		parent::__construct($special);
	}

	/**
	 * Sort the log list by timestamp
	 */
	function getIndexField() {
		return 'tmplog_timestamp';
	}

	/**
	 * Pull log entries from the database
	 */
	function getQueryInfo() {
		return array(
			'tables' => array( 'template_log' => 'cn_template_log' ),
			'fields' => '*',
		);
	}

	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 */
	function formatRow( $row ) {
		global $wgExtensionAssetsPath;
		$lang = $this->getLanguage();

		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->tmplog_user_id );
		// Create the user page link
		$userLink = Linker::linkKnown(
			$loggedUser->getUserPage(),
			$loggedUser->getName()
		);
		$userTalkLink = Linker::linkKnown(
			$loggedUser->getTalkPage(),
			wfMessage( 'centralnotice-talk-link' )->escaped()
		);

		// Create the banner link
		$bannerLink = Linker::linkKnown(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$row->tmplog_template_name}" ),
			htmlspecialchars( $row->tmplog_template_name )
		);

		// Begin log entry primary row
		$htmlOut = Xml::openElement( 'tr' );

		$htmlOut .= Xml::openElement( 'td', array( 'valign' => 'top' ) );
		if ( $row->tmplog_action !== 'removed' ) {
			$htmlOut .= '<a href="javascript:toggleLogDisplay(\''.$row->tmplog_id.'\')">'.
				'<img src="'.$wgExtensionAssetsPath.'/CentralNotice/collapsed.png" id="cn-collapsed-'.$row->tmplog_id.'" style="display:block;vertical-align:baseline;"/>'.
				'<img src="'.$wgExtensionAssetsPath.'/CentralNotice/uncollapsed.png" id="cn-uncollapsed-'.$row->tmplog_id.'" style="display:none;vertical-align:baseline;"/>'.
				'</a>';
		}
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$lang->date( $row->tmplog_timestamp ) . ' ' . $lang->time( $row->tmplog_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$this->msg( 'centralnotice-user-links', $userLink, $userTalkLink )->text()
		);
		// Give grep a chance to find the usages:
		// centralnotice-action-created, centralnotice-action-modified,
		// centralnotice-action-removed
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$this->msg( 'centralnotice-action-'.$row->tmplog_action )->text()
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$bannerLink
		);
		$htmlOut .= Xml::tags( 'td', array(),
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Xml::closeElement( 'tr' );

		if ( $row->tmplog_action !== 'removed' ) {
			// Begin log entry secondary row
			$htmlOut .= Xml::openElement( 'tr', array( 'id' => 'cn-log-details-'.$row->tmplog_id, 'style' => 'display:none;' ) );

			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				'&nbsp;' // force a table cell in older browsers
			);
			$htmlOut .= Xml::openElement( 'td', array( 'valign' => 'top', 'colspan' => '5' ) );
			if ( $row->tmplog_action == 'created' ) {
				$htmlOut .= $this->showInitialSettings( $row );
			} elseif ( $row->tmplog_action == 'modified' ) {
				$htmlOut .= $this->showChanges( $row );
			}
			$htmlOut .= Xml::closeElement( 'td' );

			// End log entry primary row
			$htmlOut .= Xml::closeElement( 'tr' );
		}

		return $htmlOut;
	}

	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'id' => 'cn-campaign-logs', 'cellpadding' => 3 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', array( 'style' => 'width: 20px;' ) );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 130px;' ),
			$this->msg( 'centralnotice-timestamp' )->text()
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 160px;' ),
			$this->msg( 'centralnotice-user' )->text()
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 100px;' ),
			$this->msg( 'centralnotice-action' )->text()
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 160px;' ),
			$this->msg( 'centralnotice-banner' )->text()
		);
		$htmlOut .= Xml::tags( 'td', array(),
			'&nbsp;'
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 * @return string
	 */
	function getEndBody() {
		return Xml::closeElement( 'table' );
	}

	function showInitialSettings( $row ) {
		$details = '';
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-anon' )->text(),
			($row->tmplog_end_anon ? 'on' : 'off')
		)->text() . "<br/>";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-account' )->text(),
			($row->tmplog_end_account ? 'on' : 'off')
		)->text() . "<br/>";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-category' )->text(),
			$row->tmplog_end_category
		)->text() . "<br/>";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-autolink' )->text(),
			($row->tmplog_end_autolink ? 'on' : 'off')
		)->text() . "<br/>";
		if ( $row->tmplog_end_landingpages ) {
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-landingpages' )->text(),
				$row->tmplog_end_landingpages
			)->text() . "<br/>";
		}
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-devices' )->text(),
			$row->tmplog_end_devices
		)->text() . "<br/>";
		return $details;
	}

	function showChanges( $row ) {
		$details = $this->testBooleanChange( 'anon', $row );
		$details .= $this->testBooleanChange( 'account', $row );
		$details .= $this->testTextChange( 'category', $row );
		$details .= $this->testBooleanChange( 'autolink', $row );
		$details .= $this->testTextChange( 'landingpages', $row );
		$details .= $this->testTextChange( 'controller_mixin', $row );
		$details .= $this->testTextChange( 'prioritylangs', $row );
		$details .= $this->testTextChange( 'devices', $row );
		if ( $row->tmplog_content_change ) {
			// Show changes to banner content
			$details .= $this->msg (
				'centralnotice-log-label',
				$this->msg( 'centralnotice-banner-content' )->text(),
				$this->msg( 'centralnotice-banner-content-changed' )->text()
			)->text() . "<br/>";
		}
		return $details;
	}

	private function testBooleanChange( $param, $row ) {
		$result = '';
		$beginField = 'tmplog_begin_'.$param;
		$endField = 'tmplog_end_'.$param;
		if ( $row->$beginField !== $row->$endField ) {
			// Give grep a chance to find the usages:
			// centralnotice-anon, centralnotice-account, centralnotice-fundraising, centralnotice-autolink
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					( $row->$beginField ? $this->msg( 'centralnotice-on' )->text() : $this->msg( 'centralnotice-off' )->text() ),
					( $row->$endField ? $this->msg( 'centralnotice-on' )->text() : $this->msg( 'centralnotice-off' )->text() )
				)->text()
			)->text() . "<br/>";
		}
		return $result;
	}

	private function testTextChange( $param, $row ) {
		$result = '';
		$beginField = 'tmplog_begin_'.$param;
		$endField = 'tmplog_end_'.$param;
		if ( $row->$beginField !== $row->$endField ) {
			// Give grep a chance to find the usages: centralnotice-landingpages, centralnotice-prioritylangs
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-'.$param )->text(),
				$this->msg(
					'centralnotice-changed',
					$row->$beginField,
					$row->$endField
				)->text()
			)->text() . "<br/>";
		}
		return $result;
	}
}
