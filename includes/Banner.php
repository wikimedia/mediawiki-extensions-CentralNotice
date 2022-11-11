<?php
/**
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\Rdbms\IDatabase;

/**
 * CentralNotice banner object. Banners are pieces of rendered wikimarkup
 * injected as HTML onto MediaWiki pages via the sitenotice hook.
 *
 * - They are allowed to be specific to devices and user status.
 * - They allow 'mixins', pieces of javascript that add additional standard
 *   functionality to the banner.
 * - They have a concept of 'messages' which are translatable strings marked
 *   out by {{{name}}} in the banner body.
 *
 * @see BannerMessage
 * @see BannerRenderer
 * @see BannerMixin
 */
class Banner {
	/** Indicates a revision of a banner that can be translated. */
	public const TRANSLATE_BANNER_TAG = 'banner:translate';

	/**
	 * Keys indicate a group of properties (which should be a 1-to-1 match to
	 * a database table.) If the value is null it means the data is not yet
	 * loaded. True means the data is clean and not modified. False means the
	 * data should be saved on the next call to save().
	 *
	 * Most functions should only ever set the flag to true; flags will be
	 * reset to false in save().
	 *
	 * @var (null|bool)[]
	 */
	protected $dirtyFlags = [
		'content' => null,
		'messages' => null,
		'basic' => null,
		'devices' => null,
		'mixins' => null,
		'prioritylang' => null,
	];

	/** @var int Unique database identifier key. */
	protected $id = null;

	/** @var string Unique human friendly name of banner. */
	protected $name = null;

	/** @var bool True if the banner should be allocated to anonymous users. */
	protected $allocateAnon = false;

	/** @var bool True if the banner should be allocated to logged in users. */
	protected $allocateLoggedIn = false;

	/** @var string Category that the banner belongs to. Will be special value expanded. */
	protected $category = '{{{campaign}}}';

	/** @var bool True if archived and hidden from default view. */
	protected $archived = false;

	/** @var string[] Devices this banner should be allocated to in the form
	 * {Device ID => Device header name}
	 */
	protected $devices = [];

	/** @var string[] Names of enabled mixins */
	protected $mixins = [];

	/** @var string[] Language codes considered a priority for translation. */
	protected $priorityLanguages = [];

	/** @var string Wikitext content of the banner */
	protected $bodyContent = '';

	/** @var bool */
	protected $runTranslateJob = false;

	/** @var bool Is banner meant to be used as a template for other banners */
	protected $template = false;

	/**
	 * Create a banner object from a known ID. Must already be
	 * an object in the database. If a fully new banner is to be created
	 * use @see newFromName().
	 *
	 * @param int $id Unique database ID of the banner
	 *
	 * @return self
	 */
	public static function fromId( $id ) {
		$obj = new self();
		$obj->id = $id;
		return $obj;
	}

	/**
	 * Create a banner object from a known banner name. Must already be
	 * an object in the database. If a fully new banner is to be created
	 * use @see newFromName().
	 *
	 * @param string $name
	 *
	 * @return self
	 * @throws BannerDataException
	 */
	public static function fromName( $name ) {
		if ( !self::isValidBannerName( $name ) ) {
			throw new BannerDataException( "Invalid banner name supplied." );
		}

		$obj = new self();
		$obj->name = $name;
		return $obj;
	}

	/**
	 * Create a brand new banner object.
	 *
	 * @param string $name
	 *
	 * @return self
	 * @throws BannerDataException
	 */
	public static function newFromName( $name ) {
		if ( !self::isValidBannerName( $name ) ) {
			throw new BannerDataException( "Invalid banner name supplied." );
		}

		$obj = new self();
		$obj->name = $name;

		foreach ( $obj->dirtyFlags as $flag => &$value ) {
			$value = true;
		}

		return $obj;
	}

	/**
	 * Get the unique ID for this banner.
	 *
	 * @return int
	 */
	public function getId() {
		$this->populateBasicData();
		return $this->id;
	}

	/**
	 * Get the unique name for this banner.
	 *
	 * This specifically does not include namespace or other prefixing.
	 *
	 * @return null|string
	 */
	public function getName() {
		// Optimization: Speed up BannerMessageGroup's getKeys and getDefinitions.
		//
		// BannerMessageGroup calls self::fromName which populates $this->name.
		// Translate calls BannerMessageGroup::getKeys(), which calls
		// self::getMessageFieldsFromCache to load the message keys.
		// self::getMessageFieldsFromCache calls self::getMessageFieldsCacheKey which calls
		// this method. self::populateBasicData does a database query, which is not needed
		// if we only need to know the banner name, which we already have.
		if ( $this->name === null ) {
			$this->populateBasicData();
		}
		return $this->name;
	}

	/**
	 * Should we allocate this banner to anonymous users.
	 *
	 * @return bool
	 */
	public function allocateToAnon() {
		$this->populateBasicData();
		return $this->allocateAnon;
	}

	/**
	 * Should we allocate this banner to logged in users.
	 *
	 * @return bool
	 */
	public function allocateToLoggedIn() {
		$this->populateBasicData();
		return $this->allocateLoggedIn;
	}

	/**
	 * Set user state allocation properties for this banner
	 *
	 * @param bool $anon Should the banner be allocated to logged out users.
	 * @param bool $loggedIn Should the banner be allocated to logged in users.
	 *
	 * @return $this
	 */
	public function setAllocation( $anon, $loggedIn ) {
		$this->populateBasicData();

		if ( ( $this->allocateAnon !== $anon ) || ( $this->allocateLoggedIn !== $loggedIn ) ) {
			$this->setBasicDataDirty();
			$this->allocateAnon = $anon;
			$this->allocateLoggedIn = $loggedIn;
		}

		return $this;
	}

