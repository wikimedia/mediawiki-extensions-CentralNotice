<?php

use MediaWiki\MediaWikiServices;

/**
 * Generate a group of message definitions for a banner so they can be translated
 */
class BannerMessageGroup extends WikiMessageGroup {

	private const TRANSLATE_GROUP_NAME_BASE = 'Centralnotice-tgroup';

	/** @var string */
	protected $bannerName = '';

	/**
	 * @var int
	 * @suppress PhanUndeclaredConstant NS_CN_BANNER defined in extension.json
	 */
	protected $namespace = NS_CN_BANNER;

	/**
	 * @param int $namespace ID of the namespace holding CentralNotice messages
	 * @param string $title The page name of the CentralNotice banner
	 */
	public function __construct( $namespace, $title ) {
		$titleObj = Title::makeTitle( $namespace, $title );
		$this->id = static::getTranslateGroupName( $title );

		// For internal usage we just want the name of the banner. In the MediaWiki namespace
		// this is stored with a prefix. Elsewhere (like the CentralNotice namespace) it is
		// just the page name.
		$this->bannerName = str_replace( 'Centralnotice-template-', '', $title );

		// And now set the label for the Translate UI
		$this->setLabel( $titleObj->getPrefixedText() );
	}

	/**
	 * This is optimized version of getDefinitions that only returns
	 * message keys to speed up message index creation.
	 * @return array
	 */
	public function getKeys() {
		$keys = [];

		$banner = Banner::fromName( $this->bannerName );
		$fields = $banner->getMessageFieldsFromCache();

		// The MediaWiki page name convention for messages is the same as the
		// convention for banners themselves, except that it doesn't include
		// the 'template' designation.
		if ( $this->namespace === NS_CN_BANNER ) {
			$msgKeyPrefix = $this->bannerName . '-';
		} else {
			$msgKeyPrefix = "Centralnotice-{$this->bannerName}-";
		}

		foreach ( array_keys( $fields ) as $msgName ) {
			$keys[] = $msgKeyPrefix . $msgName;
		}

		return $keys;
	}

	/**
	 * Fetch the messages for the banner
	 * @return array Array of message keys with definitions.
	 */
	public function getDefinitions() {
		$definitions = [];

		$banner = Banner::fromName( $this->bannerName );
		$fields = $banner->getMessageFieldsFromCache();

		// The MediaWiki page name convention for messages is the same as the
		// convention for banners themselves, except that it doesn't include
		// the 'template' designation.
		$msgDefKeyPrefix = "Centralnotice-{$this->bannerName}-";
		if ( $this->namespace === NS_CN_BANNER ) {
			$msgKeyPrefix = $this->bannerName . '-';
		} else {
			$msgKeyPrefix = $msgDefKeyPrefix;
		}

		// Build the array of message definitions.
		foreach ( $fields as $msgName => $msgCount ) {
			$defkey = $msgDefKeyPrefix . $msgName;
			$msgkey = $msgKeyPrefix . $msgName;
			$definitions[$msgkey] = wfMessage( $defkey )->inContentLanguage()->plain();
		}

		return $definitions;
	}

	/**
	 * Determine if the CentralNotice banner group is using the group review feature of translate
	 * @return bool
	 */
	public static function isUsingGroupReview() {
		static $useGroupReview = null;

		if ( $useGroupReview === null ) {
			$group = MessageGroups::getGroup( self::TRANSLATE_GROUP_NAME_BASE );
			if ( $group && $group->getMessageGroupStates() ) {
				$useGroupReview = true;
			} else {
				$useGroupReview = false;
			}
		}

		return $useGroupReview;
	}

	/**
	 * Constructs the translate group name from any number of alternate forms. The group name is
	 * defined to be 'Centralnotice-tgroup-<BannerName>'
	 *
	 * This function can handle input in the form of:
	 *  - raw banner name
	 *  - Centralnotice-template-<banner name>
	 *
	 * @param string $bannerName The name of the banner
	 *
	 * @return string Canonical translate group name
	 */
	public static function getTranslateGroupName( $bannerName ) {
		if ( strpos( $bannerName, 'Centralnotice-template' ) === 0 ) {
			return str_replace(
				'Centralnotice-template',
				self::TRANSLATE_GROUP_NAME_BASE,
				$bannerName
			);
		} else {
			return self::TRANSLATE_GROUP_NAME_BASE . '-' . $bannerName;
		}
	}

