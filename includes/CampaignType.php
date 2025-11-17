<?php

class CampaignType {

	/** @var string */
	private $id;

	/** @var bool */
	private $onForAll;

	// Prefix for creating i18n message key from id.
	// Note: Coordinate with message keys (en.json and qqq.json) for types included
	// as default values for $wgCentralNoticeCampaignTypes.
	private const MESSAGE_KEY_PREFIX = 'centralnotice-campaign-type-';

	// Prefix for creating preference key from id.
	private const PREFERENCE_KEY_PREFIX = 'centralnotice-display-campaign-type-';

	/** @var self[] */
	private static $types;

	/**
	 * @param string $id
	 * @param bool $onForAll
	 */
	public function __construct( $id, $onForAll ) {
		$this->id = $id;
		$this->onForAll = $onForAll;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return bool
	 */
	public function getOnForAll() {
		return $this->onForAll;
	}

	/**
	 * @return string
	 */
	public function getMessageKey() {
		// The following messages are generated here:
		// * centralnotice-campaign-type-advocacy
		// * centralnotice-campaign-type-article-writing
		// * centralnotice-campaign-type-photography
		// * centralnotice-campaign-type-event
		// * centralnotice-campaign-type-fundraising
		// * centralnotice-campaign-type-governance
		// * centralnotice-campaign-type-maintenance
		// * centralnotice-campaign-type-special
		return self::MESSAGE_KEY_PREFIX . $this->id;
	}

	/**
	 * @return string
	 */
	public function getPreferenceKey() {
		// The following messages are generated here:
		// * centralnotice-display-campaign-type-advocacy
		// * centralnotice-display-campaign-type-article-writing
		// * centralnotice-display-campaign-type-photography
		// * centralnotice-display-campaign-type-event
		// * centralnotice-display-campaign-type-fundraising
		// * centralnotice-display-campaign-type-governance
		// * centralnotice-display-campaign-type-maintenance
		// * centralnotice-display-campaign-type-special
		return self::PREFERENCE_KEY_PREFIX . $this->id;
	}

	/**
	 * Get all available campaign types
	 *
	 * @return self[]
	 */
	public static function getTypes() {
		self::ensureTypes();
		return array_values( self::$types );
	}

	/**
	 * Get a campaign type by id
	 *
	 * @param string $id
	 * @return self|null Campaign type requested, or null if it doesn't exist
	 */
	public static function getById( string $id ) {
		self::ensureTypes();
		// This needs to be robust in case configuration changes and removes types that
		// remain in the DB.
		return self::$types[ $id ] ?? null;
	}

	private static function ensureTypes() {
		global $wgCentralNoticeCampaignTypes;

		if ( !self::$types ) {
			self::$types = [];
			foreach ( $wgCentralNoticeCampaignTypes as $id => $props ) {
				self::$types[ $id ] =
					new self( $id, $props[ "onForAll" ] );
			}
		}
	}
}