	/**
	 * Get the banner category.
	 *
	 * The category is the name of the cookie stored on the users computer. In this way
	 * banners in the same category may share settings.
	 *
	 * @return string
	 */
	public function getCategory() {
		$this->populateBasicData();
		return $this->category;
	}

	/**
	 * Set the banner category.
	 *
	 * @see Banner->getCategory()
	 *
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setCategory( $value ) {
		$this->populateBasicData();

		if ( $this->category !== $value ) {
			$this->setBasicDataDirty();
			$this->category = $value;
		}

		return $this;
	}

	/**
	 * Obtain an array of all categories currently seen attached to banners
	 * @return string[]
	 */
	public static function getAllUsedCategories() {
		$db = CNDatabase::getDb();
		$res = $db->select(
			'cn_templates',
			'tmp_category',
			'',
			__METHOD__,
			[ 'DISTINCT', 'ORDER BY tmp_category ASC' ]
		);

		$categories = [];
		foreach ( $res as $row ) {
			$categories[$row->tmp_category] = $row->tmp_category;
		}
		return $categories;
	}

	/**
	 * Remove invalid characters from a category string that has been magic
	 * word expanded.
	 *
	 * @param string $cat Category string to sanitize
	 *
	 * @return string
	 */
	public static function sanitizeRenderedCategory( $cat ) {
		return preg_replace( '/[^a-zA-Z0-9_]/', '', $cat );
	}

	/**
	 * Should the banner be considered archived and hidden from default view
	 *
	 * @return bool
	 */
	public function isArchived() {
		$this->populateBasicData();
		return $this->archived;
	}

	/**
	 * Is this banner meant to be used as a template for other banners
	 *
	 * @return bool
	 * @throws BannerDataException
	 * @throws BannerExistenceException
	 */
	public function isTemplate() {
		$this->populateBasicData();
		return $this->template;
	}

	/**
	 * Mark banner as a template
	 *
	 * @param bool $value
	 * @throws BannerDataException
	 * @throws BannerExistenceException
	 */
	public function setIsTemplate( $value ) {
		$this->populateBasicData();
		if ( $this->template !== $value ) {
			$this->setBasicDataDirty();
			$this->template = $value;
		}
	}

	/**
	 * Populates basic banner data by querying the cn_templates table
	 *
	 * @throws BannerDataException If neither a name or ID can be used to query for data
	 * @throws BannerExistenceException If no banner data was received
	 */
	protected function populateBasicData() {
		if ( $this->dirtyFlags['basic'] !== null ) {
			return;
		}

		$db = CNDatabase::getDb();

		// What are we using to select on?
		if ( $this->name !== null ) {
			$selector = [ 'tmp_name' => $this->name ];
		} elseif ( $this->id !== null ) {
			$selector = [ 'tmp_id' => $this->id ];
		} else {
			throw new BannerDataException( 'Cannot retrieve banner data without name or ID.' );
		}

		// Query!
		$rowRes = $db->select(
			[ 'templates' => 'cn_templates' ],
			[
				'tmp_id',
				'tmp_name',
				'tmp_display_anon',
				'tmp_display_account',
				'tmp_archived',
				'tmp_category',
				'tmp_is_template'
			],
			$selector,
			__METHOD__
		);

		// Extract the dataz!
		$row = $rowRes->fetchObject();
		if ( $row ) {
			$this->id = (int)$row->tmp_id;
			$this->name = $row->tmp_name;
			$this->allocateAnon = (bool)$row->tmp_display_anon;
			$this->allocateLoggedIn = (bool)$row->tmp_display_account;
			$this->archived = (bool)$row->tmp_archived;
			$this->category = $row->tmp_category;
			$this->template = (bool)$row->tmp_is_template;
		} else {
			$keystr = [];
			foreach ( $selector as $key => $value ) {
				$keystr[] = "{$key} = {$value}";
			}
			$keystr = implode( " AND ", $keystr );
			throw new BannerExistenceException(
				"No banner exists where {$keystr}. Could not load."
			);
		}

		// Set the dirty flag to not dirty because we just loaded clean data
		$this->setBasicDataDirty( false );
	}

	/**
	 * Sets the flag which will save basic metadata on next save()
	 * @param bool $dirty
	 */
	protected function setBasicDataDirty( $dirty = true ) {
		$this->dirtyFlags['basic'] = $dirty;
	}

	/**
	 * Helper function to initializeDbForNewBanner()
	 *
	 * @param IDatabase $db
	 */
	protected function initializeDbBasicData( IDatabase $db ) {
		$db->insert( 'cn_templates', [ 'tmp_name' => $this->name ], __METHOD__ );
		$this->id = $db->insertId();
	}

	/**
	 * Helper function to saveBannerInternal() for saving basic banner metadata
	 * @param IDatabase $db
	 */
	protected function saveBasicData( IDatabase $db ) {
		if ( $this->dirtyFlags['basic'] ) {
			$db->update( 'cn_templates',
				[
					'tmp_display_anon'    => (int)$this->allocateAnon,
					'tmp_display_account' => (int)$this->allocateLoggedIn,
					'tmp_archived'        => (int)$this->archived,
					'tmp_category'        => $this->category,
					'tmp_is_template'     => (int)$this->template
				],
				[
					'tmp_id'              => $this->id
				],
				__METHOD__
			);
		}
	}

	/**
	 * Get the devices that this banner should be allocated to.
	 *
	 * Array is in the form of {Device internal ID => Device header name}
	 *
	 * @return string[]
	 */
	public function getDevices() {
		$this->populateDeviceTargetData();
		return $this->devices;
	}

