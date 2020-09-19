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

	/** @var CampaignType[] */
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
		return self::MESSAGE_KEY_PREFIX . $this->id;
	}

	/**
	 * @return string
	 */
	public function getPreferenceKey() {
		return self::PREFERENCE_KEY_PREFIX . $this->id;
	}

	/**
	 * Get all available campaign types
	 *
	 * @return CampaignType[]
	 */
	public static function getTypes() {
		self::ensureTypes();
		return array_values( self::$types );
	}

	/**
	 * Get a campaign type by id
	 *
	 * @param string $id
	 * @return CampaignType|null Campaign type requested, or null if it doesn't exist
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
					new CampaignType( $id, $props[ "onForAll" ] );
			}
		}
	}
}
