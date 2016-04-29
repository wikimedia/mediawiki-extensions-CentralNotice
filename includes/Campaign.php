<?php

use Doctrine\Instantiator\Exception\InvalidArgumentException;

class Campaign {

	protected $id = null;
	protected $name = null;

	/** @var MWTimestamp Start datetime of campaign  */
	protected $start = null;

	/** @var MWTimestamp End datetime of campaign */
	protected $end = null;

	/** @var int Priority level of the campaign, higher is more important */
	protected $priority = null;

	/** @var bool True if the campaign is enabled for showing */
	protected $enabled = null;

	/** @var bool True if the campaign is currently non editable  */
	protected $locked = null;

	/** @var bool True if the campaign has been moved to the archive */
	protected $archived = null;

	/** @var bool True if there is geo-targeting data for ths campaign */
	protected $geotargeted = null;

	/** @var int The number of buckets in this campaign */
	protected $buckets = null;

	/**
	 * Construct a lazily loaded CentralNotice campaign object
	 *
	 * @param string|int $campaignIdentifier Either an ID or name for the campaign
	 */
	public function __construct( $campaignIdentifier ) {
		if ( is_int( $campaignIdentifier ) ) {
			$this->id = $campaignIdentifier;
		} else {
			$this->name = $campaignIdentifier;
		}
	}

	/**
	 * Get the unique numerical ID for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getId() {
		if ( $this->id === null ) {
			$this->loadBasicSettings();
		}

		return $this->id;
	}

	/**
	 * Get the unique name for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return string
	 */
	public function getName() {
		if ( $this->name === null ) {
			$this->loadBasicSettings();
		}

		return $this->name;
	}

	/**
	 * Get the start time for the campaign. Only applicable if the campaign is enabled.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return MWTimestamp
	 */
	public function getStartTime() {
		if ( $this->start === null ) {
			$this->loadBasicSettings();
		}

		return $this->start;
	}

	/**
	 * Get the end time for the campaign. Only applicable if the campaign is enabled.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return MWTimestamp
	 */
	public function getEndTime() {
		if ( $this->end === null ) {
			$this->loadBasicSettings();
		}

		return $this->end;
	}

	/**
	 * Get the priority level for this campaign. The larger this is the higher the priority is.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getPriority() {
		if ( $this->priority === null ) {
			$this->loadBasicSettings();
		}

		return $this->priority;
	}

	/**
	 * Returns the enabled/disabled status of the campaign.
	 *
	 * If a campaign is enabled it is eligible to be shown to users.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isEnabled() {
		if ( $this->enabled === null ) {
			$this->loadBasicSettings();
		}

		return $this->enabled;
	}

	/**
	 * Returns the locked/unlocked status of the campaign. A locked campaign is not able to be
	 * edited until unlocked.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isLocked() {
		if ( $this->locked === null ) {
			$this->loadBasicSettings();
		}

		return $this->locked;
	}

	/**
	 * Returns the archival status of the campaign. An archived campaign is not allowed to be
	 * edited.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isArchived() {
		if ( $this->archived === null ) {
			$this->loadBasicSettings();
		}

		return $this->archived;
	}

	/**
	 * Returned the geotargeted status of this campaign. Will be true if GeoIP information should
	 * be used to determine user eligibility.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isGeotargeted() {
		if ( $this->geotargeted === null ) {
			$this->loadBasicSettings();
		}

		return $this->geotargeted;
	}

	/**
	 * Get the number of buckets in this campaign.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getBuckets() {
		if ( $this->buckets === null ) {
			$this->loadBasicSettings();
		}

		return $this->buckets;
	}

	/**
	 * Load basic campaign settings from the database table cn_notices
	 *
	 * @throws CampaignExistenceException If the campaign doesn't exist
	 */
	protected function loadBasicSettings() {
		$db = CNDatabase::getDb();

		// What selector are we using?
		if ( $this->id !== null ) {
			$selector = array( 'not_id' => $this->id );
		} elseif ( $this->name !== null ) {
			$selector = array( 'not_name' => $this->name );
		} else {
			throw new CampaignExistenceException( "No valid database key available for campaign." );
		}

		// Get campaign info from database
		$row = $db->selectRow(
			array('notices' => 'cn_notices'),
			array(
				 'not_id',
				 'not_name',
				 'not_start',
				 'not_end',
				 'not_enabled',
				 'not_preferred',
				 'not_locked',
				 'not_archived',
				 'not_geo',
				 'not_buckets',
				 'not_throttle',
			),
			$selector,
			__METHOD__
		);
		if ( $row ) {
			$this->id = $row->not_id;
			$this->name = $row->not_name;
			$this->start = new MWTimestamp( $row->not_start );
			$this->end = new MWTimestamp( $row->not_end );
			$this->enabled = (bool)$row->not_enabled;
			$this->priority = (int)$row->not_preferred;
			$this->locked = (bool)$row->not_locked;
			$this->archived = (bool)$row->not_archived;
			$this->geotargeted = (bool)$row->not_geo;
			$this->buckets = (int)$row->not_buckets;
			$this->throttle = (int)$row->not_throttle;
		} else {
			throw new CampaignExistenceException(
				"Campaign could not be retrieved from database with id '{$this->id}' or name '{$this->name}'"
			);
		}
	}

