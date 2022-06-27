<?php

class CentralNoticeBannerLogPager extends CentralNoticeCampaignLogPager {
	/** @var SpecialCentralNoticeLogs */
	public $special;

	public function __construct( SpecialCentralNoticeLogs $special ) {
		$this->special = $special;
		parent::__construct( $special );
	}

	/**
	 * Sort the log list by timestamp
	 * @return string
	 */
	public function getIndexField() {
		return 'tmplog_timestamp';
	}

	/**
	 * Pull log entries from the database
	 * @return array
	 */
	public function getQueryInfo() {
		return [
			'tables' => [ 'template_log' => 'cn_template_log' ],
			'fields' => '*',
		];
	}

	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 * @param stdClass $row
	 * @return string HTML
	 */
	public function formatRow( $row ) {
		global $wgExtensionAssetsPath;
		$lang = $this->getLanguage();

		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->tmplog_user_id );
		// Create the user page link
		$userLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getUserPage(),
			$loggedUser->getName()
		);
		$userTalkLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getTalkPage(),
			$this->msg( 'centralnotice-talk-link' )->text()
		);

		// Create the banner link
		$bannerLink = $this->special->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$row->tmplog_template_name}" ),
			$row->tmplog_template_name
		);

		// Begin log entry primary row
		$htmlOut = Xml::openElement( 'tr' );

		$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top' ] );
		if ( $row->tmplog_action !== 'removed' ) {
			$collapsedImg = $this->getLanguage()->isRtl() ?
				'collapsed-rtl.png' :
				'collapsed-ltr.png';

			$tmplogId = (int)$row->tmplog_id;
			$htmlOut .= '<a href="javascript:toggleLogDisplay(\'' . $tmplogId . '\')">' .
				'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/' . $collapsedImg . '" ' .
				'id="cn-collapsed-' . $tmplogId . '" ' .
				'style="display:block;vertical-align:baseline;"/>' .
				'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
				'id="cn-uncollapsed-' . $tmplogId . '" ' .
				'style="display:none;vertical-align:baseline;"/>' .
				'</a>';
		}
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$lang->date( $row->tmplog_timestamp ) . ' ' . $lang->time( $row->tmplog_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-user-links' )
				->rawParams( $userLink, $userTalkLink )
				->parse()
		);
		// Give grep a chance to find the usages:
		// centralnotice-action-created, centralnotice-action-modified,
		// centralnotice-action-removed
		$htmlOut .= Xml::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-action-' . $row->tmplog_action )->text()
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$bannerLink
		);

		// TODO temporary code for soft dependency on schema change
		$summary = property_exists( $row, 'tmplog_comment' ) ?
			htmlspecialchars( $row->tmplog_comment ) : '&nbsp;';

		$htmlOut .= Xml::tags( 'td',
			[ 'valign' => 'top', 'class' => 'primary-summary' ],
			$summary
		);

		$htmlOut .= Xml::tags( 'td', [],
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Xml::closeElement( 'tr' );

		if ( $row->tmplog_action !== 'removed' ) {
			// Begin log entry secondary row
			$htmlOut .= Xml::openElement( 'tr',
				[ 'id' => 'cn-log-details-' . $tmplogId, 'style' => 'display:none;' ] );
			// @phan-suppress-previous-line PhanPossiblyUndeclaredVariable

			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
				'&nbsp;' // force a table cell in older browsers
			);
			$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top', 'colspan' => '5' ] );
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

	public function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', [ 'id' => 'cn-campaign-logs', 'cellpadding' => 3 ] );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', [ 'style' => 'width: 20px;' ] );
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 130px;' ],
			$this->msg( 'centralnotice-timestamp' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-user' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 100px;' ],
			$this->msg( 'centralnotice-action' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-banner' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 250px;' ],
			$this->msg( 'centralnotice-change-summary-heading' )->text()
		);
		$htmlOut .= Xml::tags( 'td', [],
			'&nbsp;'
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 * @return string
	 */
	public function getEndBody() {
		return Xml::closeElement( 'table' );
	}

	public function showInitialSettings( $row ) {
		$details = '';
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-anon' )->text(),
			( $row->tmplog_end_anon ? 'on' : 'off' )
		)->parse() . "<br/>";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-account' )->text(),
			( $row->tmplog_end_account ? 'on' : 'off' )
		)->parse() . "<br/>";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-category' )->text(),
			wfEscapeWikiText( $row->tmplog_end_category )
		)->parse() . "<br/>";

		// Autolink/landing pages feature has been removed, but we might as
		// well show any info about it in the logs.
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-autolink' )->text(),
			( $row->tmplog_end_autolink ? 'on' : 'off' )
		)->parse() . "<br/>";
		if ( $row->tmplog_end_landingpages ) {
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-landingpages' )->text(),
				wfEscapeWikiText( $row->tmplog_end_landingpages )
			)->parse() . "<br/>";
		}
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-devices' )->text(),
			wfEscapeWikiText( $row->tmplog_end_devices )
		)->parse() . "<br/>";
		return $details;
	}

	public function showChanges( $newrow ) {
		$oldrow = false;
		if ( $newrow->tmplog_action === 'modified' ) {
			$db = CNDatabase::getDb();
			$tmplogId = (int)$newrow->tmplog_id;
			$oldrow = $db->selectRow(
				[ 'cn_template_log' => 'cn_template_log' ],
				'*',
				[ 'tmplog_template_id' => $newrow->tmplog_template_id,
					"tmplog_id < {$tmplogId}" ],
				__METHOD__,
				[ 'ORDER BY' => 'tmplog_id DESC', 'LIMIT' => 1 ]
			);
		}

		$details = $this->testBooleanBannerChange( 'anon', $newrow, $oldrow );
		$details .= $this->testBooleanBannerChange( 'account', $newrow, $oldrow );
		$details .= $this->testTextBannerChange( 'category', $newrow, $oldrow );
		$details .= $this->testBooleanBannerChange( 'autolink', $newrow, $oldrow );
		$details .= $this->testTextBannerChange( 'landingpages', $newrow, $oldrow );
		$details .= $this->testTextBannerChange( 'controller_mixin', $newrow, $oldrow );
		$details .= $this->testTextBannerChange( 'prioritylangs', $newrow, $oldrow );
		$details .= $this->testTextBannerChange( 'devices', $newrow, $oldrow );
		if ( $newrow->tmplog_content_change ) {
			// Show changes to banner content
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-banner-content' )->text(),
				$this->msg( 'centralnotice-banner-content-changed' )->text()
			)->parse() . "<br/>";
		}
		return $details;
	}

	protected function testBooleanBannerChange( $param, $newrow, $oldrow ) {
		$result = '';
		$endField = 'tmplog_end_' . $param;

		$oldval = ( $oldrow ) ? $oldrow->$endField : 0;
		if ( $oldval !== $newrow->$endField ) {
			// Give grep a chance to find the usages:
			// centralnotice-anon, centralnotice-account, centralnotice-fundraising,
			// centralnotice-autolink
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					( $oldval
						? $this->msg( 'centralnotice-on' )->text()
						: $this->msg( 'centralnotice-off' )->text() ),
					( $newrow->$endField
						? $this->msg( 'centralnotice-on' )->text()
						: $this->msg( 'centralnotice-off' )->text() )
				)->text()
			)->parse() . "<br/>";
		}
		return $result;
	}

	protected function testTextBannerChange( $param, $newrow, $oldrow ) {
		$result = '';
		$endField = 'tmplog_end_' . $param;

		$oldval = ( ( $oldrow ) ? $oldrow->$endField : '' ) ?: '';
		$newval = ( $newrow->$endField ) ?: '';

		return $this->testTextChange( $param, $newval, $oldval );
	}
}
