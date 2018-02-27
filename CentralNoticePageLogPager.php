<?php

/**
 * This class generates a paginated log of recent changes to banner messages (the parts that get
 * translated). We use the rencentchanges table since it is lightweight, however, this means that
 * the log only goes back 30 days.
 */
class CentralNoticePageLogPager extends ReverseChronologicalPager {
	public $viewPage, $special, $logType;

	/**
	 * Construct instance of class.
	 * @param $special object calling object
	 * @param $type string type of log - 'bannercontent' or 'bannermessages' (optional)
	 */
	function __construct( $special, $type = 'bannercontent' ) {
		$this->special = $special;
		parent::__construct();

		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
		$this->logType = $type;
	}

	/**
	 * Sort the log list by timestamp
	 */
	function getIndexField() {
		return 'rc_timestamp';
	}

	/**
	 * Pull log entries from the database
	 */
	function getQueryInfo() {
		$conds = array(
			'rc_bot' => 1, // include bot edits (all edits made by CentralNotice are bot edits)
			'rc_namespace' => 8, // only MediaWiki pages
		);
		if ( $this->logType == 'bannercontent' ) {
			// Add query contitions for banner content log
			$conds += array(
				"rc_title LIKE 'Centralnotice-template-%'", // get banner content
			);
		} else {
			// Add query contitions for banner messages log
			$conds += array(
				"rc_title LIKE 'Centralnotice-%'", // get banner messages
				"rc_title NOT LIKE 'Centralnotice-template-%'", // exclude normal banner content
			);
		}
		return array(
			'tables' => array( 'recentchanges' ),
			'fields' => '*',
			'conds' => $conds, // WHERE conditions
		);
	}

	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 */
	function formatRow( $row ) {
		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->rc_user );
		// Create the user page link
		$userLink = Linker::linkKnown( $loggedUser->getUserPage(),
			htmlspecialchars( $loggedUser->getName() ) );
		$userTalkLink = Linker::linkKnown(
			$loggedUser->getTalkPage(),
			$this->msg( 'centralnotice-talk-link' )->escaped()
		);

		$language = 'en'; // English is the default for CentralNotice messages

		if ( $this->logType == 'bannercontent' ) {
			// Extract the banner name from the title
			$pattern = '/Centralnotice-template-(.*)/';
			preg_match( $pattern, $row->rc_title, $matches );
			$banner = $matches[1];
		} elseif ( $this->logType == 'bannermessages' ) {
			// Split the title into banner, message, and language
			$titlePieces = explode( "/", $row->rc_title, 2 );
			$titleBase = $titlePieces[0];
			if ( array_key_exists( 1, $titlePieces ) ) $language = $titlePieces[1];
			$pattern = '/Centralnotice-([^-]*)-(.*)/';
			preg_match( $pattern, $titleBase, $matches );
			$banner = $matches[1];
			$message = $matches[2];
		}

		// Create banner link
		$bannerLink = Linker::linkKnown(
			$this->viewPage,
			htmlspecialchars( $banner ),
			array(),
			array( 'template' => $banner )
		);

		// Create title object
		$title = Title::newFromText( "MediaWiki:{$row->rc_title}" );

		if ( $this->logType == 'bannercontent' ) {
			// If the banner was just created, show a link to the banner. If the banner was
			// edited, show a link to the banner and a link to the diff.
			if ( $row->rc_new ) {
				$bannerCell = $bannerLink;
			} else {
				$querydiff = array(
					'curid' => $row->rc_cur_id,
					'diff' => $row->rc_this_oldid,
					'oldid' => $row->rc_last_oldid
				);
				$diffUrl = htmlspecialchars( $title->getLinkUrl( $querydiff ) );
				// Should "diff" be localised? It appears not to be elsewhere in the interface.
				// See ChangesList->preCacheMessages() for example.
				$bannerCell = $bannerLink . "&nbsp;(<a href=\"$diffUrl\">diff</a>)";
			}
		} elseif ( $this->logType == 'bannermessages' ) {
			$bannerCell = $bannerLink;

			// Create the message link
			$messageLink = Linker::linkKnown( $title, htmlspecialchars( $message ) );

			// If the message was just created, show a link to the message. If the message was
			// edited, show a link to the message and a link to the diff.
			if ( $row->rc_new ) {
				$messageCell = $messageLink;
			} else {
				$querydiff = array(
					'curid' => $row->rc_cur_id,
					'diff' => $row->rc_this_oldid,
					'oldid' => $row->rc_last_oldid
				);
				$diffUrl = htmlspecialchars( $title->getLinkUrl( $querydiff ) );
				// Should "diff" be localised? It appears not to be elsewhere in the interface.
				// See ChangesList->preCacheMessages() for example.
				$messageCell = $messageLink . "&nbsp;(<a href=\"$diffUrl\">diff</a>)";
			}
		}

		// Begin log entry primary row
		$lang = $this->getLanguage();
		$htmlOut = Xml::openElement( 'tr' );

		$htmlOut .= Xml::openElement( 'td', array( 'valign' => 'top' ) );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::element( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$lang->date( $row->rc_timestamp ) . ' ' . $lang->time( $row->rc_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$this->msg( 'centralnotice-user-links' )
				->rawParams( $userLink, $userTalkLink )
				->parse()
		);
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
			$bannerCell
		);
		if ( $this->logType == 'bannermessages' ) {
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
				$messageCell
			);
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'primary' ),
				$language
			);
		}
		$htmlOut .= Xml::tags( 'td',
			array( 'valign' => 'top', 'class' => 'primary-summary' ),
			htmlspecialchars( $row->rc_comment )
		);
		$htmlOut .= Xml::tags( 'td', array(),
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * @return String
	 */
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'id' => 'cn-campaign-logs', 'cellpadding' => 3 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', array( 'style' => 'width: 20px;' ) );
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 130px;' ),
			$this->msg ( 'centralnotice-timestamp' )->text()
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 160px;' ),
			$this->msg( 'centralnotice-user' )->text()
		);
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 160px;' ),
			$this->msg( 'centralnotice-banner' )->text()
		);
		if ( $this->logType == 'bannermessages' ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 160px;' ),
				$this->msg( 'centralnotice-message' )->text()
			);
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'style' => 'width: 100px;' ),
				$this->msg( 'centralnotice-language' )->text()
			);

			$commentWidth = '120px';

		} else {
			$commentWidth = '250px';
		}

		$htmlOut .= Xml::element( 'th',
			array( 'align' => 'left', 'style' => "width: {$commentWidth};" ),
			$this->msg( 'centralnotice-change-summary-heading' )->text()
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
}
