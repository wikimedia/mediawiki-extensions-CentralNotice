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
	/**
	 * Keys indicate a group of properties (which should be a 1-to-1 match to
	 * a database table.) If the value is null it means the data is not yet
	 * loaded. True means the data is clean and not modified. False means the
	 * data should be saved on the next call to save().
	 *
	 * Most functions should only ever set the flag to true; flags will be
	 * reset to false in save().
	 *
	 * @var null|bool[]
	 */
	protected $dirtyFlags = array(
		'content' => null,
		'messages' => null,
		'basic' => null,
		'devices' => null,
		'mixins' => null,
		'prioritylang' => null,
	);

	//<editor-fold desc="Properties">
	// !!! NOTE !!! It is not recommended to use directly. It is almost always more
	//              correct to use the accessor/setter function.

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

	/** @var string[] Devices this banner should be allocated to in the form {Device ID => Device header name} */
	protected $devices = array();

	/** @var string[] Names of enabled mixins  */
	protected $mixins = array();

	/** @var string[] Language codes considered a priority for translation.  */
	protected $priorityLanguages = array();

	/** @var string Wikitext content of the banner */
	protected $bodyContent = '';

	protected $runTranslateJob = false;
	//</editor-fold>

	//<editor-fold desc="Constructors">
	/**
	 * Create a banner object from a known ID. Must already be
	 * an object in the database. If a fully new banner is to be created
	 * use @see newFromName().
	 *
	 * @param int $id Unique database ID of the banner
	 *
	 * @return Banner
	 */
	public static function fromId( $id ) {
		$obj = new Banner();
		$obj->id = $id;
		return $obj;
	}

	/**
	 * Create a banner object from a known banner name. Must already be
	 * an object in the database. If a fully new banner is to be created
	 * use @see newFromName().
	 *
	 * @param $name
	 *
	 * @return Banner
	 * @throws BannerDataException
	 */
	public static function fromName( $name ) {
		if ( !Banner::isValidBannerName( $name ) ) {
			throw new BannerDataException( "Invalid banner name supplied." );
		}

		$obj = new Banner();
		$obj->name = $name;
		return $obj;
	}

	/**
	 * Create a brand new banner object.
	 *
	 * @param $name
	 *
	 * @return Banner
	 * @throws BannerDataException
	 */
	public static function newFromName( $name ) {
		if ( !Banner::isValidBannerName( $name ) ) {
			throw new BannerDataException( "Invalid banner name supplied." );
		}

		$obj = new Banner();
		$obj->name = $name;

		foreach ( $obj->dirtyFlags as $flag => &$value ) {
			$value = true;
		}

		return $obj;
	}
	//</editor-fold>

	//<editor-fold desc="Basic metadata getters/setters">
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
		$this->populateBasicData();
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
			array( 'DISTINCT', 'ORDER BY tmp_category ASC' )
		);

		$categories = array();
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
			$selector = array( 'tmp_name' => $this->name );
		} elseif ( $this->id !== null ) {
			$selector = array( 'tmp_id' => $this->id );
		} else {
			throw new BannerDataException( 'Cannot retrieve banner data without name or ID.' );
		}

		// Query!
		$rowRes = $db->select(
			array( 'templates' => 'cn_templates' ),
			array(
				 'tmp_id',
				 'tmp_name',
				 'tmp_display_anon',
				 'tmp_display_account',
				 'tmp_archived',
				 'tmp_category'
			),
			$selector,
			__METHOD__
		);

		// Extract the dataz!
		$row = $db->fetchObject( $rowRes );
		if ( $row ) {
			$this->id = (int)$row->tmp_id;
			$this->name = $row->tmp_name;
			$this->allocateAnon = (bool)$row->tmp_display_anon;
			$this->allocateLoggedIn = (bool)$row->tmp_display_account;
			$this->archived = (bool)$row->tmp_archived;
			$this->category = $row->tmp_category;
		} else {
			$keystr = array();
			foreach ( $selector as $key => $value ) {
				$keystr[] = "{$key} = {$value}";
			}
			$keystr = implode( " AND ", $keystr );
			throw new BannerExistenceException( "No banner exists where {$keystr}. Could not load." );
		}

		// Set the dirty flag to not dirty because we just loaded clean data
		$this->setBasicDataDirty( false );
	}

	/**
	 * Sets the flag which will save basic metadata on next save()
	 */
	protected function setBasicDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['basic'], $dirty, true );
	}

	/**
	 * Helper function to initializeDbForNewBanner()
	 *
	 * @param DatabaseBase $db
	 */
	protected function initializeDbBasicData( $db ) {
		$db->insert( 'cn_templates', array( 'tmp_name' => $this->name ), __METHOD__ );
		$this->id = $db->insertId();
	}

	/**
	 * Helper function to saveBannerInternal() for saving basic banner metadata
	 * @param DatabaseBase $db
	 */
	protected function saveBasicData( $db ) {
		if ( $this->dirtyFlags['basic'] ) {
			$db->update( 'cn_templates',
				array(
					 'tmp_display_anon'    => (int)$this->allocateAnon,
					 'tmp_display_account' => (int)$this->allocateLoggedIn,
					 'tmp_archived'        => $this->archived,
					 'tmp_category'        => $this->category,
				),
				array(
					 'tmp_id'              => $this->id
				),
				__METHOD__
			);
		}
	}
	//</editor-fold>

	//<editor-fold desc="Device targeting">
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
			$this->devices = array();

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
			array(
				 'tdev' => 'cn_template_devices',
				 'devices' => 'cn_known_devices'
			),
			array( 'devices.dev_id', 'dev_name' ),
			array(
				 'tdev.tmp_id' => $this->getId(),
				 'tdev.dev_id = devices.dev_id'
			),
			__METHOD__
		);

		foreach( $rowObj as $row ) {
			$this->devices[ intval( $row->dev_id ) ] = $row->dev_name;
		}

		$this->markDeviceTargetDataDirty( false );
	}

	/**
	 * Sets the flag which will force saving of device targeting data on next save()
	 */
	protected function markDeviceTargetDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['devices'], $dirty, true );
	}

	/**
	 * Helper function to saveBannerInternal()
	 *
	 * @param DatabaseBase $db
	 */
	protected function saveDeviceTargetData( $db ) {
		if ( $this->dirtyFlags['devices'] ) {
			// Remove all entries from the table for this banner
			$db->delete( 'cn_template_devices', array( 'tmp_id' => $this->getId() ), __METHOD__ );

			// Add the new device mappings
			if ( $this->devices ) {
				$modifyArray = array();
				foreach ( $this->devices as $deviceId => $deviceName ) {
					$modifyArray[] = array( 'tmp_id' => $this->getId(), 'dev_id' => $deviceId );
				}
				$db->insert( 'cn_template_devices', $modifyArray, __METHOD__ );
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc="Mixin management">
	/**
	 * @return array Keys are names of enabled mixins; valeus are mixin params.
	 * @see $wgCentralNoticeBannerMixins
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
	function setMixins( $mixins ) {
		global $wgCentralNoticeBannerMixins;

		$this->populateMixinData();

		$mixins = array_unique( $mixins );
		sort( $mixins );

		if ( $this->mixins != $mixins ) {
			$this->markMixinDataDirty();
		}

		$this->mixins = array();
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
			array(
				 "tmp_id" => $this->getId(),
			),
			__METHOD__
		);

		$this->mixins = array();
		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->mixin_name, $wgCentralNoticeBannerMixins ) ) {
				// We only want to warn here otherwise we'd never be able to
				// edit the banner to fix the issue! The editor should warn
				// when a deprecated mixin is being used; but also when we
				// do deprecate something we should make sure nothing is using
				// it!
				wfLogWarning( "Mixin does not exist: {$row->mixin_name}, included from banner {$this->name}" );
			}
			$this->mixins[$row->mixin_name] = $wgCentralNoticeBannerMixins[$row->mixin_name];
		}

		$this->markMixinDataDirty( false );
	}

	/**
	 * Sets the flag which will force saving of mixin data upon next save()
	 */
	protected function markMixinDataDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['mixins'], $dirty, true );
	}

	/**
	 * @param DatabaseBase $db
	 */
	protected function saveMixinData( $db ) {
		if ( $this->dirtyFlags['mixins'] ) {
			$db->delete( 'cn_template_mixins',
				array( 'tmp_id' => $this->getId() ),
				__METHOD__
			);

			foreach ( $this->mixins as $name => $params ) {
				$name = trim( $name );
				if ( !$name ) {
					continue;
				}
				$db->insert( 'cn_template_mixins',
					array(
						 'tmp_id' => $this->getId(),
						 'page_id' => 0,	// TODO: What were we going to use this for again?
						 'mixin_name' => $name,
					),
					__METHOD__
				);
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc="Priority languages">
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
		return (bool)wfSetVar( $this->dirtyFlags['prioritylang'], $dirty, true );
	}

	protected function savePriorityLanguageData() {
		global $wgNoticeUseTranslateExtension;

		if ( $wgNoticeUseTranslateExtension && $this->dirtyFlags['prioritylang'] ) {
			TranslateMetadata::set(
				BannerMessageGroup::getTranslateGroupName( $this->getName() ),
				'prioritylangs',
				implode( ',', $this->priorityLanguages )
			);
		}
	}
	//</editor-fold>

	//<editor-fold desc="Banner body content">
	public function getDbKey() {
		$name = $this->getName();
		return "Centralnotice-template-{$name}";
	}

	public function getTitle() {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	/**
	 * Returns an array of Title objects that have been included as templates
	 * in this banner.
	 *
	 * @return Array of Title
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

		$bodyPage = $this->getTitle();
		$curRev = Revision::newFromTitle( $bodyPage );
		if ( !$curRev ) {
			throw new BannerContentException( "No content for banner: {$this->name}" );
		}
		$this->bodyContent = ContentHandler::getContentText( $curRev->getContent() );

		$this->markBodyContentDirty( false );
	}

	protected function markBodyContentDirty( $dirty = true ) {
		return (bool)wfSetVar( $this->dirtyFlags['content'], $dirty, true );
	}

	protected function saveBodyContent( $summary = null ) {
		global $wgNoticeUseTranslateExtension;

		if ( $this->dirtyFlags['content'] ) {
			$wikiPage = new WikiPage( $this->getTitle() );

			if ( $summary === null ) {
				$summary = '';
			}

			$contentObj = ContentHandler::makeContent( $this->bodyContent, $wikiPage->getTitle() );

			$pageResult =
				$wikiPage->doEditContent( $contentObj, $summary, EDIT_FORCE_BOT );

			if ( $wgNoticeUseTranslateExtension ) {
				// Get the revision and page ID of the page that was created/modified
				if ( $pageResult->value['revision'] ) {
					$revision = $pageResult->value['revision'];
					$revisionId = $revision->getId();
					$pageId = $revision->getPage();

					// If the banner includes translatable messages, tag it for translation
					$fields = $this->extractMessageFields();
					if ( count( $fields ) > 0 ) {
						// Tag the banner for translation
						Banner::addTag( 'banner:translate', $revisionId, $pageId, $this->getId() );
						$this->runTranslateJob = true;
					}
				}
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc="Banner message fields">
	function getMessageField( $field_name ) {
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
		$data = ObjectCache::getMainStashInstance()
			->get( $this->getMessageFieldsCacheKey() );

		if ( $data !== false ) {
			$data = json_decode( $data, true );
		} else {
			$data = $this->extractMessageFields();
		}

		return $data;
	}

	protected function getMessageFieldsCacheKey() {
		return ObjectCache::getMainStashInstance()
			->makeKey( 'centralnotice', 'bannerfields', $this->getName() );
	}

	/**
	 * Extract the raw fields and field names from the banner body source.
	 *
	 * Always recalculate.  If you want the cached value, please use getMessageFieldsFromCache.
	 *
	 * @return array
	 */
	public function extractMessageFields() {
		global $wgParser;

		$expanded = $wgParser->parse(
			$this->getBodyContent(), $this->getTitle(),
			ParserOptions::newFromContext( RequestContext::getMain() )
		)->getText();

		// Also search the preload js for fields.
		$renderer = new BannerRenderer( RequestContext::getMain(), $this );
		$expanded .= $renderer->getPreloadJsRaw();

		// Extract message fields from the banner body
		$fields = array();
		$allowedChars = Title::legalChars();
		// We're using a janky custom syntax to pass arguments to a field message:
		// "{{{fieldname:arg1|arg2}}}"
		$allowedChars = str_replace( ':', '', $allowedChars );
		preg_match_all( "/{{{([$allowedChars]+)(:[^}]*)?}}}/u", $expanded, $fields );

		// Remove duplicate keys and count occurrences
		$unique_fields = array_unique( array_flip( $fields[1] ) );
		$fields = array_intersect_key( array_count_values( $fields[1] ), $unique_fields );

		$fields = array_diff_key( $fields, array_flip( $renderer->getMagicWords() ) );

		// Save in the cache.
		ObjectCache::getMainStashInstance()
			->set( $this->getMessageFieldsCacheKey(), json_encode( $fields ) );

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
	function getAvailableLanguages( $inTranslation = false ) {
		global $wgLanguageCode;
		$availableLangs = array();

		// Bit of an ugly hack to get just the banner prefix
		$prefix = $this->getMessageField( '' )->getDbKey( null, $inTranslation ? NS_CN_BANNER : NS_MEDIAWIKI );

		$db = CNDatabase::getDb();
		$result = $db->select( 'page',
			'page_title',
			array(
				 'page_namespace' => $inTranslation ? NS_CN_BANNER : NS_MEDIAWIKI,
				 'page_title' . $db->buildLike( $prefix, $db->anyString() ),
			),
			__METHOD__
		);
		while ( $row = $result->fetchRow() ) {
			if ( preg_match( "/\Q{$prefix}\E([^\/]+)(?:\/([a-z_]+))?/", $row['page_title'], $matches ) ) {
				$field = $matches[1];
				if ( isset( $matches[2] ) ) {
					$lang = $matches[2];
				} else {
					$lang = $wgLanguageCode;
				}
				$availableLangs[$lang] = true;
			}
		}
		return array_keys( $availableLangs );
	}
	//</editor-fold>

	//<editor-fold desc="Banner actions">
	//<editor-fold desc="Saving">
	/**
	 * Saves any changes made to the banner object into the database
	 *
	 * @param User $user
	 * @param string $summary Summary (comment) to associate with all changes,
	 *   including banner content and messages (which are implemented as wiki
	 *   pages).
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function save( $user = null, $summary = null ) {
		global $wgUser;

		$db = CNDatabase::getDb();

		$action = 'modified';
		if ( $user === null ) {
			$user = $wgUser;
		}

		try {
			// Don't move this to saveBannerInternal--can't be in a transaction
			$this->saveBodyContent( $summary );

			// Open a transaction so that everything is consistent
			$db->begin( __METHOD__ );

			if ( !$this->exists() ) {
				$action = 'created';
				$this->initializeDbForNewBanner( $db );
			}
			$this->saveBannerInternal( $db );
			$this->logBannerChange( $action, $user, array(), $summary );

			$db->commit( __METHOD__ );

			// Clear the dirty flags
			foreach ( $this->dirtyFlags as $flag => &$value ) { $value = false; }

			if ( $this->runTranslateJob ) {
				// Must be run after banner has finished saving due to some dependencies that
				// exist in the render job.
				// TODO: This will go away if we start tracking messages in database :)
				MessageGroups::clearCache();
				MessageIndexRebuildJob::newJob()->run();
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
	 * @param DatabaseBase $db
	 */
	protected function initializeDbForNewBanner( $db ) {
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
	 * @param DatabaseBase $db
	 *
	 * @throws BannerExistenceException
	 */
	protected function saveBannerInternal( $db ) {
		$this->saveBasicData( $db );
		$this->saveDeviceTargetData( $db );
		$this->saveMixinData( $db );
		$this->savePriorityLanguageData();
	}
	//</editor-fold>

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
		if ( !$this->isValidBannerName( $destination ) ) {
			throw new BannerDataException( "Banner name must be in format /^[A-Za-z0-9_]+$/" );
		}

		$destBanner = Banner::newFromName( $destination );
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
		return $destBanner;
	}

	public function remove( $user = null ) {
		global $wgUser;
		if ( $user === null ) {
			$user = $wgUser;
		}
		Banner::removeTemplate( $this->getName(), $user );
	}

	static function removeTemplate( $name, $user, $summary = null ) {
		global $wgNoticeUseTranslateExtension;

		$bannerObj = Banner::fromName( $name );
		$id = $bannerObj->getId();
		$dbr = CNDatabase::getDb();
		$res = $dbr->select( 'cn_assignments', 'asn_id', array( 'tmp_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			throw new LogicException( 'Cannot remove a template still bound to a campaign!' );
		} else {
			// Log the removal of the banner
			// FIXME: this log line will display changes with inverted sense
			$bannerObj->logBannerChange( 'removed', $user, array(), $summary );

			// Delete banner record from the CentralNotice cn_templates table
			$dbw = CNDatabase::getDb();
			$dbw->delete( 'cn_templates',
				array( 'tmp_id' => $id ),
				__METHOD__
			);

			// Delete the MediaWiki page that contains the banner source
			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$pageId = $article->getPage()->getId();

			// TODO Inconsistency: deletion of banner content is not recorded
			// as a bot edit, so it does not appear on the CN logs page. Also,
			// related messages are not deleted.
			$article->doDeleteArticle( $summary ? $summary : '' );

			if ( $wgNoticeUseTranslateExtension ) {
				// Remove any revision tags related to the banner
				Banner::removeTag( 'banner:translate', $pageId );

				// And the preferred language metadata if it exists
				TranslateMetadata::set(
					BannerMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					false
				);
			}
		}
	}
	//</editor-fold>

	//<editor-fold desc=" Random stuff that still needs to die a hideous horrible death">
	/**
	 * Add a revision tag for the banner
	 * @param string $tag The name of the tag
	 * @param integer $revisionId ID of the revision
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @param string $bannerId ID of banner this revtag belongs to
	 * @throws Exception
	 */
	static function addTag( $tag, $revisionId, $pageId, $bannerId ) {
		$dbw = CNDatabase::getDb();

		if ( is_object( $revisionId ) ) {
			throw new LogicException( 'Got object, excepted id' );
		}

		// There should only ever be one tag applied to a banner object
		Banner::removeTag( $tag, $pageId );

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag ),
			'rt_revision' => $revisionId
		);

		if ( $bannerId !== null ) {
			$conds['rt_value'] = $bannerId;
		}

		$dbw->insert( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Make sure banner is not tagged with specified tag
	 * @param string $tag The name of the tag
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @throws Exception
	 */
	static protected function removeTag( $tag, $pageId ) {
		$dbw = CNDatabase::getDb();

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag )
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Given one or more campaign ids, return all banners bound to them
	 *
	 * @param integer|array $campaigns list of campaign numeric IDs
	 *
	 * @return array a 2D array of banners with associated weights and settings
	 */
	static function getCampaignBanners( $campaigns ) {
		$dbr = CNDatabase::getDb();

		$banners = array();

		if ( $campaigns ) {
			$res = $dbr->select(
				// Aliases (keys) are needed to avoid problems with table prefixes
				array(
					'notices' => 'cn_notices',
					'templates' => 'cn_templates',
					'known_devices' => 'cn_known_devices',
					'template_devices' => 'cn_template_devices',
					'assignments' => 'cn_assignments',
				),
				array(
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
				),
				array(
					'notices.not_id' => $campaigns,
					'notices.not_id = assignments.not_id',
					'known_devices.dev_id = template_devices.dev_id',
					'assignments.tmp_id = templates.tmp_id'
				),
				__METHOD__,
				array(),
				array(
					 'template_devices' => array(
						 'LEFT JOIN', 'template_devices.tmp_id = assignments.tmp_id'
					 )
				)
			);

			foreach ( $res as $row ) {
				$banners[ ] = array(
					'name'             => $row->tmp_name, // name of the banner
					'weight'           => intval( $row->tmp_weight ), // weight assigned to the banner
					'display_anon'     => intval( $row->tmp_display_anon ), // display to anonymous users?
					'display_account'  => intval( $row->tmp_display_account ), // display to logged in users?
					'fundraising'      => intval( $row->tmp_category === 'fundraising' ), // fundraising banner?
					'device'           => $row->dev_name, // device this banner can target
					'campaign'         => $row->not_name, // campaign the banner is assigned to
					'campaign_z_index' => $row->not_preferred, // z level of the campaign
					'campaign_num_buckets' => intval( $row->not_buckets ),
					'campaign_throttle' => intval( $row->not_throttle ),
					'bucket'           => ( intval( $row->not_buckets ) == 1 ) ? 0 : intval( $row->asn_bucket ),
				);
			}
		}
		return $banners;
	}

	/**
	 * Return settings for a banner
	 *
	 * @param $bannerName string name of banner
	 * @param $detailed boolean if true, get some more expensive info
	 *
	 * @return array an array of banner settings
	 * @throws RangeException
	 */
	static function getBannerSettings( $bannerName, $detailed = true ) {
		$banner = Banner::fromName( $bannerName );
		if ( !$banner->exists() ) {
			throw new RangeException( "Banner doesn't exist!" );
		}

		$details = array(
			'anon'             => (int)$banner->allocateToAnon(),
			'account'          => (int)$banner->allocateToLoggedIn(),
			'fundraising'      => (int)($banner->getCategory() === 'fundraising'), // TODO: Death to this!
			'category'         => $banner->getCategory(),
			'controller_mixin' => implode( ",", array_keys( $banner->getMixins() ) ),
			'devices'          => array_values( $banner->getDevices() ),
		);

		if ( $detailed ) {
			$details['prioritylangs'] = $banner->getPriorityLanguages();
		}

		return $details;
	}

	/**
	 * FIXME: a little thin, it's just enough to get the job done
	 *
	 * @return array|null banner settings as an associative array, with these properties:
	 *    display_anon: 0/1 whether the banner is displayed to anonymous users
	 *    display_account: 0/1 same, for logged-in users
	 *    fundraising: 0/1, is in the fundraising group
	 *    device: device key
	 */
	static function getHistoricalBanner( $name, $ts ) {
		$id = Banner::fromName( $name )->getId();

		$dbr = CNDatabase::getDb();

		$newestLog = $dbr->selectRow(
			"cn_template_log",
			array(
				"log_id" => "MAX(tmplog_id)",
			),
			array(
				"tmplog_timestamp <= $ts",
				"tmplog_template_id = $id",
			),
			__METHOD__
		);

		if ( $newestLog->log_id === null ) {
			return null;
		}

		$row = $dbr->selectRow(
			"cn_template_log",
			array(
				"display_anon" => "tmplog_end_anon",
				"display_account" => "tmplog_end_account",
				"fundraising" => "tmplog_end_fundraising",
			),
			array(
				"tmplog_id = {$newestLog->log_id}",
			),
			__METHOD__
		);
		$banner['display_anon'] = (int) $row->display_anon;
		$banner['display_account'] = (int) $row->display_account;

		$banner['fundraising'] = (int) $row->fundraising;

		//XXX
		$banner['devices'] = array( "desktop" );
		return $banner;
	}

	/**
	 * Create a new banner
	 *
	 * @param $name             string name of banner
	 * @param $body             string content of banner
	 * @param $user             User causing the change
	 * @param $displayAnon      integer flag for display to anonymous users
	 * @param $displayAccount   integer flag for display to logged in users
	 * @param $fundraising      integer flag for fundraising banner (optional)
	 * @param $mixins           array list of mixins (optional)
	 * @param $priorityLangs    array Array of priority languages for the translate extension
	 * @param $devices          array Array of device names this banner is targeted at
	 *
	 * @return bool true or false depending on whether banner was successfully added
	 */
	static function addTemplate( $name, $body, $user, $displayAnon,
		$displayAccount, $fundraising = 0,
		$mixins = array(), $priorityLangs = array(), $devices = null,
		$summary = null
	) {

		// Default initial value for devices
		if ( $devices === null ) {
			$devices = array( 'desktop' );
		}

		if ( $name == '' || !Banner::isValidBannerName( $name ) || $body == '' ) {
			return 'centralnotice-null-string';
		}

		$banner = Banner::newFromName( $name );
		if ( $banner->exists() ) {
			return 'centralnotice-template-exists';
		}

		$banner->setAllocation( $displayAnon, $displayAccount );
		$banner->setCategory( ( $fundraising == 1 ) ? 'fundraising' : '{{{campaign}}}' );
		$banner->setDevices( $devices );
		$banner->setPriorityLanguages( $priorityLangs );
		$banner->setBodyContent( $body );

		$banner->setMixins( $mixins );

		$banner->save( $user, $summary );
	}

	/**
	 * Log setting changes related to a banner
	 *
	 * @param string $action         'created', 'modified', or 'removed'
	 * @param User   $user           The user causing the change
	 * @param array  $beginSettings  Banner settings before changes (optional)
	 * @param string $summary        Summary (comment) for this action
	 */
	function logBannerChange(
		$action, $user, $beginSettings = array(), $summary = null ) {

		$endSettings = array();
		if ( $action !== 'removed' ) {
			$endSettings = Banner::getBannerSettings( $this->getName(), true );
		}

		$dbw = CNDatabase::getDb();

		$log = array(
			'tmplog_timestamp'     => $dbw->timestamp(),
			'tmplog_user_id'       => $user->getId(),
			'tmplog_action'        => $action,
			'tmplog_template_id'   => $this->getId(),
			'tmplog_template_name' => $this->getName(),
			'tmplog_content_change'=> (int)$this->dirtyFlags['content'],
		);

		// TODO temporary code for soft dependency on schema change
		// Note: MySQL-specific
		global $wgDBtype;
		if ( $wgDBtype === 'mysql' && $dbw->query(
				'SHOW COLUMNS FROM ' .
				$dbw->tableName( 'cn_template_log' )
				. ' LIKE ' . $dbw->addQuotes( 'tmplog_comment' )
			)->numRows() === 1 ) {

			$log['tmplog_comment'] = $summary;
		}

		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJSON::encode( $value );
			}

			$log[ 'tmplog_end_' . $key ] = $value;
		}

		$dbw->insert( 'cn_template_log', $log );
	}
	//</editor-fold>

	/**
	 * Validation function for banner names. Will return true iff the name fits
	 * the generic format of letters, numbers, and dashes.
	 *
	 * @param string $name The name to check
	 *
	 * @return bool True if valid
	 */
	static function isValidBannerName( $name ) {
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
			$selector = array( 'tmp_name' => $this->name );
		} elseif ( $this->id !== null ) {
			$selector = array( 'tmp_id' => $this->id );
		} else {
			throw new BannerDataException( 'Cannot determine banner existence without name or ID.' );
		}
		$row = $db->selectRow( 'cn_templates', 'tmp_name', $selector );
		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}
}

class BannerDataException extends Exception {}
class BannerContentException extends BannerDataException {}
class BannerExistenceException extends BannerDataException {}