	/**
	 * Set the devices that this banner should be allocated to.
	 *
	 * @param string[]|string $devices Header name of devices. E.g. {'android', 'desktop'}
	 *
	 * @return $this
	 * @throws BannerDataException on unknown device header name.
	 */
	public function setDevices( $devices ) {
		$this->populateDeviceTargetData();

		$knownDevices = CNDeviceTarget::getAvailableDevices( true );

		$devices = (array)$devices;
		$devices = array_unique( array_values( $devices ) );
		sort( $devices );

		if ( $devices != $this->devices ) {
			$this->devices = [];

			foreach ( $devices as $device ) {
				if ( !$device ) {
					// Empty...
					continue;
				} elseif ( !array_key_exists( $device, $knownDevices ) ) {
					throw new BannerDataException( "Device name '$device' not known! Cannot add." );
				} else {
					$this->devices[$knownDevices[$device]['id']] = $device;
				}
			}
			$this->markDeviceTargetDataDirty();
		}

		return $this;
	}

	/**
	 * Populates device targeting data by querying the cn_template_devices table.
	 *
	 * @see CNDeviceTarget for more information about mapping.
	 */
	protected function populateDeviceTargetData() {
		if ( $this->dirtyFlags['devices'] !== null ) {
			return;
		}

		$db = CNDatabase::getDb();

		$rowObj = $db->select(
			[
				'tdev' => 'cn_template_devices',
				'devices' => 'cn_known_devices'
			],
			[ 'devices.dev_id', 'dev_name' ],
			[
				'tdev.tmp_id' => $this->getId(),
				'tdev.dev_id = devices.dev_id'
			],
			__METHOD__
		);

		foreach ( $rowObj as $row ) {
			$this->devices[ intval( $row->dev_id ) ] = $row->dev_name;
		}

		$this->markDeviceTargetDataDirty( false );
	}

	/**
	 * Sets the flag which will force saving of device targeting data on next save()
	 * @param bool $dirty
	 */
	protected function markDeviceTargetDataDirty( $dirty = true ) {
		$this->dirtyFlags['devices'] = $dirty;
	}

	/**
	 * Helper function to saveBannerInternal()
	 *
	 * @param IDatabase $db
	 */
	protected function saveDeviceTargetData( IDatabase $db ) {
		if ( $this->dirtyFlags['devices'] ) {
			// Remove all entries from the table for this banner
			$db->delete( 'cn_template_devices', [ 'tmp_id' => $this->getId() ], __METHOD__ );

			// Add the new device mappings
			if ( $this->devices ) {
				$modifyArray = [];
				foreach ( $this->devices as $deviceId => $deviceName ) {
					$modifyArray[] = [ 'tmp_id' => $this->getId(), 'dev_id' => $deviceId ];
				}
				$db->insert( 'cn_template_devices', $modifyArray, __METHOD__ );
			}
		}
	}

	/**
	 * @return array Keys are names of enabled mixins; valeus are mixin params.
	 * @see $wgCentralNoticeBannerMixins
	 * TODO: Remove. See T225831.
	 */
	public function getMixins() {
		$this->populateMixinData();
		return $this->mixins;
	}

	/**
	 * Set the banner mixins to enable.
	 *
	 * @param array $mixins Names of mixins to enable on this banner. Valid values
	 * come from @see $wgCentralNoticeBannerMixins
	 *
	 * @throws RangeException
	 * @return $this
	 */
	public function setMixins( $mixins ) {
		global $wgCentralNoticeBannerMixins;

		$this->populateMixinData();

		$mixins = array_unique( $mixins );
		sort( $mixins );

		if ( $this->mixins != $mixins ) {
			$this->markMixinDataDirty();
		}

		$this->mixins = [];
		foreach ( $mixins as $mixin ) {
			if ( !array_key_exists( $mixin, $wgCentralNoticeBannerMixins ) ) {
				throw new RangeException( "Mixin does not exist: {$mixin}" );
			}
			$this->mixins[$mixin] = $wgCentralNoticeBannerMixins[$mixin];
		}

		return $this;
	}

