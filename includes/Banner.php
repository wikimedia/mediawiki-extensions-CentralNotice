<?php

class Banner {
	protected $name;
	protected $id;

	function __construct( $name ) {
		$this->name = $name;
	}

	function getName() {
		return $this->name;
	}

	function getDbKey() {
		return "Centralnotice-template-{$this->name}";
	}

	function getTitle() {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	function getContent() {
		$bodyPage = $this->getTitle();
		$curRev = Revision::newFromTitle( $bodyPage );
		if ( !$curRev ) {
			throw new MWException( "No content for banner: {$this->name}" );
		}
		return $curRev->getText();
	}

	/**
	 * Returns an array of Title objects that have been included as templates
	 * in this banner.
	 *
	 * @return Array of Title
	 */
	function getIncludedTemplates() {
		return $this->getTitle()->getTemplateLinksFrom();
	}

	function getId() {
		if ( !$this->id ) {
			$this->id = Banner::getTemplateId( $this->name );
		}
		return $this->id;
	}

	function getMessageField( $field_name ) {
		return new BannerMessage( $this->name, $field_name );
	}

	/**
	 * Extract the raw fields and field names from the banner body source.
	 * @param string $body The unparsed body source of the banner
	 * @return array
	 */
	function extractMessageFields( $body = null ) {
		global $wgOut;

		if ( $body === null ) {
			$body = $this->getContent();
		}
		$expanded = $wgOut->parse( $body );

		// Also search the preload js for fields.
		$renderer = new BannerRenderer( RequestContext::getMain(), $this );
		$expanded .= $renderer->getPreloadJs();

		/* FIXME: haven't decided if this is a terrrrible idea.
		// And search magic word output
		foreach ( $mixedin->getMagicWords() as $magic ) {
			unset( $fields[$magic] );
			$expanded .= $mixedin->renderMagicWord( $magic );
		}
		*/

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

		return $fields;
	}

	function remove() {
		global $wgUser;
		Banner::removeTemplate( $this->name, $wgUser );
	}

	static function removeTemplate( $name, $user ) {
		global $wgNoticeUseTranslateExtension, $wgCentralDBname;

		$bannerObj = new Banner( $name );
		$id = $bannerObj->getId();
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$res = $dbr->select( 'cn_assignments', 'asn_id', array( 'tmp_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			throw new MWException( 'Cannot remove a template still bound to a campaign!' );
		} else {
			// Log the removal of the banner
			// FIXME: this log line will display changes with inverted sense
			$bannerObj->logBannerChange( 'removed', $user );

			// Delete banner record from the CentralNotice cn_templates table
			$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
			$dbw->begin();
			$dbw->delete( 'cn_templates',
				array( 'tmp_id' => $id ),
				__METHOD__
			);
			$dbw->commit();

			// Delete the MediaWiki page that contains the banner source
			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$pageId = $article->getPage()->getId();
			$article->doDeleteArticle( 'CentralNotice automated removal' );

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

	/**
	 * Add a revision tag for the banner
	 * @param string $tag The name of the tag
	 * @param integer $revisionId ID of the revision
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @param string $value Value to store for the tag
	 * @throws MWException
	 */
	static function addTag( $tag, $revisionId, $pageId, $value = null ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		if ( is_object( $revisionId ) ) {
			throw new MWException( 'Got object, excepted id' );
		}

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag ),
			'rt_revision' => $revisionId
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );

		if ( $value !== null ) {
			$conds['rt_value'] = serialize( implode( '|', $value ) );
		}

		$dbw->insert( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Make sure banner is not tagged with specified tag
	 * @param string $tag The name of the tag
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @throws MWException
	 */
	static protected function removeTag( $tag, $pageId ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag )
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * See if a given banner exists in the database
	 *
	 * @param string $bannerName
	 *
	 * @return bool
	 */
	static function bannerExists( $bannerName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$eBannerName = htmlspecialchars( $bannerName );
		$row = $dbr->selectRow( 'cn_templates', 'tmp_name', array( 'tmp_name' => $eBannerName ) );
		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Given one or more campaign ids, return all banners bound to them
	 *
	 * @param array $campaigns list of campaign numeric IDs
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
					'tmp_fundraising',
					'tmp_autolink',
					'tmp_landing_pages',
					'not_name',
					'not_preferred',
					'asn_bucket',
					'not_buckets',
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
					'fundraising'      => intval( $row->tmp_fundraising ), // fundraising banner?
					'autolink'         => intval( $row->tmp_autolink ), // automatically create links?
					'landing_pages'    => $row->tmp_landing_pages, // landing pages to link to
					'device'           => $row->dev_name, // device this banner can target
					'campaign'         => $row->not_name, // campaign the banner is assigned to
					'campaign_z_index' => $row->not_preferred, // z level of the campaign
					'campaign_num_buckets' => intval( $row->not_buckets ),
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
	 */
	static function getBannerSettings( $bannerName, $detailed = true ) {
		global $wgNoticeUseTranslateExtension;

		$banner = array();

		$dbr = CNDatabase::getDb();

		$row = $dbr->selectRow(
			'cn_templates',
			array(
				'tmp_display_anon',
				'tmp_display_account',
				'tmp_fundraising',
				'tmp_autolink',
				'tmp_landing_pages'
			),
			array( 'tmp_name' => $bannerName ),
			__METHOD__
		);

		if ( $row ) {
			$banner = array(
				'anon'         => (int)$row->tmp_display_anon,
				'account'      => (int)$row->tmp_display_account,
				'fundraising'  => (int)$row->tmp_fundraising,
				'autolink'     => (int)$row->tmp_autolink,
				'landingpages' => $row->tmp_landing_pages,
				//TODO: 'landingpages' => explode( ", ", $row->tmp_landing_pages ),
			);

			$bannerObj = new Banner( $bannerName );
			$banner['controller_mixin'] = implode( ",", array_keys( $bannerObj->getMixins() ) );

			if ( $wgNoticeUseTranslateExtension && $detailed ) {
				$langs = TranslateMetadata::get(
					BannerMessageGroup::getTranslateGroupName( $bannerName ),
					'prioritylangs'
				);
				if ( !$langs ) {
					// If priority langs is not set; TranslateMetadata::get will return false
					$langs = '';
				}
				$banner['prioritylangs'] = explode( ',', $langs );
			}
		} else {
			throw new MWException( "Banner doesn't exist!" );
		}

		return $banner;
	}

	/**
	 * FIXME: a little thin, it's just enough to get the job done
	 *
	 * @return banner settings as an associative array, with these properties:
	 *    display_anon: 0/1 whether the banner is displayed to anonymous users
	 *    display_account: 0/1 same, for logged-in users
	 *    fundraising: 0/1, is in the fundraising group
	 *    autolink: make landing page autolink?
	 *    landing_pages: array of landing page names
	 *    device: device key
	 */
	static function getHistoricalBanner( $name, $ts ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$id = Banner::getTemplateId( $name );

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

		$row = $dbr->selectRow(
			"cn_template_log",
			array(
				"display_anon" => "tmplog_end_anon",
				"display_account" => "tmplog_end_account",
				"fundraising" => "tmplog_end_fundraising",
				"autolink" => "tmplog_end_autolink",
				"landing_pages" => "tmplog_end_landingpages",
			),
			array(
				"tmplog_id = {$newestLog->log_id}",
			),
			__METHOD__
		);
		$banner['display_anon'] = (int) $row->display_anon;
		$banner['display_account'] = (int) $row->display_account;

		$banner['fundraising'] = (int) $row->fundraising;
		$banner['autolink'] = (int) $row->autolink;
		$banner['landing_pages'] = explode( ", ", $row->landing_pages );

		//XXX
		$banner['device'] = "desktop";
		return $banner;
	}

	static function getTemplateId( $templateName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$templateName = htmlspecialchars( $templateName );
		$res = $dbr->select(
			'cn_templates',
			'tmp_id',
			array( 'tmp_name' => $templateName ),
			__METHOD__
		);
		$row = $dbr->fetchObject( $res );
		if ( $row ) {
			return $row->tmp_id;
		}
		return null;
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
	 * @param $autolink         integer flag for automatically creating landing page links (optional)
	 * @param $landingPages     string list of landing pages (optional)
	 * @param $mixins           string list of mixins (optional)
	 * @param $priorityLangs    array Array of priority languages for the translate extension
	 *
	 * @return bool true or false depending on whether banner was successfully added
	 */
	static function addTemplate( $name, $body, $user, $displayAnon, $displayAccount, $fundraising = 0,
	                             $autolink = 0, $landingPages = '', $mixins = '', $priorityLangs = array()
	) {
		if ( $body == '' || $name == '' ) {
			return 'centralnotice-null-string';
		}

		// Format name so there are only letters, numbers, and underscores
		$name = preg_replace( '/[^A-Za-z0-9_]/', '', $name );

		$db = CNDatabase::getDb();
		$res = $db->select(
			'cn_templates',
			'tmp_name',
			array( 'tmp_name' => $name ),
			__METHOD__
		);

		if ( $db->numRows( $res ) > 0 ) {
			return 'centralnotice-template-exists';
		} else {
			// Insert the banner record
			$db->insert( 'cn_templates',
				array(
					'tmp_name'            => $name,
					'tmp_display_anon'    => $displayAnon,
					'tmp_display_account' => $displayAccount,
					'tmp_fundraising'     => $fundraising,
					'tmp_autolink'        => $autolink,
					'tmp_landing_pages'   => $landingPages
				),
				__METHOD__
			);
			$bannerObj = new Banner( $name );
			$bannerObj->id = $db->insertId();

			$bannerObj->setMixins( explode( ",", $mixins ) );

			// TODO: Add the attached devices (yes this is a hack until the UI supports it)
			$res = $db->select(
				array( 'known_devices' => 'cn_known_devices' ),
				'dev_id',
				array( 'dev_name' => 'desktop' ),
				__METHOD__
			);
			$desktop_id = $db->fetchRow( $res );
			$desktop_id = $desktop_id[ 'dev_id' ];

			$db->insert(
				'cn_template_devices',
				array(
					 'tmp_id' => $bannerObj->id,
					 'dev_id' => $desktop_id
				),
				__METHOD__
			);

			$wikiPage = new WikiPage( $bannerObj->getTitle() );

			if ( class_exists( 'ContentHandler' ) ) {
				// MediaWiki 1.21+
				$content = ContentHandler::makeContent( $body, $wikiPage->getTitle() );
				$pageResult = $wikiPage->doEditContent( $content, '/* CN admin */', EDIT_FORCE_BOT );
			} else {
				$pageResult = $wikiPage->doEdit( $body, '/* CN admin */', EDIT_FORCE_BOT );
			}

			Banner::updateTranslationMetadata( $pageResult, $name, $body, $priorityLangs );

			// Log the creation of the banner
			$bannerObj->logBannerChange( 'created', $user );
		}
	}

	/**
	 * Updates any metadata required for banner/translation extension integration.
	 *
	 * @param array  $pageResult        Return from WikiPage->doEditContent()
	 * @param string $name              Raw name of banner
	 * @param string $body              Body text of banner
	 * @param array  $priorityLangs     Languages to emphasize during translation
	 */
	static function updateTranslationMetadata( $pageResult, $name, $body, $priorityLangs ) {
		global $wgNoticeUseTranslateExtension;

		// Do nothing if we arent actually using translate
		if ( $wgNoticeUseTranslateExtension ) {
			// Get the revision and page ID of the page that was created/modified
			if ( $pageResult->value['revision'] ) {
				$revision = $pageResult->value['revision'];
				$revisionId = $revision->getId();
				$pageId = $revision->getPage();

				// If the banner includes translatable messages, tag it for translation
				$banner = new Banner( $name );
				$fields = $banner->extractMessageFields( $body );
				if ( count( $fields ) > 0 ) {
					// Tag the banner for translation
					Banner::addTag( 'banner:translate', $revisionId, $pageId );
					MessageGroups::clearCache();
					MessageIndexRebuildJob::newJob()->run();
				}
			}

			// Set the priority languages
			if ( $wgNoticeUseTranslateExtension && $priorityLangs ) {
				TranslateMetadata::set(
					BannerMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					implode( ',', $priorityLangs )
				);
			}
		}
	}

	/**
	 * Log setting changes related to a banner
	 *
	 * @param $action        string: 'created', 'modified', or 'removed'
	 * @param $user          User causing the change
	 * @param $beginSettings array of banner settings before changes (optional)
	 */
	function logBannerChange( $action, $user, $beginSettings = array() ) {
		$endSettings = array();
		if ( $action !== 'removed' ) {
			$endSettings = Banner::getBannerSettings( $this->getName(), true );
		}
		if ( $action === 'modified' ) {
			// Only log if there are any differences in the settings
			$changed = false;
			foreach ( $endSettings as $key => $value ) {
				if ( $endSettings[$key] != $beginSettings[$key] ) {
					$changed = true;
				}
			}
			if ( !$changed ) {
				return;
			}
		}

		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$log = array(
			'tmplog_timestamp'     => $dbw->timestamp(),
			'tmplog_user_id'       => $user->getId(),
			'tmplog_action'        => $action,
			'tmplog_template_id'   => $this->getId(),
			'tmplog_template_name' => $this->getName(),
		);

		foreach ( $beginSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJson::encode( $value );
			}

			$log[ 'tmplog_begin_' . $key ] = $value;
		}
		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJSON::encode( $value );
			}

			$log[ 'tmplog_end_' . $key ] = $value;
		}

		$dbw->insert( 'cn_template_log', $log );
	}

	/**
	 * @return array All mixin structs associated with this banner.
	 */
	function getMixins() {
		global $wgNoticeMixins;

		$dbr = CNDatabase::getDb();

		$result = $dbr->select( 'cn_template_mixins', 'mixin_name',
			array(
				"tmp_id" => $this->getId(),
			),
			__METHOD__
		);

		$mixins = array();
		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->mixin_name, $wgNoticeMixins ) ) {
				throw new MWException( "Mixin does not exist: {$row->mixin_name}, included from banner {$this->name}" );
			}
			$mixins[$row->mixin_name] = $wgNoticeMixins[$row->mixin_name];
		}
		return $mixins;
	}

