<?php

class Banner {
	var $name;
	var $id;

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
	static function extractMessageFields( $body ) {
		$expanded = MessageCache::singleton()->transform( $body );

		// Extract message fields from the banner body
		$fields = array();
		$allowedChars = Title::legalChars();
		preg_match_all( "/\{\{\{([$allowedChars]+)\}\}\}/u", $expanded, $fields );

		// Remove duplicate keys and count occurrences
		$unique_fields = array_unique( array_flip( $fields[1] ) );
		$fields = array_intersect_key( array_count_values( $fields[1] ), $unique_fields );

		// Remove magic words that don't need translation
		$fields = array_diff_key( $fields, array(
			'campaign' => 1,
			'banner' => 1,
		) );
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
	 * Render the banner as an html fieldset
	 *
	 * TODO js refresh, iframe
	 * live_target to other projects depending on ... something
	 */
	function previewFieldSet( IContextSource $context, $lang ) {
		$render = new SpecialBannerLoader();
		$render->siteName = 'Wikipedia'; //FIXME: translate?
		$render->language = $lang;

		try {
			$preview = $render->getHtmlNotice( $this->name );
		} catch ( SpecialBannerLoaderException $e ) {
			$preview = $context->msg( 'centralnotice-nopreview' )->text();
		}
		$label = $context->msg( 'centralnotice-preview' )->text();
		if ( $render->language ) {
			$label .= " ($render->language)";
			$live_target = "wikipedia:{$render->language}:Special:Random";
		} else {
			$live_target = "wikipedia:Special:Random";
		}
		$live_link = Linker::link(
			Title::newFromText( $live_target ),
			$context->msg( 'centralnotice-live-page' )->text(),
			array(),
			array( 'banner' => $this->name )
		);
		$htmlOut = Xml::fieldset(
			$label,
			$preview . '<br>' . $live_link,
			array( 'class' => 'cn-bannerpreview' )
		);
		return $htmlOut;
	}

	/**
	 * See if a given banner exists in the database
	 *
	 * @param $bannerName string
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
	 * @param $campaigns array of id numbers
	 * @param $logging   boolean whether or not request is for logging (optional)
	 *
	 * @return array a 2D array of banners with associated weights and settings
	 */
	static function getCampaignBanners( $campaigns, $logging = false ) {
		global $wgCentralDBname;

		// If logging, read from the master database to avoid concurrency problems
		if ( $logging ) {
			$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		} else {
			$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		}

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
	 * @param $logging    boolean whether or not request is for logging (optional)
	 *
	 * @return array an array of banner settings
	 */
	static function getBannerSettings( $bannerName, $logging = false ) {
		global $wgCentralDBname, $wgNoticeUseTranslateExtension;

		$banner = array();

		// If logging, read from the master database to avoid concurrency problems
		if ( $logging ) {
			$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		} else {
			$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		}

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
				'anon'         => $row->tmp_display_anon,
				'account'      => $row->tmp_display_account,
				'fundraising'  => $row->tmp_fundraising,
				'autolink'     => $row->tmp_autolink,
				'landingpages' => $row->tmp_landing_pages
			);

			if ( $wgNoticeUseTranslateExtension ) {
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
		}

		return $banner;
	}

	/**
	 * FIXME: a little thin, it's just enough to get the job done
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
		$banner['display_anon'] = intval( $row->display_anon );
		$banner['display_account'] = intval( $row->display_account );

		$banner['fundraising'] = intval( $row->fundraising );
		$banner['autolink'] = intval( $row->autolink );
		$banner['landing_pages'] = explode( ", ", $row->landing_pages );

		return $banner;
	}

	/**
	 * DEPRECATED, but included for backwards compatibility during upgrade
	 * Lookup function for active banners under a given language/project/location. This function is
	 * called by SpecialBannerListLoader::getJsonList() in order to build the banner list JSON for
	 * each project.
	 * @deprecated Remove me after upgrade has been completed.
	 * @param $project string
	 * @param $language string
	 * @param $location string
	 * @return array a 2D array of running banners with associated weights and settings
	 */
	static function getBannersByTarget( $project, $language, $location = null ) {
		global $wgCentralDBname;

		$campaigns = array();
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );

		// Pull non-geotargeted campaigns
		$campaignResults1 = $dbr->select(
			// Aliases are needed to avoid problems with table prefixes
			array(
				'notices' => 'cn_notices',
				'cn_notice_projects',
				'cn_notice_languages'
			),
			array(
				'not_id'
			),
			array(
				"not_start <= $encTimestamp",
				"not_end >= $encTimestamp",
				'not_enabled = 1', // enabled
				'not_geo = 0', // not geotargeted
				'np_notice_id = notices.not_id',
				'np_project' => $project,
				'nl_notice_id = notices.not_id',
				'nl_language' => $language
			),
			__METHOD__
		);
		foreach ( $campaignResults1 as $row ) {
			$campaigns[] = $row->not_id;
		}
		if ( $location ) {

			// Normalize location parameter (should be an uppercase 2-letter country code)
			preg_match( '/[a-zA-Z][a-zA-Z]/', $location, $matches );
			if ( $matches ) {
				$location = strtoupper( $matches[0] );

				// Pull geotargeted campaigns
				$campaignResults2 = $dbr->select(
					array(
						'cn_notices',
						'cn_notice_projects',
						'cn_notice_languages',
						'cn_notice_countries'
					),
					array(
						'not_id'
					),
					array(
						"not_start <= $encTimestamp",
						"not_end >= $encTimestamp",
						'not_enabled = 1', // enabled
						'not_geo = 1', // geotargeted
						'nc_notice_id = cn_notices.not_id',
						'nc_country' => $location,
						'np_notice_id = cn_notices.not_id',
						'np_project' => $project,
						'nl_notice_id = cn_notices.not_id',
						'nl_language' => $language
					),
					__METHOD__
				);
				foreach ( $campaignResults2 as $row ) {
					$campaigns[] = $row->not_id;
				}
			}
		}

		$banners = array();
		if ( $campaigns ) {
			// Pull all banners assigned to the campaigns
			$banners = Banner::getCampaignBanners( $campaigns );
		}
		return $banners;
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
	 * @param $priorityLangs    array Array of priority languages for the translate extension
	 *
	 * @return bool true or false depending on whether banner was successfully added
	 */
	static function addTemplate( $name, $body, $user, $displayAnon, $displayAccount, $fundraising = 0,
	                             $autolink = 0, $landingPages = '', $priorityLangs = array()
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

			// Perhaps these should move into the db as blobs instead of being stored as articles
			$wikiPage = new WikiPage(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);

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
				$fields = Banner::extractMessageFields( $body );
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
		if ( $action === 'modified' ) {
			// Only log if there are any differences in the settings
			$endSettings = Banner::getBannerSettings( $this->getName(), true );
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
}