	/**
	 * Populates mixin data from the cn_template_mixins table.
	 */
	protected function populateMixinData() {
		global $wgCentralNoticeBannerMixins;

		if ( $this->dirtyFlags['mixins'] !== null ) {
			return;
		}

		$dbr = CNDatabase::getDb();

		$result = $dbr->select( 'cn_template_mixins', 'mixin_name',
			[
				"tmp_id" => $this->getId(),
			],
			__METHOD__
		);

		$this->mixins = [];
		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->mixin_name, $wgCentralNoticeBannerMixins ) ) {
				// We only want to warn here otherwise we'd never be able to
				// edit the banner to fix the issue! The editor should warn
				// when a deprecated mixin is being used; but also when we
				// do deprecate something we should make sure nothing is using
				// it!
				wfLogWarning(
					"Mixin does not exist: {$row->mixin_name}, included from banner {$this->name}"
				);
			}
			$this->mixins[$row->mixin_name] = $wgCentralNoticeBannerMixins[$row->mixin_name];
		}

		$this->markMixinDataDirty( false );
	}

	/**
	 * Sets the flag which will force saving of mixin data upon next save()
	 * @param bool $dirty
	 */
	protected function markMixinDataDirty( $dirty = true ) {
		$this->dirtyFlags['mixins'] = $dirty;
	}

	/**
	 * @param IDatabase $db
	 */
	protected function saveMixinData( IDatabase $db ) {
		if ( $this->dirtyFlags['mixins'] ) {
			$db->delete( 'cn_template_mixins',
				[ 'tmp_id' => $this->getId() ],
				__METHOD__
			);

			foreach ( $this->mixins as $name => $params ) {
				$name = trim( $name );
				if ( !$name ) {
					continue;
				}
				$db->insert( 'cn_template_mixins',
					[
						'tmp_id' => $this->getId(),
						'page_id' => 0,	// TODO: What were we going to use this for again?
						'mixin_name' => $name,
					],
					__METHOD__
				);
			}
		}
	}

	/**
	 * Returns language codes that are considered a priority for translations.
	 *
	 * If a language is in this list it means that the translation UI will promote
	 * translating them, and discourage translating other languages.
	 *
	 * @return string[]
	 */
	public function getPriorityLanguages() {
		$this->populatePriorityLanguageData();
		return $this->priorityLanguages;
	}

	/**
	 * Set language codes that should be considered a priority for translation.
	 *
	 * If a language is in this list it means that the translation UI will promote
	 * translating them, and discourage translating other languages.
	 *
	 * @param string[] $languageCodes
	 *
	 * @return $this
	 */
	public function setPriorityLanguages( $languageCodes ) {
		$this->populatePriorityLanguageData();

		$languageCodes = array_unique( (array)$languageCodes );
		sort( $languageCodes );

		if ( $this->priorityLanguages != $languageCodes ) {
			$this->priorityLanguages = $languageCodes;
			$this->markPriorityLanguageDataDirty();
		}

		return $this;
	}

	protected function populatePriorityLanguageData() {
		global $wgNoticeUseTranslateExtension;

		if ( $this->dirtyFlags['prioritylang'] !== null ) {
			return;
		}

		if ( $wgNoticeUseTranslateExtension ) {
			$langs = TranslateMetadata::get(
				BannerMessageGroup::getTranslateGroupName( $this->getName() ),
				'prioritylangs'
			);
			if ( !$langs ) {
				// If priority langs is not set; TranslateMetadata::get will return false
				$langs = '';
			}
			$this->priorityLanguages = explode( ',', $langs );
		}
		$this->markPriorityLanguageDataDirty( false );
	}

	protected function markPriorityLanguageDataDirty( $dirty = true ) {
		$this->dirtyFlags['prioritylang'] = $dirty;
	}

	protected function savePriorityLanguageData() {
		global $wgNoticeUseTranslateExtension;

		if ( $wgNoticeUseTranslateExtension && $this->dirtyFlags['prioritylang'] ) {
			$groupName = BannerMessageGroup::getTranslateGroupName( $this->getName() );
			if ( $this->priorityLanguages === [] ) {
				// Using false to delete the value instead of writing empty content
				TranslateMetadata::set( $groupName, 'prioritylangs', false );
			} else {
				TranslateMetadata::set(
					$groupName,
					'prioritylangs',
					implode( ',', $this->priorityLanguages )
				);
			}
		}
	}

	public function getDbKey() {
		$name = $this->getName();
		return "Centralnotice-template-{$name}";
	}

	public function getTitle() {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	/**
	 * Return the names of campaigns that this banner is currently used in.
	 *
	 * @return string[]
	 */
	public function getCampaignNames() {
		$dbr = CNDatabase::getDb();

		$result = $dbr->select(
			[
				'notices' => 'cn_notices',
				'assignments' => 'cn_assignments',
			],
			'notices.not_name',
			[
				'assignments.tmp_id' => $this->getId(),
			],
			__METHOD__,
			[],
			[
				'assignments' =>
				[
					'INNER JOIN', 'notices.not_id = assignments.not_id'
				]
			]
		);

		$campaigns = [];
		foreach ( $result as $row ) {
			$campaigns[] = $row->not_name;
		}

		return $campaigns;
	}

	/**
	 * Returns an array of Title objects that have been included as templates
	 * in this banner.
	 *
	 * @return Title[]
	 */
	public function getIncludedTemplates() {
		return $this->getTitle()->getTemplateLinksFrom();
	}

	/**
	 * Get the raw body HTML for the banner.
	 *
	 * @return string HTML
	 */
	public function getBodyContent() {
		$this->populateBodyContent();
		return $this->bodyContent;
	}

	/**
	 * Set the raw body HTML for the banner.
	 *
	 * @param string $text HTML
	 *
	 * @return $this
	 */
	public function setBodyContent( $text ) {
		$this->populateBodyContent();

		if ( $this->bodyContent !== $text ) {
			$this->bodyContent = $text;
			$this->markBodyContentDirty();
		}

		return $this;
	}

	protected function populateBodyContent() {
		if ( $this->dirtyFlags['content'] !== null ) {
			return;
		}

		$curRev = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $this->getTitle() );
		if ( !$curRev ) {
			throw new BannerContentException( "No content for banner: {$this->name}" );
		}

		$content = $curRev->getContent( SlotRecord::MAIN );
		$this->bodyContent = ( $content instanceof TextContent ) ? $content->getText() : null;

		$this->markBodyContentDirty( false );
	}

	/**
	 * @param bool $dirty If true, we're storing a flag that means the
	 * in-memory banner content is newer than what's stored in the database.
	 * If false, we're clearing that bit.
	 */
	protected function markBodyContentDirty( $dirty = true ) {
		$this->dirtyFlags['content'] = $dirty;
	}

	/**
	 * @param string|null $summary
	 * @param User $user
	 */
	protected function saveBodyContent( $summary, User $user ) {
		global $wgNoticeUseTranslateExtension;

		if ( $this->dirtyFlags['content'] ) {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->getTitle() );

			if ( $summary === null ) {
				$summary = '';
			}

			$contentObj = ContentHandler::makeContent( $this->bodyContent, $wikiPage->getTitle() );

			$tags = [ 'centralnotice' ];
			$pageResult = $wikiPage->doUserEditContent(
				$contentObj,
				$user,
				$summary,
				EDIT_FORCE_BOT,
				false, // $originalRevId
				$tags
			);

			self::protectBannerContent( $wikiPage, $user );

			if ( $wgNoticeUseTranslateExtension ) {
				// Get the revision and page ID of the page that was created/modified
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				if ( $pageResult->value['revision-record'] ) {
					// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
					$revisionRecord = $pageResult->value['revision-record'];
					$revisionId = $revisionRecord->getId();
					$pageId = $revisionRecord->getPageId();

					// If the banner includes translatable messages, tag it for translation
					$fields = $this->extractMessageFields();
					if ( count( $fields ) > 0 ) {
						// Tag the banner for translation
						self::addTag( self::TRANSLATE_BANNER_TAG, $revisionId, $pageId, (string)$this->getId() );
						$this->runTranslateJob = true;
					}
					$this->invalidateCache( $fields );
				}
			}
		}
	}

	public function getMessageField( $field_name ) {
		return new BannerMessage( $this->getName(), $field_name );
	}

	/**
	 * Returns all the message fields in a banner
	 *
	 * Check the cache first, then calculate if necessary.  Will always prefer the cache.
	 * @see Banner::extractMessageFields()
	 *
	 * @return array
	 */
	public function getMessageFieldsFromCache() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $this->getMessageFieldsCacheKey( $cache );

		return $cache->getWithSetCallback(
			$key,
			$cache::TTL_MONTH,
			function () {
				return $this->extractMessageFields();
			},
			[ 'checkKeys' => [ $key ], 'lockTSE' => 60 ]
		);
	}

	/**
	 * @param array|null $newFields Optional new result of the extractMessageFields()
	 */
	public function invalidateCache( $newFields = null ) {
		// Update cache after the DB transaction finishes
		CNDatabase::getDb()->onTransactionCommitOrIdle(
			function () use ( $newFields ) {
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$key = $this->getMessageFieldsCacheKey( $cache );

				$cache->touchCheckKey( $key );
				if ( $newFields !== null ) {
					// May as well set the new volatile cache value in the local datacenter
					$cache->set( $key, $newFields, $cache::TTL_MONTH );
				}
			},
			__METHOD__
		);
	}

	/**
	 * @param WANObjectCache $cache
	 * @return mixed
	 */
	protected function getMessageFieldsCacheKey( $cache ) {
		return $cache->makeKey( 'centralnotice', 'bannerfields', $this->getName() );
	}

	/**
	 * Extract the raw fields and field names from the banner body source.
	 *
	 * Always recalculate.  If you want the cached value, please use getMessageFieldsFromCache.
	 *
	 * @return array
	 */
	public function extractMessageFields() {
		$parser = MediaWikiServices::getInstance()->getParser();

		$expanded = $parser->parse(
			$this->getBodyContent(), $this->getTitle(),
			ParserOptions::newFromContext( RequestContext::getMain() )
		)->getText();

		// Also search the preload js for fields.
		$renderer = new BannerRenderer( RequestContext::getMain(), $this );
		$expanded .= $renderer->getPreloadJsRaw();

		// Extract message fields from the banner body
		$fields = [];
		$allowedChars = Title::legalChars();
		// Janky custom syntax to pass arguments to a message, broken and unused:
		// "{{{fieldname:arg1|arg2}}}". This is why ':' is forbidden. FIXME: Remove.
		// Also forbid ',' so we can use it as a separator between banner name and
		// message name in banner content, and thus insert messages from other banners.
		// ' ' is forbidden to avoid problems in the UI.
		$allowedChars = str_replace( [ ':', ',', ' ' ], '', $allowedChars );
		preg_match_all( "/{{{([$allowedChars]+)(:[^}]*)?}}}/u", $expanded, $fields );

		// Remove duplicate keys and count occurrences
		$unique_fields = array_unique( array_flip( $fields[1] ) );
		$fields = array_intersect_key( array_count_values( $fields[1] ), $unique_fields );

		$fields = array_diff_key( $fields, array_flip( $renderer->getMagicWords() ) );

		return $fields;
	}

	/**
	 * Returns a list of messages that are either published or in the CNBanner translation
	 *
	 * @param bool $inTranslation If true and using group translation this will return
	 * all the messages that are in the translation system
	 *
	 * @return array A list of languages with existing field translations
	 */
	public function getAvailableLanguages( $inTranslation = false ) {
		global $wgLanguageCode;
		$availableLangs = [];

		// Bit of an ugly hack to get just the banner prefix
		$prefix = $this->getMessageField( '' )
			->getDbKey( null, $inTranslation ? NS_CN_BANNER : NS_MEDIAWIKI );

		$db = CNDatabase::getDb();
		$result = $db->select( 'page',
			'page_title',
			[
				'page_namespace' => $inTranslation ? NS_CN_BANNER : NS_MEDIAWIKI,
				'page_title' . $db->buildLike( $prefix, $db->anyString() ),
			],
			__METHOD__
		);
		foreach ( $result as $row ) {
			if (
				preg_match(
					"/\Q{$prefix}\E([^\/]+)(?:\/([a-z_]+))?/", $row->page_title,
					$matches
				)
			) {
				$lang = $matches[2] ?? $wgLanguageCode;
				$availableLangs[$lang] = true;
			}
		}
		return array_keys( $availableLangs );
	}

	/**
	 * Saves any changes made to the banner object into the database
	 *
	 * @param User $user
	 * @param string|null $summary Summary (comment) to associate with all changes,
	 *   including banner content and messages (which are implemented as wiki
	 *   pages).
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function save( User $user, $summary = null ) {
		$db = CNDatabase::getDb();
		$action = 'modified';

		try {
			// Don't move this to saveBannerInternal--can't be in a transaction
			// TODO: explain why not.  Is text in another database?
			$this->saveBodyContent( $summary, $user );

			// Open a transaction so that everything is consistent
			$db->startAtomic( __METHOD__ );

			if ( !$this->exists() ) {
				$action = 'created';
				$this->initializeDbForNewBanner( $db );
			}
			$this->saveBannerInternal( $db );
			$this->logBannerChange( $action, $user, $summary );

			$db->endAtomic( __METHOD__ );

			// Clear the dirty flags
			foreach ( $this->dirtyFlags as $flag => &$value ) {
				$value = false;
			}

			if ( $this->runTranslateJob ) {
				// Must be run after banner has finished saving due to some dependencies that
				// exist in the render job.
				// TODO: This will go away if we start tracking messages in database :)
				MessageGroups::singleton()->recache();
				MediaWikiServices::getInstance()->getJobQueueGroup()->push(
					MessageIndexRebuildJob::newJob()
				);
				$this->runTranslateJob = false;
			}

		} catch ( Exception $ex ) {
			$db->rollback( __METHOD__ );
			throw $ex;
		}

		return $this;
	}

	/**
	 * Called before saveBannerInternal() when a new to the database banner is
	 * being saved. Intended to create all table rows required such that any
	 * additional operation can be an UPDATE statement.
	 *
	 * @param IDatabase $db
	 */
	protected function initializeDbForNewBanner( IDatabase $db ) {
		$this->initializeDbBasicData( $db );
	}

	/**
	 * Helper function to save(). This is wrapped in a database transaction and
	 * is intended to be easy to override -- though overriding function should
	 * call this at some point. :)
	 *
	 * Because it is wrapped in a database transaction; most MediaWiki calls
	 * like page saving cannot be performed here.
	 *
	 * Dirty flags are not globally reset until after this function is called.
	 *
	 * @param IDatabase $db
	 *
	 * @throws BannerExistenceException
	 */
	protected function saveBannerInternal( IDatabase $db ) {
		$this->saveBasicData( $db );
		$this->saveDeviceTargetData( $db );
		$this->saveMixinData( $db );
		$this->savePriorityLanguageData();
	}

	/**
	 * Archive a banner.
	 *
	 * TODO: Remove data from translation, in place replace all templates
	 *
	 * @return $this
	 */
	public function archive() {
		if ( $this->dirtyFlags['basic'] === null ) {
			$this->populateBasicData();
		}
		$this->dirtyFlags['basic'] = true;

		$this->archived = true;

		return $this;
	}

	public function cloneBanner( $destination, $user, $summary = null ) {
		if ( !self::isValidBannerName( $destination ) ) {
			throw new BannerDataException( "Banner name must be in format /^[A-Za-z0-9_]+$/" );
		}

		$destBanner = self::newFromName( $destination );
		if ( $destBanner->exists() ) {
			throw new BannerExistenceException( "Banner by that name already exists!" );
		}

		$destBanner->setAllocation( $this->allocateToAnon(), $this->allocateToLoggedIn() );
		$destBanner->setCategory( $this->getCategory() );
		$destBanner->setDevices( $this->getDevices() );
		$destBanner->setMixins( array_keys( $this->getMixins() ) );
		$destBanner->setPriorityLanguages( $this->getPriorityLanguages() );

		$destBanner->setBodyContent( $this->getBodyContent() );

		// Populate the message fields
		$langs = $this->getAvailableLanguages();
		$fields = $this->extractMessageFields();
		foreach ( $langs as $lang ) {
			foreach ( $fields as $field => $count ) {
				$text = $this->getMessageField( $field )->getContents( $lang );
				if ( $text !== null ) {
					$destBanner->getMessageField( $field )
						->update( $text, $lang, $user, $summary );
				}
			}
		}

		// Save it!
		$destBanner->save( $user, $summary );
		$this->invalidateCache( $fields );

		return $destBanner;
	}

	public function remove( User $user ) {
		self::removeBanner( $this->getName(), $user );
	}

	public static function removeBanner( $name, $user, $summary = null ) {
		global $wgNoticeUseTranslateExtension;

		$bannerObj = self::fromName( $name );
		$id = $bannerObj->getId();
		$dbr = CNDatabase::getDb();
		$res = $dbr->select( 'cn_assignments', 'asn_id', [ 'tmp_id' => $id ], __METHOD__ );

		if ( $res->numRows() > 0 ) {
			throw new LogicException( 'Cannot remove a template still bound to a campaign!' );
		} else {
			// Log the removal of the banner
			// FIXME: this log line will display changes with inverted sense
			$bannerObj->logBannerChange( 'removed', $user, $summary );

			// Delete banner record from the CentralNotice cn_templates table
			$dbw = CNDatabase::getDb();
			$dbw->delete( 'cn_templates',
				[ 'tmp_id' => $id ],
				__METHOD__
			);

			// Delete the MediaWiki page that contains the banner source
			// TODO Inconsistency: deletion of banner content is not recorded
			// as a bot edit, so it does not appear on the CN logs page. Also,
			// related messages are not deleted.
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $bannerObj->getTitle() );
			$wikiPage->doDeleteArticleReal( $summary ?: '', $user );

			if ( $wgNoticeUseTranslateExtension ) {
				// Remove any revision tags related to the banner
				self::removeTag( self::TRANSLATE_BANNER_TAG, $wikiPage->getId() );

				// And the preferred language metadata if it exists
				TranslateMetadata::set(
					BannerMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					false
				);
			}
		}
	}

	/**
	 * Add a revision tag for the banner
	 * @param string $tag The name of the tag
	 * @param int $revisionId ID of the revision
	 * @param int $pageId ID of the MediaWiki page for the banner
	 * @param string $bannerId ID of banner this revtag belongs to
	 * @throws Exception
	 */
	public static function addTag( $tag, $revisionId, $pageId, $bannerId ) {
		$dbw = CNDatabase::getDb();

		if ( is_object( $revisionId ) ) {
			throw new LogicException( 'Got object, excepted id' );
		}

		// There should only ever be one tag applied to a banner object
		self::removeTag( $tag, $pageId );

		$conds = [
			'rt_page' => $pageId,
			'rt_type' => $tag,
			'rt_revision' => $revisionId
		];

		if ( $bannerId !== null ) {
			$conds['rt_value'] = $bannerId;
		}

		$dbw->insert( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Make sure banner is not tagged with specified tag
	 * @param string $tag The name of the tag
	 * @param int $pageId ID of the MediaWiki page for the banner
	 * @throws Exception
	 */
	protected static function removeTag( $tag, $pageId ) {
		$dbw = CNDatabase::getDb();

		$conds = [
			'rt_page' => $pageId,
			'rt_type' => $tag
		];
		$dbw->delete( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Given one or more campaign ids, return all banners bound to them
	 *
	 * @param int|array $campaigns list of campaign numeric IDs
	 *
	 * @return array a 2D array of banners with associated weights and settings
	 */
	public static function getCampaignBanners( $campaigns ) {
		$dbr = CNDatabase::getDb();

		$banners = [];

		if ( $campaigns ) {
			$res = $dbr->select(
				// Aliases (keys) are needed to avoid problems with table prefixes
				[
					'notices' => 'cn_notices',
					'templates' => 'cn_templates',
					'known_devices' => 'cn_known_devices',
					'template_devices' => 'cn_template_devices',
					'assignments' => 'cn_assignments',
				],
				[
					'tmp_name',
					'tmp_weight',
					'tmp_display_anon',
					'tmp_display_account',
					'tmp_category',
					'not_name',
					'not_preferred',
					'asn_bucket',
					'not_buckets',
					'not_throttle',
					'dev_name',
				],
				[
					'notices.not_id' => $campaigns,
					'notices.not_id = assignments.not_id',
					'known_devices.dev_id = template_devices.dev_id',
					'assignments.tmp_id = templates.tmp_id'
				],
				__METHOD__,
				[],
				[
					'template_devices' => [
						'LEFT JOIN', 'template_devices.tmp_id = assignments.tmp_id'
					]
				]
			);

			foreach ( $res as $row ) {
				$banners[] = [
					// name of the banner
					'name'             => $row->tmp_name,
					// weight assigned to the banner
					'weight'           => intval( $row->tmp_weight ),
					// display to anonymous users?
					'display_anon'     => intval( $row->tmp_display_anon ),
					// display to logged in users?
					'display_account'  => intval( $row->tmp_display_account ),
					// fundraising banner?
					'fundraising'      => intval( $row->tmp_category === 'fundraising' ),
					// device this banner can target
					'device'           => $row->dev_name,
					// campaign the banner is assigned to
					'campaign'         => $row->not_name,
					// z level of the campaign
					'campaign_z_index' => $row->not_preferred,
					'campaign_num_buckets' => intval( $row->not_buckets ),
					'campaign_throttle' => intval( $row->not_throttle ),
					'bucket'           => ( intval( $row->not_buckets ) == 1 )
						? 0 : intval( $row->asn_bucket ),
				];
			}
		}
		return $banners;
	}

	/**
	 * Return settings for a banner
	 *
	 * @param string $bannerName name of banner
	 * @param bool $detailed if true, get some more expensive info
	 *
	 * @return array an array of banner settings
	 * @throws RangeException
	 */
	public static function getBannerSettings( $bannerName, $detailed = true ) {
		$banner = self::fromName( $bannerName );
		if ( !$banner->exists() ) {
			throw new RangeException( "Banner doesn't exist!" );
		}

		$details = [
			'anon'             => (int)$banner->allocateToAnon(),
			'account'          => (int)$banner->allocateToLoggedIn(),
			// TODO: Death to this!
			'fundraising'      => (int)( $banner->getCategory() === 'fundraising' ),
			'category'         => $banner->getCategory(),
			'controller_mixin' => implode( ",", array_keys( $banner->getMixins() ) ),
			'devices'          => array_values( $banner->getDevices() ),
		];

		if ( $detailed ) {
			$details['prioritylangs'] = $banner->getPriorityLanguages();
		}

		return $details;
	}

	/**
	 * FIXME: a little thin, it's just enough to get the job done
	 *
	 * @param string $name
	 * @param int $ts
	 * @return array|null banner settings as an associative array, with these properties:
	 *    display_anon: 0/1 whether the banner is displayed to anonymous users
	 *    display_account: 0/1 same, for logged-in users
	 *    fundraising: 0/1, is in the fundraising group
	 *    device: device key
	 */
	public static function getHistoricalBanner( $name, $ts ) {
		$id = self::fromName( $name )->getId();

		$dbr = CNDatabase::getDb();
		$tsEnc = $dbr->addQuotes( $ts );

		$newestLog = $dbr->selectRow(
			"cn_template_log",
			[
				"log_id" => "MAX(tmplog_id)",
			],
			[
				"tmplog_timestamp <= $tsEnc",
				"tmplog_template_id = $id",
			],
			__METHOD__
		);

		if ( $newestLog->log_id === null ) {
			return null;
		}

		$row = $dbr->selectRow(
			"cn_template_log",
			[
				"display_anon" => "tmplog_end_anon",
				"display_account" => "tmplog_end_account",
				"fundraising" => "tmplog_end_fundraising",
			],
			[
				"tmplog_id" => $newestLog->log_id,
			],
			__METHOD__
		);

		return [
			'display_anon' => (int)$row->display_anon,
			'display_account' => (int)$row->display_account,
			'fundraising' => (int)$row->fundraising,
			'devices' => [ 'desktop' ],
		];
	}

	/**
	 * @param string $name
	 * @param User $user
	 * @param Banner $template
	 * @param string|null $summary
	 * @return string|null error message key or null on success
	 * @throws BannerDataException
	 * @throws BannerExistenceException
	 */
	public static function addFromBannerTemplate( $name, $user, Banner $template, $summary = null ) {
		if ( !$template->isTemplate() ) {
			return 'centralnotice-banner-template-error';
		}
		return static::addBanner(
			$name, $template->getBodyContent(), $user,
			$template->allocateToAnon(),
			$template->allocateToLoggedIn(),
			$template->getMixins(),
			$template->getPriorityLanguages(),
			$template->getDevices(),
			$summary,
			false,
			$template->getCategory()
		);
	}

	/**
	 * Create a new banner
	 *
	 * @param string $name name of banner
	 * @param string $body content of banner
	 * @param User $user User causing the change
	 * @param bool $displayAnon flag for display to anonymous users
	 * @param bool $displayAccount flag for display to logged in users
	 * @param array $mixins list of mixins (optional)
	 * @param array $priorityLangs Array of priority languages for the translate extension
	 * @param array|null $devices Array of device names this banner is targeted at
	 * @param string|null $summary Optional summary of changes for logging
	 * @param bool $isTemplate Is banner marked as a template
	 * @param string|null $category Category of the banner
	 *
	 * @return string|null error message key or null on success
	 * @throws BannerDataException
	 */
	public static function addBanner( $name, $body, $user, $displayAnon,
		$displayAccount, $mixins = [], $priorityLangs = [], $devices = null,
		$summary = null, $isTemplate = false, $category = null
	) {
		// Default initial value for devices
		if ( $devices === null ) {
			$devices = [ 'desktop' ];
		}

		// Set default value for category
		if ( !$category ) {
			$category = '{{{campaign}}}';
		}

		if ( $name == '' || !self::isValidBannerName( $name ) || $body == '' ) {
			return 'centralnotice-null-string';
		}

		$banner = self::newFromName( $name );
		if ( $banner->exists() ) {
			return 'centralnotice-template-exists';
		}

		$banner->setAllocation( $displayAnon, $displayAccount );
		$banner->setCategory( $category );
		$banner->setDevices( $devices );
		$banner->setPriorityLanguages( $priorityLangs );
		$banner->setBodyContent( $body );
		$banner->setIsTemplate( $isTemplate );

		$banner->setMixins( $mixins );

		$banner->save( $user, $summary );
		return null;
	}

	/**
	 * Log setting changes related to a banner
	 *
	 * @param string $action 'created', 'modified', or 'removed'
	 * @param User $user The user causing the change
	 * @param string|null $summary Summary (comment) for this action
	 */
	public function logBannerChange( $action, $user, $summary = null ) {
		ChoiceDataProvider::invalidateCache();

		// Summary shouldn't actually come in null, but just in case...
		if ( $summary === null ) {
			$summary = '';
		}

		$endSettings = [];
		if ( $action !== 'removed' ) {
			$endSettings = self::getBannerSettings( $this->getName(), true );
		}

		$dbw = CNDatabase::getDb();

		$log = [
			'tmplog_timestamp'     => $dbw->timestamp(),
			'tmplog_user_id'       => $user->getId(),
			'tmplog_action'        => $action,
			'tmplog_template_id'   => $this->getId(),
			'tmplog_template_name' => $this->getName(),
			'tmplog_content_change' => (int)$this->dirtyFlags['content'],
			'tmplog_comment'       => $summary,
		];

		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJson::encode( $value );
			}

			$log[ 'tmplog_end_' . $key ] = $value;
		}

		$dbw->insert( 'cn_template_log', $log, __METHOD__ );
	}

	/**
	 * Validation function for banner names. Will return true iff the name fits
	 * the generic format of letters, numbers, and dashes.
	 *
	 * @param string $name The name to check
	 *
	 * @return bool True if valid
	 */
	public static function isValidBannerName( $name ) {
		// Note: regex should coordinate with banner name validation
		// in ext.centralNotice.adminUi.bannerSequence.js
		return preg_match( '/^[A-Za-z0-9_]+$/', $name );
	}

	/**
	 * Check to see if a banner actually exists in the database
	 *
	 * @return bool
	 * @throws BannerDataException If it's a silly query
	 */
	public function exists() {
		$db = CNDatabase::getDb();
		if ( $this->name !== null ) {
			$selector = [ 'tmp_name' => $this->name ];
		} elseif ( $this->id !== null ) {
			$selector = [ 'tmp_id' => $this->id ];
		} else {
			throw new BannerDataException(
				'Cannot determine banner existence without name or ID.'
			);
		}
		$row = $db->selectRow( 'cn_templates', 'tmp_name', $selector, __METHOD__ );
		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get BannerMessage defined in a specific banner.
	 *
	 * @param string $bannerName
	 * @param string $messageName
	 * @return BannerMessage
	 */
	public static function getMessageFieldForBanner( $bannerName, $messageName ) {
		return new BannerMessage( $bannerName, $messageName );
	}

	/**
	 * Apply cascading protection to the banner template or message
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param bool $isTranslatedMessage if true, protect with $wgCentralNoticeMessageProtectRight
	 * @throws BannerContentException
	 */
	public static function protectBannerContent(
		WikiPage $wikiPage, User $user, $isTranslatedMessage = false
	) {
		if ( $isTranslatedMessage ) {
			global $wgCentralNoticeMessageProtectRight;
			if ( empty( $wgCentralNoticeMessageProtectRight ) ) {
				return;
			}
			$protectionRight = $wgCentralNoticeMessageProtectRight;
		} else {
			$protectionRight = 'centralnotice-admin';
		}

		$limits = [
			'edit' => $protectionRight,
			'move' => $protectionRight,
		];

		$expiry = [
			'edit' => 'infinity',
			'move' => 'infinity',
		];

		$cascade = true;
		$reason = wfMessage( 'centralnotice-banner-protection-log-reason' )
			->inContentLanguage()->text();

		$status = $wikiPage->doUpdateRestrictions(
			$limits, $expiry, $cascade, $reason, $user
		);
		if ( !$status->isGood() || !$cascade ) {
			throw new BannerContentException(
				'Unable to protect banner' . $status->getMessage()->text()
			);
		}
	}
}