	/**
	 * See if a given campaign exists in the database
	 *
	 * @param $campaignName string
	 *
	 * @return bool
	 */
	static function campaignExists( $campaignName ) {
		$dbr = CNDatabase::getDb();

		$eCampaignName = htmlspecialchars( $campaignName );
		return (bool)$dbr->selectRow( 'cn_notices', 'not_name', array( 'not_name' => $eCampaignName ) );
	}

	/**
	 * Returns a list of campaigns. May be filtered on optional constraints.
	 * By default returns only enabled and active campaigns in all projects, languages and
	 * countries.
	 *
	 * @param null|string $project  The name of the project, ie: 'wikipedia'; if null select all
	 *                              projects.
	 * @param null|string $language ISO language code, if null select all languages
	 * @param null|string $location ISO country code, if null select all campaigns
	 * @param null|date   $date     Campaigns must start before and end after this date
	 *                              If the parameter is null, it takes the current date/time
	 * @param bool        $enabled  If true, select only active campaigns. If false select all.
	 * @param bool        $archived If true: only archived; false: only active; null; all.
	 *
	 * @return array Array of campaign IDs that matched the filter.
	 */
	static function getCampaigns( $project = null, $language = null, $location = null, $date = null,
	                              $enabled = true, $archived = false ) {
		$notices = array();

		// Database setup
		$dbr = CNDatabase::getDb();

		// We will perform two queries (one geo-targeted, the other not) to
		// catch all notices. We do it bifurcated because otherwise the query
		// would be really funky (if even possible) to pass to the underlying
		// DB layer.

		// Therefore... construct the common components : cn_notices
		if ( $date ) {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp( $date ) );
		} else {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		}

		$tables = array( 'notices' => 'cn_notices' );
		$conds = array(
			"not_start <= $encTimestamp",
			"not_end >= $encTimestamp",
		);

		if ( $enabled ) {
			$conds[ 'not_enabled' ] = 1;
		}

		if ( $archived === true ) {
			$conds[ 'not_archived' ] = 1;
		} elseif ( $archived === false ) {
			$conds[ 'not_archived' ] = 0;
		}

		// common components: cn_notice_projects
		if ( $project ) {
			$tables[ 'notice_projects' ] = 'cn_notice_projects';

			$conds[ ] = 'np_notice_id = notices.not_id';
			$conds[ 'np_project' ] = $project;
		}

		// common components: language
		if ( $language ) {
			$tables[ 'notice_languages' ] = 'cn_notice_languages';

			$conds[ ] = 'nl_notice_id = notices.not_id';
			$conds[ 'nl_language' ] = $language;
		}

		if ( $location ) {
			$conds['not_geo'] = 0;
		}