	/**
	 * @param string[] list of mixins to associate with this banner.  Clears any
	 *     existing associations.
	 */
	function setMixins( $mixins ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$dbw->delete( 'cn_template_mixins',
			array( 'tmp_id' => $this->getId() ),
			__METHOD__
		);

		foreach ( $mixins as $name ) {
			$name = trim( $name );
			if ( !$name ) {
				continue;
			}
			$dbw->insert( 'cn_template_mixins',
				array(
					'tmp_id' => $this->getId(),
					'mixin_name' => $name,
				),
				__METHOD__
			);
		}
	}

	/**
	 * Copy all the data from one banner to another
	 */
	static function cloneTemplate( $source, $dest, $user ) {
		// Normalize name
		$dest = preg_replace( '/[^A-Za-z0-9_]/', '', $dest );

		if ( Banner::bannerExists( $dest ) ) {
			throw new MWException( "Banner by that name already exists!" );
		}

		$sourceBanner = new Banner( $source );

		$settings = Banner::getBannerSettings( $source, false );
		$template_body = $sourceBanner->getContent();

		// Create new banner
		$errors = Banner::addTemplate( $dest, $template_body, $user,
			$settings['anon'],
			$settings['account'],
			$settings['fundraising'],
			$settings['autolink'],
			$settings['landingpages'],
			$settings['controller_mixin']
		);
		if ( !$errors ) {
			$destBanner = new Banner( $dest );

			// Populate the fields
			$langs = $sourceBanner->getAvailableLanguages();
			$fields = $sourceBanner->extractMessageFields();
			foreach ( $langs as $lang ) {
				foreach ( $fields as $field => $count ) {
					$text = $sourceBanner->getMessageField( $field )->getContents( $lang );
					if ( $text !== null ) {
						$destBanner->getMessageField( $field )->update( $text, $lang, $user );
					}
				}
			}

			return $dest;
		} else {
			//FIXME: throw errors
		}
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

	/**
	 * Update a banner
	 */
	function editTemplate( $user, $body, $displayAnon, $displayAccount, $fundraising,
	                               $autolink, $landingPages, $mixins, $priorityLangs
	) {
		global $wgCentralDBname;
		if ( !Banner::bannerExists( $this->name ) ) {
			return;
		}
		$initialBannerSettings = Banner::getBannerSettings( $this->name, true );

		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->update( 'cn_templates',
			array(
				'tmp_display_anon'    => $displayAnon,
				'tmp_display_account' => $displayAccount,
				'tmp_fundraising'     => $fundraising,
				'tmp_autolink'        => $autolink,
				'tmp_landing_pages'   => $landingPages
			),
			array( 'tmp_name' => $this->name )
		);

		// Perhaps these should move into the db as blob
		$wikiPage = new WikiPage( $this->getTitle() );

		if ( class_exists( 'ContentHandler' ) ) {
			// MediaWiki 1.21+
			$content = ContentHandler::makeContent( $body, $wikiPage->getTitle() );
			$pageResult = $wikiPage->doEditContent( $content, '', EDIT_FORCE_BOT );
		} else {
			$pageResult = $wikiPage->doEdit( $body, '', EDIT_FORCE_BOT );
		}

		$this->setMixins( explode( ",", $mixins ) );

		Banner::updateTranslationMetadata( $pageResult, $this->name, $body, $priorityLangs );

		$this->logBannerChange( 'modified', $user, $initialBannerSettings );
	}

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
}