	/**
	 * Hook to handle message group review state changes. If the $newState
	 * for a group is equal to @see $wgNoticeTranslateDeployStates then this
	 * function will copy from the CNBanners namespace into the MW namespace
	 * and protect them with right $wgCentralNoticeMessageProtectRight. This
	 * implies that the user calling this hook must have site-edit permissions
	 * and the $wgCentralNoticeMessageProtectRight granted.
	 *
	 * @param MessageGroup $group Effected group object
	 * @param string $code Language code that was modified
	 * @param string $currentState Review state the group is transitioning from
	 * @param string $newState Review state the group is transitioning to
	 *
	 * @return bool
	 */
	public static function updateBannerGroupStateHook( $group, $code, $currentState, $newState ) {
		global $wgNoticeTranslateDeployStates;

		// We only need to run this if we're actually using group review
		if ( !self::isUsingGroupReview() ) {
			return true;
		}

		if ( $group instanceof AggregateMessageGroup ) {
			// Deal with an aggregate group object having changed
			$groups = $group->getGroups();
			foreach ( $groups as $subgroup ) {
				self::updateBannerGroupStateHook(
					$subgroup, $code, $currentState, $newState );
			}
		} elseif ( ( $group instanceof BannerMessageGroup )
			&& in_array( $newState, $wgNoticeTranslateDeployStates )
		) {
			// Finally an object we can deal with directly and it's in the right state!
			$collection = $group->initCollection( $code );
			$collection->loadTranslations();
			$keys = $collection->getMessageKeys();
			$user = RequestContext::getMain()->getUser();
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();

			// Now copy each key into the MW namespace
			foreach ( $keys as $key ) {
				$wikiPage = $wikiPageFactory->newFromTitle(
					Title::makeTitleSafe( NS_CN_BANNER, $key . '/' . $code )
				);

				// Make sure the translation actually exists :p
				if ( $wikiPage->exists() ) {
					$content = $wikiPage->getContent();
					/** @var TextContent $content */
					'@phan-var TextContent $content';
					$text = $content->getText();

					$wikiPage = $wikiPageFactory->newFromTitle(
						Title::makeTitleSafe( NS_MEDIAWIKI, 'Centralnotice-' . $key . '/' . $code )
					);
					$wikiPage->doUserEditContent(
						ContentHandler::makeContent( $text, $wikiPage->getTitle() ),
						$user,
						'Update from translation plugin',
						EDIT_FORCE_BOT,
						false, // $originalRevId
						[ 'centralnotice translation' ]
					);
					Banner::protectBannerContent( $wikiPage, $user, true );
				}
			}
		} else {
			// We do nothing; we don't care about this type of group; or it's in the wrong state
		}

		return true;
	}

	public function getMessageGroupStates() {
		$conf = [
			'progress' => [ 'color' => 'E00' ],
			'proofreading' => [ 'color' => 'FFBF00' ],
			'ready' => [ 'color' => 'FF0' ],
			'published' => [ 'color' => 'AEA', 'right' => 'centralnotice-admin' ],
			'state conditions' => [
				[ 'ready', [ 'PROOFREAD' => 'MAX' ] ],
				[ 'proofreading', [ 'TRANSLATED' => 'MAX' ] ],
				[ 'progress', [ 'UNTRANSLATED' => 'NONZERO' ] ],
				[ 'unset', [ 'UNTRANSLATED' => 'MAX', 'OUTDATED' => 'ZERO',
					'TRANSLATED' => 'ZERO' ] ],
			],
		];

		return new MessageGroupStates( $conf );
	}

	/**
	 * TranslatePostInitGroups hook handler
	 * Add banner message groups to the list of message groups that should be
	 * translated through the Translate extension.
	 *
	 * @param array &$list
	 * @return bool
	 */
	public static function registerGroupHook( &$list ) {
		// Must be explicitly primary for runs under a jobqueue
		$dbr = CNDatabase::getDb( DB_PRIMARY );

		// Create the base aggregate group
		$conf = [];
		$conf['BASIC'] = [
			'id' => self::TRANSLATE_GROUP_NAME_BASE,
			'label' => 'CentralNotice Banners',
			'description' => '{{int:centralnotice-aggregate-group-desc}}',
			'meta' => 1,
			'class' => 'AggregateMessageGroup',
			'namespace' => NS_CN_BANNER,
		];
		$conf['GROUPS'] = [];

		// Find all the banners marked for translation
		$tables = [ 'page', 'revtag' ];
		$vars   = [ 'page_id', 'page_namespace', 'page_title', ];
		$conds  = [ 'page_id=rt_page', 'rt_type' => Banner::TRANSLATE_BANNER_TAG ];
		$options = [ 'GROUP BY' => 'rt_page, page_id, page_namespace, page_title' ];
		$res = $dbr->select( $tables, $vars, $conds, __METHOD__, $options );

		foreach ( $res as $r ) {
			$grp = new BannerMessageGroup( $r->page_namespace, $r->page_title );
			$id = $grp::getTranslateGroupName( $r->page_title );
			$list[$id] = $grp;

			// Add the banner group to the aggregate group
			$conf['GROUPS'][] = $id;
		}

		// Update the subgroup meta with any new groups since the last time this was run
		$list[$conf['BASIC']['id']] = MessageGroupBase::factory( $conf );

		return true;
	}

	public static function getLanguagesInState( $banner, $state ) {
		if ( !self::isUsingGroupReview() ) {
			throw new LogicException(
				'CentralNotice is not using group review. Cannot query group review state.'
			);
		}

		$groupName = self::getTranslateGroupName( $banner );

		$db = CNDatabase::getDb();
		$result = $db->select(
			'translate_groupreviews',
			'tgr_lang',
			[
				'tgr_group' => $groupName,
				'tgr_state' => $state,
			],
			__METHOD__
		);

		$langs = [];
		foreach ( $result as $row ) {
			$langs[] = $row->tgr_lang;
		}
		return $langs;
	}
}