		// Pull the notice IDs of the non geotargeted campaigns
		$res = $dbr->select(
			$tables,
			'not_id',
			$conds,
			__METHOD__
		);
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_id;
		}

		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables[ 'notice_countries' ] = 'cn_notice_countries';

			$conds[ ] = 'nc_notice_id = notices.not_id';
			$conds[ 'nc_country' ] = $location;
			$conds[ 'not_geo' ] = 1;

			// Pull the notice IDs
			$res = $dbr->select(
				$tables,
				'not_id',
				$conds,
				__METHOD__
			);

			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$notices[ ] = $row->not_id;
			}
		}

		return $notices;
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param string $campaignName The name of the campaign
	 *
	 * @return array|bool an array of settings or false if the campaign does not exist
	 */
	static function getCampaignSettings( $campaignName ) {
		$dbr = CNDatabase::getDb();

		// Get campaign info from database
		$row = $dbr->selectRow(
			array('notices' => 'cn_notices'),
			array(
				'not_id',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked',
				'not_archived',
				'not_geo',
				'not_buckets',
				'not_throttle',
			),
			array( 'not_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				'start'     => $row->not_start,
				'end'       => $row->not_end,
				'enabled'   => $row->not_enabled,
				'preferred' => $row->not_preferred,
				'locked'    => $row->not_locked,
				'archived'  => $row->not_archived,
				'geo'       => $row->not_geo,
				'buckets'   => $row->not_buckets,
				'throttle'  => $row->not_throttle,
			);
		} else {
			return false;
		}

		$projects = Campaign::getNoticeProjects( $campaignName );
		$languages = Campaign::getNoticeLanguages( $campaignName );
		$geo_countries = Campaign::getNoticeCountries( $campaignName );
		$campaign[ 'projects' ] = implode( ", ", $projects );
		$campaign[ 'languages' ] = implode( ", ", $languages );
		$campaign[ 'countries' ] = implode( ", ", $geo_countries );

		$bannersIn = Banner::getCampaignBanners( $row->not_id );
		$bannersOut = array();
		// All we want are the banner names, weights, and buckets
		foreach ( $bannersIn as $key => $row ) {
			$outKey = $bannersIn[ $key ][ 'name' ];
			$bannersOut[ $outKey ]['weight'] = $bannersIn[ $key ][ 'weight' ];
			$bannersOut[ $outKey ]['bucket'] = $bannersIn[ $key ][ 'bucket' ];
		}
		// Encode into a JSON string for storage
		$campaign[ 'banners' ] = FormatJson::encode( $bannersOut );
		$campaign[ 'mixins' ] =
			FormatJson::encode( Campaign::getCampaignMixins( $campaignName, true ) );

		return $campaign;
	}

	/**
	 * Get all campaign configurations as of timestamp $ts
	 *
	 * @return array of settings structs having the following properties:
	 *     id
	 *     name
	 *     enabled
	 *     projects: array of sister project names
	 *     languages: array of language codes
	 *     countries: array of country codes
	 *     preferred: campaign priority
	 *     geo: is geolocated?
	 *     buckets: number of buckets
	 *     banners: array of banner objects, as returned by getHistoricalBanner,
	 *       plus the following information from the parent campaign:
	 *         campaign: name of the campaign
	 *         campaign_z_index
	 *         campaign_num_buckets
	 *         campaign_throttle
	 */
	static function getHistoricalCampaigns( $ts ) {
		$dbr = CNDatabase::getDb();
		$res = $dbr->select(
			"cn_notice_log",
			array(
				"log_id" => "MAX(notlog_id)",
			),
			array(
				"notlog_timestamp <= $ts",
			),
			__METHOD__,
			array(
				"GROUP BY" => "notlog_not_id",
			)
		);

		$campaigns = array();
		foreach ( $res as $row ) {
			$singleRes = $dbr->select(
				"cn_notice_log",
				array(
					"id" => "notlog_not_id",
					"name" => "notlog_not_name",
					"enabled" => "notlog_end_enabled",
					"projects" => "notlog_end_projects",
					"languages" => "notlog_end_languages",
					"countries" => "notlog_end_countries",
					"preferred" => "notlog_end_preferred",
					"geotargeted" => "notlog_end_geo",
					"banners" => "notlog_end_banners",
					"bucket_count" => "notlog_end_buckets",
					"throttle" => "notlog_end_throttle",
				),
				array(
					"notlog_id = {$row->log_id}",
					"notlog_end_start <= $ts",
					"notlog_end_end >= $ts",
					"notlog_end_enabled = 1",
				),
				__METHOD__
			);

			$campaign = $singleRes->fetchRow();
			if ( !$campaign ) {
				continue;
			}
			$campaign['projects'] = explode( ", ", $campaign['projects'] );
			$campaign['languages'] = explode( ", ", $campaign['languages'] );
			$campaign['countries'] = explode( ", ", $campaign['countries'] );
			if ( $campaign['banners'] === null ) {
				$campaign['banners'] = array();
			} else {
				$campaign['banners'] = FormatJson::decode( $campaign['banners'], true );
				if ( !is_array( current( $campaign['banners'] ) ) ) {
					// Old log format; only had weight
					foreach( $campaign['banners'] as $key => &$value ) {
						$value = array(
							'weight' => $value,
							'bucket' => 0
						);
					}
				}
			}
			if ( $campaign['bucket_count'] === null ) {
				// Fix for legacy logs before bucketing
				$campaign['bucket_count'] = 1;
			}
			foreach ( $campaign['banners'] as $name => &$banner ) {
				$historical_banner = Banner::getHistoricalBanner( $name, $ts );

				if ( $historical_banner === null ) {
					//FIXME: crazy hacks
					$historical_banner = Banner::getBannerSettings( $name );
					$historical_banner['label'] = wfMessage( 'centralnotice-damaged-log', $name );
					$historical_banner['display_anon'] = $historical_banner['anon'];
					$historical_banner['display_account'] = $historical_banner['account'];
					$historical_banner['devices'] = array( 'desktop' );
				}
				$banner['name'] = $name;
				$banner['label'] = $name;

				$campaign_info = array(
					'campaign' => $campaign['name'],
					'campaign_z_index' => $campaign['preferred'],
					'campaign_num_buckets' => $campaign['bucket_count'],
					'campaign_throttle' => $campaign['throttle'],
				);

				$banner = array_merge( $banner, $campaign_info, $historical_banner );
			}

			$campaigns[] = $campaign;
		}
		return $campaigns;
	}

	/**
	 * Retrieve campaign mixins settings for this campaign.
	 *
	 * If $compact is true, retrieve only enabled mixins, and return a compact
	 * data structure in which keys are mixin names and values are parameter
	 * settings.
	 *
	 * If $compact is false, mixins that were once enabled for this campaign but
	 * are now disabled will be included, showing their last parameter settings.
	 * The data structure will be an array whose keys are mixin names and whose
	 * values are arrays with 'enabled' and 'parameters' keys. Note that mixins
	 * that were never enabled for this campaign will be omitted.
	 *
	 * @param string $campaignName
	 * @param boolean $compact
	 * @return array
	 */
	public static function getCampaignMixins( $campaignName, $compact = false ) {

		global $wgCentralNoticeCampaignMixins;

		$dbr = CNDatabase::getDb();

		// Prepare query conditions
		$conds = array( 'notices.not_name' => $campaignName );
		if ( $compact ) {
			$conds['notice_mixins.nmxn_enabled'] = 1;
		}

		$dbRows = $dbr->select(
			array(
				'notices' => 'cn_notices',
				'notice_mixins' => 'cn_notice_mixins',
				'notice_mixin_params' => 'cn_notice_mixin_params'
			),
			array(
				'notice_mixins.nmxn_mixin_name',
				'notice_mixins.nmxn_enabled',
				'notice_mixin_params.nmxnp_param_name',
				'notice_mixin_params.nmxnp_param_value'
			),
			$conds,
			__METHOD__,
			array(),
			array(
				'notice_mixins' => array(
					'INNER JOIN', 'notices.not_id = notice_mixins.nmxn_not_id'
				),
				'notice_mixin_params' => array(
					'LEFT OUTER JOIN', 'notice_mixins.nmxn_id = notice_mixin_params.nmxnp_notice_mixin_id'
				)
			)
		);

		// Build up the results
		// We expect a row for every parameter name-value pair for every mixin,
		// and maybe some with null name-value pairs (for mixins with no
		// parameters).
		$campaignMixins = array();
		foreach ( $dbRows as $dbRow ) {

			$mixinName = $dbRow->nmxn_mixin_name;

			// A mixin may have been removed from the code but may still
			// leave stuff in the database. In that case, skip it!
			if ( !isset( $wgCentralNoticeCampaignMixins[$mixinName] ) ) {
				continue;
			}

			// First time we have a result row for this mixin?
			if ( !isset( $campaignMixins[$mixinName] ) ) {

				// Data structure depends on $compact
				if ( $compact ) {
					$campaignMixins[$mixinName] = array();

				} else {
					$campaignMixins[$mixinName] = array(
						'enabled' => (bool) $dbRow->nmxn_enabled,
						'parameters' => array()
					);
				}
			}

			// If there are mixin params in this row, add them in
			if ( !is_null( $dbRow->nmxnp_param_name ) ) {

				$paramName = $dbRow->nmxnp_param_name;
				$mixinDef = $wgCentralNoticeCampaignMixins[$mixinName];

				// Handle mixin parameters being removed, too
				if ( !isset( $mixinDef['parameters'][$paramName] ) ) {
					continue;
				}

				$paramType = $mixinDef['parameters'][$paramName]['type'];

				switch ( $paramType ) {
					case 'string':
						$paramVal = $dbRow->nmxnp_param_value;
						break;

					case 'integer':
						$paramVal = intval( $dbRow->nmxnp_param_value );
						break;

					case 'float':
						$paramVal = floatval( $dbRow->nmxnp_param_value );
						break;

					case 'boolean':
						$paramVal = ( $dbRow->nmxnp_param_value === 'true' );
						break;

					default:
						throw new DomainException(
							'Unknown parameter type ' . $paramType );
				}

				// Again, data structure depends on $compact
				if ( $compact )  {
					$campaignMixins[$mixinName][$paramName] = $paramVal;
				} else {
					$campaignMixins[$mixinName]['parameters'][$paramName]
						= $paramVal;
				}
			}
		}

		// Ensure consistent ordering, since it's needed for
		// CNChoiceDataResourceLoaderModule (which gets this data via
		// ChoiceDataProvider) for consistent RL module hashes.

		array_walk( $campaignMixins, function ( &$campaignMixin ) {
			ksort( $campaignMixin );
		} );

		ksort( $campaignMixins );

		return $campaignMixins;
	}

	/**
	 * Update enabled or disabled status and parameters for a campaign mixin,
	 * for a given campaign.
	 *
	 * @param string $campaignName
	 * @param string $mixinName
	 * @param boolean $enable
	 * @param array $params For mixins with no parameters, set to an empty array.
	 */
	public static function updateCampaignMixins(
		$campaignName, $mixinName, $enable, $params = null ) {

		global $wgCentralNoticeCampaignMixins;

		// TODO Error handling!

		$dbw = CNDatabase::getDb( DB_MASTER );

		// Get the campaign ID
		// Note: the need to fetch the ID here highlights the need for some
		// kind of ORM.
		$noticeId = $dbw->selectRow( 'cn_notices', 'not_id',
			array( 'not_name' => $campaignName ) )->not_id;

		if ( $enable ) {

			if ( $params === null ) {
				throw new InvalidArgumentException( 'Paremeters info required to enable mixin ' .
					$mixinName . ' for campaign '. $campaignName );
			}

			$dbw->upsert(
				'cn_notice_mixins',
				array(
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName,
					'nmxn_enabled' => 1
				),
				array( 'nmxn_not_id', 'nmxn_mixin_name' ),
				array(
					'nmxn_enabled' => 1
				)
			);

			$noticeMixinId = $dbw->selectRow(
				'cn_notice_mixins',
				'nmxn_id',
				array(
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName
				)
			)->nmxn_id;

			foreach ( $params as $paramName => $paramVal ) {

				$mixinDef = $wgCentralNoticeCampaignMixins[$mixinName];

				// Handle an undefined parameter. Not likely to happen, maybe
				// in the middle of a deploy that removes a parameter.
				if ( !isset( $mixinDef['parameters'][$paramName] ) ) {

					wfLogWarning( 'No definition found for the parameter '
						. $paramName . ' for the campaign mixn ' .
						$mixinName . '.' );

					continue;
				}

				// Munge boolean params for database storage. (Other types
				// should end up as strings, which will be fine.)
				if ( $mixinDef['parameters'][$paramName]['type'] === 'boolean' ) {
					$paramVal = ( $paramVal ? 'true' : 'false' );
				}

				$dbw->upsert(
					'cn_notice_mixin_params',
					array(
						'nmxnp_notice_mixin_id' => $noticeMixinId,
						'nmxnp_param_name' => $paramName,
						'nmxnp_param_value' => $paramVal
					),
					array( 'nmxnp_notice_mixin_id', 'nmxnp_param_name' ),
					array(
						'nmxnp_param_value' => $paramVal
					)
				);
			}

		} else {

			// When we disable a mixin, just set enabled to false; since we keep
			// the old parameter values in case the mixin is re-enabled, we also
			// keep the row in this table, since the id is used in the param
			// table.
			$dbw->update(
				'cn_notice_mixins',
				array( 'nmxn_enabled' => 0 ),
				array(
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName
				)
			);
		}
	}

	/**
	 * Get all the campaigns in the database
	 *
	 * @return array an array of campaign names
	 */
	static function getAllCampaignNames() {
		$dbr = CNDatabase::getDb();
		$res = $dbr->select( 'cn_notices', 'not_name', null, __METHOD__ );
		$notices = array();
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_name;
		}
		return $notices;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param $noticeName        string: Name of the campaign
	 * @param $enabled           int: Boolean setting, 0 or 1
	 * @param $startTs           string: Campaign start in UTC
	 * @param $projects          array: Targeted project types (wikipedia, wikibooks, etc.)
	 * @param $project_languages array: Targeted project languages (en, de, etc.)
	 * @param $geotargeted       int: Boolean setting, 0 or 1
	 * @param $geo_countries     array: Targeted countries
	 * @param $throttle          int: limit allocations, 0 - 100
	 * @param $priority          int: priority level, LOW_PRIORITY - EMERGENCY_PRIORITY
	 * @param $user              User adding the campaign
	 * @param $summary           string: Change summary provided by the user
	 *
	 * @throws RuntimeException
	 * @return int|string noticeId on success, or message key for error
	 */
	static function addCampaign( $noticeName, $enabled, $startTs, $projects,
		$project_languages, $geotargeted, $geo_countries, $throttle, $priority,
		$user, $summary = null
	) {
		$noticeName = trim( $noticeName );
		if ( Campaign::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-exists';
		} elseif ( empty( $projects ) ) {
			return 'centralnotice-no-project';
		} elseif ( empty( $project_languages ) ) {
			return 'centralnotice-no-language';
		}

		if ( !$geo_countries ) {
			$geo_countries = array();
		}

		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		$endTime = strtotime( '+1 hour', wfTimestamp( TS_UNIX, $startTs ) );
		$endTs = wfTimestamp( TS_MW, $endTime );

		$dbw->insert( 'cn_notices',
			array( 'not_name'    => $noticeName,
				'not_enabled' => $enabled,
				'not_start'   => $dbw->timestamp( $startTs ),
				'not_end'     => $dbw->timestamp( $endTs ),
				'not_geo'     => $geotargeted,
				'not_throttle' => $throttle,
				'not_preferred' => $priority,
			)
		);
		$not_id = $dbw->insertId();

		if ( $not_id ) {
			// Do multi-row insert for campaign projects
			$insertArray = array();
			foreach ( $projects as $project ) {
				$insertArray[ ] = array( 'np_notice_id' => $not_id, 'np_project' => $project );
			}
			$dbw->insert( 'cn_notice_projects', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			// Do multi-row insert for campaign languages
			$insertArray = array();
			foreach ( $project_languages as $code ) {
				$insertArray[ ] = array( 'nl_notice_id' => $not_id, 'nl_language' => $code );
			}
			$dbw->insert( 'cn_notice_languages', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			if ( $geotargeted ) {
				// Do multi-row insert for campaign countries
				$insertArray = array();
				foreach ( $geo_countries as $code ) {
					$insertArray[ ] = array( 'nc_notice_id' => $not_id, 'nc_country' => $code );
				}
				$dbw->insert( 'cn_notice_countries', $insertArray,
					__METHOD__, array( 'IGNORE' ) );
			}

			$dbw->endAtomic( __METHOD__ );

			// Log the creation of the campaign
			$beginSettings = array();
			$endSettings = array(
				'projects'  => implode( ", ", $projects ),
				'languages' => implode( ", ", $project_languages ),
				'countries' => implode( ", ", $geo_countries ),
				'start'     => $dbw->timestamp( $startTs ),
				'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => $enabled,
				'preferred' => 0,
				'locked'    => 0,
				'geo'       => $geotargeted,
				'throttle'  => $throttle,
			);
			Campaign::logCampaignChange( 'created', $not_id, $user,
				$beginSettings, $endSettings, array(), array(), $summary );

			return $not_id;
		}

		throw new RuntimeException( 'insertId() did not return a value.' );
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param $campaignName string: Name of the campaign
	 * @param $user User removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	static function removeCampaign( $campaignName, $user ) {
		// TODO This method is never used?
		$dbr = CNDatabase::getDb( DB_MASTER );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			array( 'not_name' => $campaignName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			return 'centralnotice-remove-notice-doesnt-exist';
		}
		$row = $dbr->fetchObject( $res );
		if ( $row->not_locked == '1' ) {
			return 'centralnotice-notice-is-locked';
		}

		Campaign::removeCampaignByName( $campaignName, $user );

		return true;
	}

	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = Campaign::getNoticeId( $campaignName );
		Campaign::logCampaignChange( 'removed', $campaignId, $user );

		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'cn_assignments', array( 'not_id' => $campaignId ) );
		$dbw->delete( 'cn_notices', array( 'not_name' => $campaignName ) );
		$dbw->delete( 'cn_notice_languages', array( 'nl_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_projects', array( 'np_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_countries', array( 'nc_notice_id' => $campaignId ) );
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Assign a banner to a campaign at a certain weight
	 * @param $noticeName string
	 * @param $templateName string
	 * @param $weight integer
	 * @param $bucket integer
	 * @return bool|string True on success, string with message key for error
	 */
	static function addTemplateTo( $noticeName, $templateName, $weight, $bucket = 0 ) {
		$dbw = CNDatabase::getDb( DB_MASTER );

		$eNoticeName = htmlspecialchars( $noticeName );
		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$res = $dbw->select( 'cn_assignments', 'asn_id',
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);

		if ( $dbw->numRows( $res ) > 0 ) {
			return 'centralnotice-template-already-exists';
		}

		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$dbw->insert( 'cn_assignments',
			array(
				'tmp_id'     => $templateId,
				'tmp_weight' => $weight,
				'not_id'     => $noticeId,
				'asn_bucket' => $bucket,
			)
		);

		return true;
	}

	/**
	 * Remove a banner assignment from a campaign
	 */
	static function removeTemplateFor( $noticeName, $templateName ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$noticeId = Campaign::getNoticeId( $noticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$dbw->delete( 'cn_assignments', array( 'tmp_id' => $templateId, 'not_id' => $noticeId ) );
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	static function getNoticeId( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		if ( $row ) {
			return $row->not_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 */
	static function getNoticeName( $noticeId ) {
		$dbr = CNDatabase::getDb();
		if ( is_numeric( $noticeId ) ) {
			$row = $dbr->selectRow( 'cn_notices', 'not_name', array( 'not_id' => $noticeId ) );
			if ( $row ) {
				return $row->not_name;
			}
		}
		return null;
	}

	static function getNoticeProjects( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$projects = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_projects', 'np_project',
				array( 'np_notice_id' => $row->not_id ) );
			foreach ( $res as $projectRow ) {
				$projects[ ] = $projectRow->np_project;
			}
		}
		sort( $projects );
		return $projects;
	}

	static function getNoticeLanguages( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$languages = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_languages', 'nl_language',
				array( 'nl_notice_id' => $row->not_id ) );
			foreach ( $res as $langRow ) {
				$languages[ ] = $langRow->nl_language;
			}
		}
		sort( $languages );
		return $languages;
	}

	static function getNoticeCountries( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$countries = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_countries', 'nc_country',
				array( 'nc_notice_id' => $row->not_id ) );
			foreach ( $res as $countryRow ) {
				$countries[ ] = $countryRow->nc_country;
			}
		}
		sort( $countries );
		return $countries;
	}

	/**
	 * @param $noticeName string
	 * @param $start string Date
	 * @param $end string Date
	 * @return bool|string True on success, string with message key for error
	 */
	static function updateNoticeDate( $noticeName, $start, $end ) {
		$dbw = CNDatabase::getDb( DB_MASTER );

		// Start/end don't line up
		if ( $start > $end || $end < $start ) {
			return 'centralnotice-invalid-date-range';
		}

		// Invalid campaign name
		if ( !Campaign::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-doesnt-exist';
		}

		// Overlap over a date within the same project and language
		$startDate = $dbw->timestamp( $start );
		$endDate = $dbw->timestamp( $end );

		$dbw->update( 'cn_notices',
			array(
				'not_start' => $startDate,
				'not_end'   => $endDate
			),
			array( 'not_name' => $noticeName )
		);

		return true;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param $noticeName string: Name of the campaign
	 * @param $settingName string: Name of a boolean setting (enabled, locked, or geo)
	 * @param $settingValue int: Value to use for the setting, 0 or 1
	 */
	static function setBooleanCampaignSetting( $noticeName, $settingName, $settingValue ) {
		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = CNDatabase::getDb( DB_MASTER );
			$dbw->update( 'cn_notices',
				array( 'not_' . $settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates a numeric setting on a campaign
	 *
	 * @param string $noticeName Name of the campaign
	 * @param string $settingName Name of a numeric setting (preferred)
	 * @param int $settingValue Value to use
	 * @param int $max The max that the value can take, default 1
	 * @param int $min The min that the value can take, default 0
	 * @throws InvalidArgumentException|RangeException
	 */
	static function setNumericCampaignSetting( $noticeName, $settingName, $settingValue, $max = 1, $min = 0 ) {
		if ( $max <= $min ) {
			throw new RangeException( 'Max must be greater than min.' );
		}

		if ( !is_numeric( $settingValue ) ) {
			throw new InvalidArgumentException( 'Setting value must be numeric.' );
		}

		if ( $settingValue > $max ) {
			$settingValue = $max;
		}

		if ( $settingValue < $min ) {
			$settingValue = $min;
		}

		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = CNDatabase::getDb( DB_MASTER );
			$dbw->update( 'cn_notices',
				array( 'not_'.$settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates the weight of a banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $weight       New banner weight
	 */
	static function updateWeight( $noticeName, $templateId, $weight ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$noticeId = Campaign::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'tmp_weight' => $weight ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	/**
	 * Updates the bucket of a banner in a campaign. Buckets alter what is shown to the end user
	 * which can affect the relative weight of the banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $bucket       New bucket number
	 */
	static function updateBucket( $noticeName, $templateId, $bucket ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$noticeId = Campaign::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'asn_bucket' => $bucket ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	// @todo FIXME: Unused.
	static function updateProjectName( $notice, $projectName ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->update( 'cn_notices',
			array( 'not_project' => $projectName ),
			array(
				'not_name' => $notice
			)
		);
	}

	static function updateProjects( $notice, $newProjects ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned projects
		$oldProjects = Campaign::getNoticeProjects( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned projects
		$addProjects = array_diff( $newProjects, $oldProjects );
		$insertArray = array();
		foreach ( $addProjects as $project ) {
			$insertArray[ ] = array( 'np_notice_id' => $row->not_id, 'np_project' => $project );
		}
		$dbw->insert( 'cn_notice_projects', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated projects
		$removeProjects = array_diff( $oldProjects, $newProjects );
		if ( $removeProjects ) {
			$dbw->delete( 'cn_notice_projects',
				array( 'np_notice_id' => $row->not_id, 'np_project' => $removeProjects )
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	static function updateProjectLanguages( $notice, $newLanguages ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned languages
		$oldLanguages = Campaign::getNoticeLanguages( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned languages
		$addLanguages = array_diff( $newLanguages, $oldLanguages );
		$insertArray = array();
		foreach ( $addLanguages as $code ) {
			$insertArray[ ] = array( 'nl_notice_id' => $row->not_id, 'nl_language' => $code );
		}
		$dbw->insert( 'cn_notice_languages', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated languages
		$removeLanguages = array_diff( $oldLanguages, $newLanguages );
		if ( $removeLanguages ) {
			$dbw->delete( 'cn_notice_languages',
				array( 'nl_notice_id' => $row->not_id, 'nl_language' => $removeLanguages )
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	static function updateCountries( $notice, $newCountries ) {
		$dbw = CNDatabase::getDb( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned languages
		$oldCountries = Campaign::getNoticeCountries( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned countries
		$addCountries = array_diff( $newCountries, $oldCountries );
		$insertArray = array();
		foreach ( $addCountries as $code ) {
			$insertArray[ ] = array( 'nc_notice_id' => $row->not_id, 'nc_country' => $code );
		}
		$dbw->insert( 'cn_notice_countries', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated countries
		$removeCountries = array_diff( $oldCountries, $newCountries );
		if ( $removeCountries ) {
			$dbw->delete( 'cn_notice_countries',
				array( 'nc_notice_id' => $row->not_id, 'nc_country' => $removeCountries )
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Log any changes related to a campaign
	 *
	 * @param $action           string: 'created', 'modified', or 'removed'
	 * @param $campaignId       int: ID of campaign
	 * @param $user             User causing the change
	 * @param $beginSettings    array of campaign settings before changes (optional)
	 * @param $endSettings      array of campaign settings after changes (optional)
	 * @param $beginAssignments array of banner assignments before changes (optional)
	 * @param $endAssignments   array of banner assignments after changes (optional)
	 * @param $summary          string Change summary provided by the user
	 *
	 * @return integer: ID of log entry (or null)
	 */
	static function logCampaignChange(
		$action, $campaignId, $user, $beginSettings = array(),
		$endSettings = array(), $beginAssignments = array(),
		$endAssignments = array(), $summary = null
	) {
		// TODO prune unused parameters
		// Only log the change if it is done by an actual user (rather than a testing script)
		if ( $user->getId() > 0 ) { // User::getID returns 0 for anonymous or non-existant users
			$dbw = CNDatabase::getDb( DB_MASTER );

			$log = array(
				'notlog_timestamp' => $dbw->timestamp(),
				'notlog_user_id'   => $user->getId(),
				'notlog_action'    => $action,
				'notlog_not_id'    => $campaignId,
				'notlog_not_name'  => Campaign::getNoticeName( $campaignId )
			);

			// TODO temporary code for soft dependency on schema change
			// Note: MySQL-specific
			global $wgDBtype;
			if ( $wgDBtype === 'mysql' && $dbw->query(
					'SHOW COLUMNS FROM ' .
					$dbw->tableName( 'cn_notice_log' )
					. ' LIKE ' . $dbw->addQuotes( 'notlog_comment' )
				)->numRows() === 1 ) {

				$log['notlog_comment'] = $summary;
			}

			foreach ( $beginSettings as $key => $value ) {
				$log[ 'notlog_begin_' . $key ] = $value;
			}
			foreach ( $endSettings as $key => $value ) {
				$log[ 'notlog_end_' . $key ] = $value;
			}

			$dbw->insert( 'cn_notice_log', $log );
			$log_id = $dbw->insertId();
			return $log_id;
		} else {
			return null;
		}
	}

	static function campaignLogs( $campaign=false, $username=false, $start=false, $end=false, $limit=50, $offset=0 ) {

		$conds = array();
		if ( $start ) {
			$conds[] = "notlog_timestamp >= $start";
		}
		if ( $end ) {
			$conds[] = "notlog_timestamp < $end";
		}
		if ( $campaign ) {
			$conds[] = "notlog_not_name LIKE '$campaign'";
		}
		if ( $username ) {
			$user = User::newFromName( $username );
			if ( $user ) {
				$conds[] = "notlog_user_id = {$user->getId()}";
			}
		}

		// Read from the master database to avoid concurrency problems
		$dbr = CNDatabase::getDb();
		$res = $dbr->select( 'cn_notice_log', '*', $conds,
			__METHOD__,
			array(
				"ORDER BY" => "notlog_timestamp DESC",
				"LIMIT" => $limit,
				"OFFSET" => $offset,
			)
		);
		$logs = array();
		foreach ( $res as $row ) {
			$entry = new CampaignLog( $row );
			$logs[] = array_merge( get_object_vars( $entry ), $entry->changes() );
		}
		return $logs;
	}
}

class CampaignExistenceException extends Exception {}
