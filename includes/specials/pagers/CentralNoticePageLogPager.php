<?php

use MediaWiki\MediaWikiServices;

/**
 * This class generates a paginated log of recent changes to banner messages (the parts that get
 * translated). We use the rencentchanges table since it is lightweight, however, this means that
 * the log only goes back 30 days.
 */
class CentralNoticePageLogPager extends ReverseChronologicalPager {
	/** @var Title */
	public $viewPage;
	/** @var SpecialPage */
	public $special;
	/** @var string */
	public $logType;

	/**
	 * Construct instance of class.
	 * @param SpecialPage $special object calling object
	 * @param string $type type of log - 'bannercontent' or 'bannermessages' (optional)
	 */
	public function __construct( $special, $type = 'bannercontent' ) {
		$this->special = $special;
		parent::__construct();

		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
		$this->logType = $type;
	}

	/**
	 * Sort the log list by timestamp
	 * @return string
	 */
	public function getIndexField() {
		return 'rc_timestamp';
	}

	/**
	 * Pull log entries from the database
	 * @return array[]
	 */
	public function getQueryInfo() {
		$conds = [
			'rc_bot' => 1, // include bot edits (all edits made by CentralNotice are bot edits)
			'rc_namespace' => 8, // only MediaWiki pages
		];
		$db = CNDatabase::getDb();
		if ( $this->logType == 'bannercontent' ) {
			// Add query contitions for banner content log
			$conds += [
				// get banner content
				'rc_title' . $db->buildLike( 'Centralnotice-template-', $db->anyString() ),
			];
		} else {
			// Add query contitions for banner messages log
			$conds += [
				// get banner messages
				'rc_title' . $db->buildLike( 'Centralnotice-', $db->anyString() ),
				// exclude normal banner content
				'rc_title NOT' . $db->buildLike( 'Centralnotice-template-', $db->anyString() ),
			];
		}

		$rcQuery = RecentChange::getQueryInfo();
		$ret = [
			'tables' => $rcQuery['tables'],
			'fields' => $rcQuery['fields'],
			'conds' => $conds, // WHERE conditions
			'join_conds' => $rcQuery['joins'],
		];

		return $ret;
	}

	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 * @param stdClass $row
	 * @return string HTML
	 */
	public function formatRow( $row ) {
		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->rc_user );
		// Create the user page link
		$userLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getUserPage(),
			$loggedUser->getName()
		);
		$userTalkLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getTalkPage(),
			$this->msg( 'centralnotice-talk-link' )->text()
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
			if ( array_key_exists( 1, $titlePieces ) ) {
				$language = $titlePieces[1];
			}
			$pattern = '/Centralnotice-([^-]*)-(.*)/';
			preg_match( $pattern, $titleBase, $matches );
			$banner = $matches[1];
			$message = $matches[2];
		} else {
			throw new LogicException( "Unknown type {$this->logType}" );
		}

		// Create banner link
		$bannerLink = $this->special->getLinkRenderer()->makeKnownLink(
			$this->viewPage,
			$banner,
			[],
			[ 'template' => $banner ]
		);

		// Create title object
		$title = Title::newFromText( "MediaWiki:{$row->rc_title}" );

		if ( $this->logType == 'bannercontent' ) {
			// If the banner was just created, show a link to the banner. If the banner was
			// edited, show a link to the banner and a link to the diff.
			if ( $row->rc_source === RecentChange::SRC_NEW ) {
				$bannerCell = $bannerLink;
			} else {
				$querydiff = [
					'curid' => $row->rc_cur_id,
					'diff' => $row->rc_this_oldid,
					'oldid' => $row->rc_last_oldid
				];
				$diffUrl = htmlspecialchars( $title->getLinkUrl( $querydiff ) );
				// Should "diff" be localised? It appears not to be elsewhere in the interface.
				// See ChangesList->preCacheMessages() for example.
				$bannerCell = $bannerLink . "&nbsp;(<a href=\"$diffUrl\">diff</a>)";
			}
		} elseif ( $this->logType == 'bannermessages' ) {
			$bannerCell = $bannerLink;

			// Create the message link
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
			$messageLink = $this->special->getLinkRenderer()->makeKnownLink( $title, $message );

			// If the message was just created, show a link to the message. If the message was
			// edited, show a link to the message and a link to the diff.
			if ( $row->rc_source === RecentChange::SRC_NEW ) {
				$messageCell = $messageLink;
			} else {
				$querydiff = [
					'curid' => $row->rc_cur_id,
					'diff' => $row->rc_this_oldid,
					'oldid' => $row->rc_last_oldid
				];
				$diffUrl = htmlspecialchars( $title->getLinkUrl( $querydiff ) );
				// Should "diff" be localised? It appears not to be elsewhere in the interface.
				// See ChangesList->preCacheMessages() for example.
				$messageCell = $messageLink . "&nbsp;(<a href=\"$diffUrl\">diff</a>)";
			}
		} else {
			throw new LogicException( "Unknown type {$this->logType}" );
		}

		// Begin log entry primary row
		$lang = $this->getLanguage();
		$htmlOut = Xml::openElement( 'tr' );

		$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top' ] );
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$lang->date( $row->rc_timestamp ) . ' ' . $lang->time( $row->rc_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-user-links' )
				->rawParams( $userLink, $userTalkLink )
				->parse()
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$bannerCell
		);
		if ( $this->logType == 'bannermessages' ) {
			// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable,PhanTypeMismatchArgumentNullable
			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
				$messageCell
			);
			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
				$language
			);
		}

		$htmlOut .= Xml::tags( 'td',
			[ 'valign' => 'top', 'class' => 'primary-summary' ],
			htmlspecialchars(
				MediaWikiServices::getInstance()->getCommentStore()->getComment( 'rc_comment', $row )->text
			)
		);
		$htmlOut .= Xml::tags( 'td', [],
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * @return string
	 */
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
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-banner' )->text()
		);
		if ( $this->logType == 'bannermessages' ) {
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
				$this->msg( 'centralnotice-message' )->text()
			);
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 100px;' ],
				$this->msg( 'centralnotice-language' )->text()
			);

			$commentWidth = '120px';

		} else {
			$commentWidth = '250px';
		}

		$htmlOut .= Xml::element( 'th',
			[ 'align' => 'left', 'style' => "width: {$commentWidth};" ],
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
}
