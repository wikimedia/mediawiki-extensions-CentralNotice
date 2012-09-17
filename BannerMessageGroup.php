<?php

/**
 * Generate a group of message definitions for a banner so they can be translated
 */
class BannerMessageGroup extends WikiMessageGroup {

	const TRANSLATE_GROUP_NAME_BASE = 'Centralnotice-tgroup';

	/**
	 * Constructor.
	 *
	 * @param string $id Unique id for this group.
	 * @param string $bannerPageName The page name of the CentralNotice banner
	 */
	public function __construct( $id, $bannerPageName ) {
		$this->id = $id;
		$this->bannerPageName = $bannerPageName;
	}

	/**
	 * Fetch the messages for the banner
	 * @return array Array of message keys with definitions.
	 */
	public function getDefinitions() {
		$definitions = array();

		// Retrieve the body source of the banner
		$bannerSource = wfMessage( $this->bannerPageName )->inContentLanguage()->plain();

		// Extract the list of message fields from the banner source.
		$fields = SpecialNoticeTemplate::extractMessageFields( $bannerSource );

		// The MediaWiki page name convention for messages is the same as the
		// convention for banners themselves, except that it doesn't include
		// the 'template' designation.
		$msgPageNamePrefix = str_replace( 'Centralnotice-template-', 'Centralnotice-', $this->bannerPageName );

		// Build the array of message definitions.
		foreach ( $fields[1] as $msgName ) {
			$key = $msgPageNamePrefix . '-' . $msgName;
			$definitions[$key] = wfMessage( $key )->inContentLanguage()->plain();
		}

		return $definitions;
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
