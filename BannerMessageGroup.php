<?php

/**
 * Generate a group of message definitions for a banner so they can be translated
 */
class BannerMessageGroup extends WikiMessageGroup {

	const TRANSLATE_GROUP_NAME_BASE = 'Centralnotice-tgroup';

	protected $bannerPageName = '';

	protected $namespace = NS_CN_BANNER;

	/**
	 * Constructor.
	 *
	 * @param string $id Unique id for this group.
	 * @param string $bannerPageName The page name of the CentralNotice banner
	 */
	public function __construct( $namespace, $title ) {

        $titleObj = Title::makeTitle( $namespace, $title );
        $this->id = static::getTranslateGroupName( $title );

		// For internal usage we just want the name of the banner. In the MediaWiki namespace
        // this is stored with a prefix. Elsewhere (like the CentralNotice namespace) it is
        // just the page name.
		$this->bannerPageName = str_replace( 'Centralnotice-template-', '', $title );

        // And now set the label for the Translate UI
        $this->setLabel( $titleObj->getPrefixedText() );
	}

	/**
	 * Fetch the messages for the banner
	 * @return array Array of message keys with definitions.
	 */
	public function getDefinitions() {
		$definitions = array();

		// Retrieve the body source of the banner
		$bannerSource = wfMessage(
			"Centralnotice-template-{$this->bannerPageName}"
		)->inContentLanguage()->plain();

		// Extract the list of message fields from the banner source.
		$fields = SpecialNoticeTemplate::extractMessageFields( $bannerSource );

		// The MediaWiki page name convention for messages is the same as the
		// convention for banners themselves, except that it doesn't include
		// the 'template' designation.
		$msgDefKeyPrefix = "Centralnotice-{$this->bannerPageName}-";
		if ( $this->namespace == NS_CN_BANNER ) {
			$msgKeyPrefix = $this->bannerPageName . '-';
		}
		else {
			$msgKeyPrefix = $msgDefKeyPrefix;
		}

		// Build the array of message definitions.
		foreach ( $fields[1] as $msgName ) {
			$defkey = $msgDefKeyPrefix . $msgName;
			$msgkey = $msgKeyPrefix . $msgName;
			$definitions[$msgkey] = wfMessage( $defkey )->inContentLanguage()->plain();
		}

		return $definitions;
	}

	/**
	 * Determine if the CentralNotice banner group is using the group review feature of translate
	 */
	static function isUsingGroupReview() {
		static $useGroupReview = null;

		if ( $useGroupReview === null ) {
			$group = MessageGroups::getGroup( static::TRANSLATE_GROUP_NAME_BASE );
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
	static function getTranslateGroupName( $bannerName ) {
		if ( strpos( $bannerName, 'Centralnotice-template' ) === 0 ) {
			return str_replace( 'Centralnotice-template', static::TRANSLATE_GROUP_NAME_BASE, $bannerName );
		} else {
			return static::TRANSLATE_GROUP_NAME_BASE . '-' . $bannerName;
		}
	}

	/**
	 * Hook to handle message group review state changes. If the $newState for a group is equal to
	 * @see $wgNoticeTranslateDeployStates then this function will copy from the CNBanners namespace
	 * into the MW namespace. This implies that the user calling this hook must have site-edit
	 * permissions.
	 *
	 * @param object        $group        Effected group object
	 * @param string        $code         Language code that was modified
	 * @param string        $currentState Review state the group is transitioning from
	 * @param string        $newState     Review state the group is transitioning to
	 *
	 * @return bool
	 */
	static function updateBannerGroupStateHook( $group, $code, $currentState, $newState ) {
		global $wgNoticeTranslateDeployStates;

		// We only need to run this if we're actually using group review
		if ( !BannerMessageGroup::isUsingGroupReview() ) {
			return true;
		}

		if ( $group instanceof AggregateMessageGroup ) {
			// Deal with an aggregate group object having changed
			$groups = $group->getGroups();
			foreach ( $groups as $subgroup ) {
				static::updateBannerGroupStateHook( $subgroup, $code, $currentState, $newState );
			}
		}
		elseif ( ( $group instanceof BannerMessageGroup )
				 && in_array( $newState, $wgNoticeTranslateDeployStates )
		) {
			// Finally an object we can deal with directly and it's in the right state!
			$collection = $group->initCollection( $code );
			$collection->loadTranslations( DB_MASTER );
			$keys = $collection->getMessageKeys();

			// Now copy each key into the MW namespace
			foreach ( $keys as $key ) {
				$wikiPage = new WikiPage(
					Title::makeTitleSafe( NS_CN_BANNER, $key . '/' . $code )
				);

				// Make sure the translation actually exists :p
				if ( $wikiPage->exists() ) {
					$text = $wikiPage->getContent()->getNativeData();

					$wikiPage = new WikiPage(
						Title::makeTitleSafe( NS_MEDIAWIKI, 'Centralnotice-' . $key . '/' . $code )
					);
					if ( class_exists( 'ContentHandler' ) ) {
						// MediaWiki 1.21+
						$wikiPage->doEditContent(
							ContentHandler::makeContent( $text, $wikiPage->getTitle() ),
							'Update from translation plugin',
							EDIT_FORCE_BOT
						);
					} else {
						// Legacy -- pre content handler
						$wikiPage->doEdit( $text, 'Update from translation plugin', EDIT_FORCE_BOT );
					}
				}
			}
		}
		else {
			// We do nothing; we don't care about this type of group; or it's in the wrong state
		}

		return true;
	}

	public function getMessageGroupStates() {
		$conf = array(
			'progress' => array( 'color' => 'E00' ),
			'proofreading' => array( 'color' => 'FFBF00' ),
			'ready' => array( 'color' => 'FF0' ),
			'published' => array( 'color' => 'AEA', 'right' => 'centralnotice-admin' ),
			'state conditions' => array(
				array( 'ready', array( 'PROOFREAD' => 'MAX' ) ),
				array( 'proofreading', array( 'TRANSLATED' => 'MAX' ) ),
				array( 'progress', array( 'UNTRANSLATED' => 'NONZERO' ) ),
				array( 'unset', array( 'UNTRANSLATED' => 'MAX', 'OUTDATED' => 'ZERO', 'TRANSLATED' => 'ZERO' ) ),
			),
		);

		return new MessageGroupStates( $conf );
	}
}
