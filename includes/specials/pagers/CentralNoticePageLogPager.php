<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\ReverseChronologicalPager;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

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
			// include bot edits (all edits made by CentralNotice are bot edits)
			'rc_bot' => 1,
			// only MediaWiki pages
			'rc_namespace' => NS_MEDIAWIKI,
		];
		$db = CNDatabase::getReplicaDb();
		if ( $this->logType == 'bannercontent' ) {
			// Add query conditions for banner content log
			$conds += [
				// get banner content
				$db->expr( 'rc_title', IExpression::LIKE,
					new LikeValue( 'Centralnotice-template-', $db->anyString() ) ),
			];
		} else {
			// Add query conditions for banner messages log
			$conds += [
				// get banner messages
				$db->expr( 'rc_title', IExpression::LIKE, new LikeValue( 'Centralnotice-', $db->anyString() ) ),
				// exclude normal banner content
				$db->expr( 'rc_title', IExpression::NOT_LIKE,
					new LikeValue( 'Centralnotice-template-', $db->anyString() ) ),
			];
		}

		$rcQuery = RecentChange::getQueryInfo();
		return [
			'tables' => $rcQuery['tables'],
			'fields' => $rcQuery['fields'],
			'conds' => $conds,
			'join_conds' => $rcQuery['joins'],
		];
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

		// English is the default for CentralNotice messages
		$language = 'en';

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
		$htmlOut = Html::openElement( 'tr' );

		$htmlOut .= Html::element( 'td', [ 'valign' => 'top' ] );
		$htmlOut .= Html::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$lang->date( $row->rc_timestamp ) . ' ' . $lang->time( $row->rc_timestamp )
		);
		$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-user-links' )
				->rawParams( $userLink, $userTalkLink )
				->escaped()
		);
		$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$bannerCell
		);
		if ( $this->logType == 'bannermessages' ) {
			// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable,PhanTypeMismatchArgumentNullable
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
				$messageCell
			);
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
				$language
			);
		}

		$htmlOut .= Html::rawElement( 'td',
			[ 'valign' => 'top', 'class' => 'primary-summary' ],
			htmlspecialchars(
				MediaWikiServices::getInstance()->getCommentStore()->getComment( 'rc_comment', $row )->text
			)
		);
		$htmlOut .= Html::rawElement( 'td', [],
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Html::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * @return string
	 */
	public function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Html::openElement( 'table', [ 'id' => 'cn-campaign-logs', 'cellpadding' => 3 ] );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Html::element( 'th', [ 'style' => 'width: 20px;' ] );
		$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'style' => 'width: 130px;' ],
			$this->msg( 'centralnotice-timestamp' )->text()
		);
		$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-user' )->text()
		);
		$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-banner' )->text()
		);
		if ( $this->logType == 'bannermessages' ) {
			$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
				$this->msg( 'centralnotice-message' )->text()
			);
			$htmlOut .= Html::element( 'th', [ 'align' => 'left', 'style' => 'width: 100px;' ],
				$this->msg( 'centralnotice-language' )->text()
			);

			$commentWidth = '120px';

		} else {
			$commentWidth = '250px';
		}

		$htmlOut .= Html::element( 'th',
			[ 'align' => 'left', 'style' => "width: {$commentWidth};" ],
			$this->msg( 'centralnotice-change-summary-heading' )->text()
		);

		$htmlOut .= Html::rawElement( 'td', [],
			'&nbsp;'
		);
		$htmlOut .= Html::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 * @return string
	 */
	public function getEndBody() {
		return Html::closeElement( 'table' );
	}
}
