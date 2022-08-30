<?php

class Campaign {

	/** @var int|null */
	protected $id = null;
	/** @var string|null */
	protected $name = null;

	/** @var MWTimestamp Start datetime of campaign */
	protected $start = null;

	/** @var MWTimestamp End datetime of campaign */
	protected $end = null;

	/** @var int Priority level of the campaign, higher is more important */
	protected $priority = null;

	/** @var bool True if the campaign is enabled for showing */
	protected $enabled = null;

	/** @var bool True if the campaign is currently non editable */
	protected $locked = null;

	/** @var bool True if the campaign has been moved to the archive */
	protected $archived = null;

	/** @var bool True if there is geo-targeting data for ths campaign */
	protected $geotargeted = null;

	/** @var int The number of buckets in this campaign */
	protected $buckets = null;

	/** @var int */
	protected $throttle = null;

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
			$selector = [ 'not_id' => $this->id ];
		} elseif ( $this->name !== null ) {
			$selector = [ 'not_name' => $this->name ];
		} else {
			throw new CampaignExistenceException( "No valid database key available for campaign." );
		}

		// Get campaign info from database
		$row = $db->selectRow(
			[ 'notices' => 'cn_notices' ],
			[
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
			],
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
				"Campaign could not be retrieved from database " .
					"with id '{$this->id}' or name '{$this->name}'"
			);
		}
	}

	/**
	 * See if a given campaign exists in the database
	 *
	 * @param string $campaignName
	 *
	 * @return bool
	 */
	public static function campaignExists( $campaignName ) {
		$dbr = CNDatabase::getDb();

		return (bool)$dbr->selectRow(
			'cn_notices',
			'not_name',
			[ 'not_name' => $campaignName ],
			__METHOD__
		);
	}

	/**
	 * Get a list of active/active-and-future campaigns and associated banners.
	 *
	 * @param bool $includeFuture Include campaigns that haven't started yet, too.
	 *
	 * @return array An array of campaigns, whose elements are arrays with campaign name,
	 * an array of associated banners, and campaign start and end times.
	 */
	public static function getActiveCampaignsAndBanners( $includeFuture = false ) {
		$dbr = CNDatabase::getDb( DB_REPLICA );
		$time = $dbr->timestamp();

		$conds = [
			'notices.not_end >= ' . $dbr->addQuotes( $time ),
			'notices.not_enabled' => 1,
			'notices.not_archived' => 0
		];

		if ( !$includeFuture ) {
			$conds[] = 'notices.not_start <= ' . $dbr->addQuotes( $time );
		}

		// Query campaigns and banners at once
		$dbRows = $dbr->select(
			[
				'notices' => 'cn_notices',
				'assignments' => 'cn_assignments',
				'templates' => 'cn_templates'
			],
			[
				'notices.not_id',
				'notices.not_name',
				'notices.not_start',
				'notices.not_end',
				'templates.tmp_name'
			],
			$conds,
			__METHOD__,
			[],
			[
				'assignments' => [
					'LEFT OUTER JOIN', 'notices.not_id = assignments.not_id'
				],
				'templates' => [
					'LEFT OUTER JOIN', 'assignments.tmp_id = templates.tmp_id'
				]
			]
		);

		$campaigns = [];

		foreach ( $dbRows as $dbRow ) {
			$campaignId = $dbRow->not_id;

			// The first time we see any campaign, create the corresponding outer K/V
			// entry. Note that these keys don't make it into data structure we return.
			if ( !isset( $campaigns[$campaignId] ) ) {
				$campaigns[$campaignId] = [
					'name' => $dbRow->not_name,
					'start' => $dbRow->not_start,
					'end' => $dbRow->not_end,
				];
			}

			$bannerName = $dbRow->tmp_name;
			// Automagically PHP creates the inner array as needed
			if ( $bannerName ) {
				$campaigns[$campaignId]['banners'][] = $bannerName;
			}
		}

		return array_values( $campaigns );
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param string $campaignName The name of the campaign
	 *
	 * @return array|bool an array of settings or false if the campaign does not exist
	 */
	public static function getCampaignSettings( $campaignName ) {
		$dbr = CNDatabase::getDb();

		// Get campaign info from database
		$row = $dbr->selectRow(
			[ 'notices' => 'cn_notices' ],
			[
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
				'not_type',
			],
			[ 'not_name' => $campaignName ],
			__METHOD__
		);
		if ( $row ) {
			$campaign = [
				'start'     => $row->not_start,
				'end'       => $row->not_end,
				'enabled'   => $row->not_enabled,
				'preferred' => $row->not_preferred,
				'locked'    => $row->not_locked,
				'archived'  => $row->not_archived,
				'geo'       => $row->not_geo,
				'buckets'   => $row->not_buckets,
				'throttle'  => $row->not_throttle,
				'type'      => $row->not_type
			];
		} else {
			return false;
		}

		$projects = self::getNoticeProjects( $campaignName );
		$languages = self::getNoticeLanguages( $campaignName );
		$geo_countries = self::getNoticeCountries( $campaignName );
		$geo_regions = self::getNoticeRegions( $campaignName );
		$campaign[ 'projects' ] = implode( ", ", $projects );
		$campaign[ 'languages' ] = implode( ", ", $languages );
		$campaign[ 'countries' ] = implode( ", ", $geo_countries );
		$campaign[ 'regions' ] = implode( ", ", $geo_regions );

		$bannersIn = Banner::getCampaignBanners( $row->not_id );
		$bannersOut = [];
		// All we want are the banner names, weights, and buckets
		foreach ( $bannersIn as $row ) {
			$outKey = $row['name'];
			$bannersOut[$outKey]['weight'] = $row['weight'];
			$bannersOut[$outKey]['bucket'] = $row['bucket'];
		}
		// Encode into a JSON string for storage
		$campaign[ 'banners' ] = FormatJson::encode( $bannersOut );
		$campaign[ 'mixins' ] =
			FormatJson::encode( self::getCampaignMixins( $campaignName, true ) );

		return $campaign;
	}

	/**
	 * Get all campaign configurations as of timestamp $ts
	 *
	 * @param int $ts
	 * @return array of settings structs having the following properties:
	 *     id
	 *     name
	 *     enabled
	 *     projects: array of sister project names
	 *     languages: array of language codes
	 *     countries: array of country codes
	 *     regions: array of region codes
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
	public static function getHistoricalCampaigns( $ts ) {
		$dbr = CNDatabase::getDb();
		$tsEnc = $dbr->addQuotes( $dbr->timestamp( $ts ) );

		$res = $dbr->select(
			"cn_notice_log",
			[
				"log_id" => "MAX(notlog_id)",
			],
			[
				"notlog_timestamp <= $tsEnc",
			],
			__METHOD__,
			[
				"GROUP BY" => "notlog_not_id",
			]
		);

		$campaigns = [];
		foreach ( $res as $row ) {
			$campaignRow = $dbr->selectRow(
				"cn_notice_log",
				[
					"id" => "notlog_not_id",
					"name" => "notlog_not_name",
					"enabled" => "notlog_end_enabled",
					"projects" => "notlog_end_projects",
					"languages" => "notlog_end_languages",
					"countries" => "notlog_end_countries",
					"regions" => "notlog_end_regions",
					"preferred" => "notlog_end_preferred",
					"geotargeted" => "notlog_end_geo",
					"banners" => "notlog_end_banners",
					"bucket_count" => "notlog_end_buckets",
					"throttle" => "notlog_end_throttle",
				],
				[
					"notlog_id" => $row->log_id,
					"notlog_end_start <= $tsEnc",
					"notlog_end_end >= $tsEnc",
					"notlog_end_enabled = 1",
				],
				__METHOD__
			);

			if ( !$campaignRow ) {
				continue;
			}
			$campaign = (array)$campaignRow;
			$campaign['projects'] = explode( ", ", $campaign['projects'] );
			$campaign['languages'] = explode( ", ", $campaign['languages'] );
			$campaign['countries'] = explode( ", ", $campaign['countries'] );
			$campaign['regions'] = explode( ", ", $campaign['regions'] );
			if ( $campaign['banners'] === null ) {
				$campaign['banners'] = [];
			} else {
				$campaign['banners'] = FormatJson::decode( $campaign['banners'], true );
				if ( !is_array( current( $campaign['banners'] ) ) ) {
					// Old log format; only had weight
					foreach ( $campaign['banners'] as $key => &$value ) {
						$value = [
							'weight' => $value,
							'bucket' => 0
						];
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
					// FIXME: crazy hacks
					$historical_banner = Banner::getBannerSettings( $name );
					$historical_banner['label'] = wfMessage( 'centralnotice-damaged-log', $name )->text();
					$historical_banner['display_anon'] = $historical_banner['anon'];
					$historical_banner['display_account'] = $historical_banner['account'];
					$historical_banner['devices'] = [ 'desktop' ];
				}
				$banner['name'] = $name;
				$banner['label'] = $name;

				$campaign_info = [
					'campaign' => $campaign['name'],
					'campaign_z_index' => $campaign['preferred'],
					'campaign_num_buckets' => $campaign['bucket_count'],
					'campaign_throttle' => $campaign['throttle'],
				];

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
	 * @param bool $compact
	 * @return array
	 */
	public static function getCampaignMixins( $campaignName, $compact = false ) {
		global $wgCentralNoticeCampaignMixins;

		$dbr = CNDatabase::getDb();

		// Prepare query conditions
		$conds = [ 'notices.not_name' => $campaignName ];
		if ( $compact ) {
			$conds['notice_mixins.nmxn_enabled'] = 1;
		}

		$dbRows = $dbr->select(
			[
				'notices' => 'cn_notices',
				'notice_mixins' => 'cn_notice_mixins',
				'notice_mixin_params' => 'cn_notice_mixin_params'
			],
			[
				'notice_mixins.nmxn_mixin_name',
				'notice_mixins.nmxn_enabled',
				'notice_mixin_params.nmxnp_param_name',
				'notice_mixin_params.nmxnp_param_value'
			],
			$conds,
			__METHOD__,
			[],
			[
				'notice_mixins' => [
					'INNER JOIN', 'notices.not_id = notice_mixins.nmxn_not_id'
				],
				'notice_mixin_params' => [
					'LEFT OUTER JOIN',
					'notice_mixins.nmxn_id = notice_mixin_params.nmxnp_notice_mixin_id'
				]
			]
		);

		// Build up the results
		// We expect a row for every parameter name-value pair for every mixin,
		// and maybe some with null name-value pairs (for mixins with no
		// parameters).
		$campaignMixins = [];
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
					$campaignMixins[$mixinName] = [];

				} else {
					$campaignMixins[$mixinName] = [
						'enabled' => (bool)$dbRow->nmxn_enabled,
						'parameters' => []
					];
				}
			}

			// If there are mixin params in this row, add them in
			if ( $dbRow->nmxnp_param_name !== null ) {
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

					case 'json':
						$paramVal = json_decode( $dbRow->nmxnp_param_value );

						if ( $paramVal === null ) {
							wfLogWarning( 'Couldn\'t decode json param ' . $paramName
								. ' for mixin ' . $mixinName . ' in campaign ' .
								$campaignName . '.' );

							// In this case, it's fine to emit a null value for the
							// parameter. Both Admin UI and subscribing client-side
							// code should handle it gracefully and warn in the console.
							// TODO Handle this better, server-side.
						}

						break;

					default:
						throw new DomainException(
							'Unknown parameter type ' . $paramType );
				}

				// Again, data structure depends on $compact
				if ( $compact ) {
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

		array_walk( $campaignMixins, static function ( &$campaignMixin ) {
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
	 * @param bool $enable
	 * @param array|null $params For mixins with no parameters, set to an empty array.
	 */
	public static function updateCampaignMixins(
		$campaignName, $mixinName, $enable, $params = null
	) {
		global $wgCentralNoticeCampaignMixins;

		// TODO Error handling!

		$dbw = CNDatabase::getDb( DB_PRIMARY );

		// Get the campaign ID
		// Note: the need to fetch the ID here highlights the need for some
		// kind of ORM.
		$noticeId = $dbw->selectRow( 'cn_notices', 'not_id',
			[ 'not_name' => $campaignName ], __METHOD__ )->not_id;

		if ( $enable ) {
			if ( $params === null ) {
				throw new InvalidArgumentException( 'Paremeters info required to enable mixin ' .
					$mixinName . ' for campaign ' . $campaignName );
			}

			$dbw->upsert(
				'cn_notice_mixins',
				[
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName,
					'nmxn_enabled' => 1
				],
				[ [ 'nmxn_not_id', 'nmxn_mixin_name' ] ],
				[
					'nmxn_enabled' => 1
				],
				__METHOD__
			);

			$noticeMixinId = $dbw->selectRow(
				'cn_notice_mixins',
				'nmxn_id',
				[
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName
				],
				__METHOD__
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
					[
						'nmxnp_notice_mixin_id' => $noticeMixinId,
						'nmxnp_param_name' => $paramName,
						'nmxnp_param_value' => $paramVal
					],
					[ [ 'nmxnp_notice_mixin_id', 'nmxnp_param_name' ] ],
					[
						'nmxnp_param_value' => $paramVal
					],
					__METHOD__
				);
			}

		} else {

			// When we disable a mixin, just set enabled to false; since we keep
			// the old parameter values in case the mixin is re-enabled, we also
			// keep the row in this table, since the id is used in the param
			// table.
			$dbw->update(
				'cn_notice_mixins',
				[ 'nmxn_enabled' => 0 ],
				[
					'nmxn_not_id' => $noticeId,
					'nmxn_mixin_name' => $mixinName
				],
				__METHOD__
			);
		}
	}

	/**
	 * Get all the campaigns in the database
	 *
	 * @return array an array of campaign names
	 */
	public static function getAllCampaignNames() {
		$dbr = CNDatabase::getDb();
		$res = $dbr->select( 'cn_notices', 'not_name', '', __METHOD__ );
		$notices = [];
		foreach ( $res as $row ) {
			$notices[] = $row->not_name;
		}
		return $notices;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param string $noticeName Name of the campaign
	 * @param bool $enabled Boolean setting, true or false
	 * @param string $startTs Campaign start in UTC
	 * @param array $projects Targeted project types (wikipedia, wikibooks, etc.)
	 * @param array $project_languages Targeted project languages (en, de, etc.)
	 * @param bool $geotargeted Boolean setting, true or false
	 * @param array $geo_countries Targeted countries
	 * @param array $geo_regions Targeted regions in format CountryCode_RegionCode
	 * @param int $throttle limit allocations, 0 - 100
	 * @param int $priority priority level, LOW_PRIORITY - EMERGENCY_PRIORITY
	 * @param User $user User adding the campaign
	 * @param string|null $type Type of campaign
	 * @param string|null $summary Change summary provided by the user
	 * @return int|string noticeId on success, or message key for error
	 */
	public static function addCampaign( $noticeName, $enabled, $startTs, $projects,
		$project_languages, $geotargeted, $geo_countries, $geo_regions, $throttle,
		$priority, $user, $type, $summary = null
	) {
		$noticeName = trim( $noticeName );
		if ( self::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-exists';
		} elseif ( empty( $projects ) ) {
			return 'centralnotice-no-project';
		} elseif ( empty( $project_languages ) ) {
			return 'centralnotice-no-language';
		}

		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		$endTime = strtotime( '+1 hour', (int)wfTimestamp( TS_UNIX, $startTs ) );
		$endTs = wfTimestamp( TS_MW, $endTime );

		$dbw->insert( 'cn_notices',
			[
				'not_name'      => $noticeName,
				'not_enabled'   => (int)$enabled,
				'not_start'     => $dbw->timestamp( $startTs ),
				'not_end'       => $dbw->timestamp( $endTs ),
				'not_geo'       => (int)$geotargeted,
				'not_throttle'  => $throttle,
				'not_preferred' => $priority,
				'not_type'      => $type
			],
			__METHOD__
		);
		$not_id = $dbw->insertId();

		if ( $not_id ) {
			// Do multi-row insert for campaign projects
			$insertArray = [];
			foreach ( $projects as $project ) {
				$insertArray[] = [ 'np_notice_id' => $not_id, 'np_project' => $project ];
			}
			$dbw->insert( 'cn_notice_projects', $insertArray,
				__METHOD__, [ 'IGNORE' ] );

			// Do multi-row insert for campaign languages
			$insertArray = [];
			foreach ( $project_languages as $code ) {
				$insertArray[] = [ 'nl_notice_id' => $not_id, 'nl_language' => $code ];
			}
			$dbw->insert( 'cn_notice_languages', $insertArray,
				__METHOD__, [ 'IGNORE' ] );

			if ( $geotargeted ) {

				// Do multi-row insert for campaign countries
				if ( $geo_countries ) {
					$insertArray = [];
					foreach ( $geo_countries as $code ) {
						$insertArray[] = [ 'nc_notice_id' => $not_id, 'nc_country' => $code ];
					}
					$dbw->insert(
						'cn_notice_countries', $insertArray, __METHOD__, [ 'IGNORE' ] );
				}

				// Do multi-row insert for campaign regions
				if ( $geo_regions ) {
					$insertArray = [];
					foreach ( $geo_regions as $code ) {
						$insertArray[] = [ 'nr_notice_id' => $not_id, 'nr_region' => $code ];
					}
					$dbw->insert( 'cn_notice_regions', $insertArray,
						__METHOD__, [ 'IGNORE' ] );
				}

			}

			$dbw->endAtomic( __METHOD__ );

			// Log the creation of the campaign
			$beginSettings = [];
			$endSettings = [
				'projects'  => implode( ", ", $projects ),
				'languages' => implode( ", ", $project_languages ),
				'countries' => implode( ", ", $geo_countries ),
				'regions'   => implode( ", ", $geo_regions ),
				'start'     => $dbw->timestamp( $startTs ),
				'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => (int)$enabled,
				'preferred' => 0,
				'locked'    => 0,
				'archived'  => 0,
				'geo'       => (int)$geotargeted,
				'throttle'  => $throttle,
				'type'      => $type
			];
			self::processAfterCampaignChange( 'created', $not_id, $noticeName, $user,
				$beginSettings, $endSettings, $summary );

			return $not_id;
		}

		throw new RuntimeException( 'insertId() did not return a value.' );
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param string $campaignName Name of the campaign
	 * @param User $user User removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	public static function removeCampaign( $campaignName, $user ) {
		// TODO This method is never used?
		$dbr = CNDatabase::getDb( DB_PRIMARY );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			[ 'not_name' => $campaignName ],
			__METHOD__
		);
		if ( $res->numRows() < 1 ) {
			return 'centralnotice-remove-notice-doesnt-exist';
		}
		$row = $res->fetchObject();
		if ( $row->not_locked == '1' ) {
			return 'centralnotice-notice-is-locked';
		}

		self::removeCampaignByName( $campaignName, $user );

		return true;
	}

	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = self::getNoticeId( $campaignName );
		self::processAfterCampaignChange( 'removed', $campaignId, $campaignName, $user );

		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'cn_assignments', [ 'not_id' => $campaignId ], __METHOD__ );
		$dbw->delete( 'cn_notices', [ 'not_name' => $campaignName ], __METHOD__ );
		$dbw->delete( 'cn_notice_languages', [ 'nl_notice_id' => $campaignId ], __METHOD__ );
		$dbw->delete( 'cn_notice_projects', [ 'np_notice_id' => $campaignId ], __METHOD__ );
		$dbw->delete( 'cn_notice_countries', [ 'nc_notice_id' => $campaignId ], __METHOD__ );
		$dbw->delete( 'cn_notice_regions', [ 'nr_notice_id' => $campaignId ], __METHOD__ );
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Assign a banner to a campaign at a certain weight
	 * @param string $noticeName
	 * @param string $templateName
	 * @param int $weight
	 * @param int $bucket
	 * @return bool|string True on success, string with message key for error
	 */
	public static function addTemplateTo( $noticeName, $templateName, $weight, $bucket = 0 ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );

		$noticeId = self::getNoticeId( $noticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$res = $dbw->select( 'cn_assignments', 'asn_id',
			[
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			],
			__METHOD__
		);

		if ( $res->numRows() > 0 ) {
			return 'centralnotice-template-already-exists';
		}

		$dbw->insert( 'cn_assignments',
			[
				'tmp_id'     => $templateId,
				'tmp_weight' => $weight,
				'not_id'     => $noticeId,
				'asn_bucket' => $bucket,
			],
			__METHOD__
		);

		return true;
	}

	/**
	 * Remove a banner assignment from a campaign
	 * @param string $noticeName
	 * @param string $templateName
	 */
	public static function removeTemplateFor( $noticeName, $templateName ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$noticeId = self::getNoticeId( $noticeName );
		$templateId = Banner::fromName( $templateName )->getId();
		$dbw->delete( 'cn_assignments', [ 'tmp_id' => $templateId, 'not_id' => $noticeId ], __METHOD__ );
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 * @param string $noticeName
	 * @return int|null
	 */
	public static function getNoticeId( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$row = $dbr->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $noticeName ], __METHOD__ );
		if ( $row ) {
			return $row->not_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 * @param int $noticeId
	 * @return null|string
	 */
	public static function getNoticeName( $noticeId ) {
		$dbr = CNDatabase::getDb();
		if ( is_numeric( $noticeId ) ) {
			$row = $dbr->selectRow( 'cn_notices', 'not_name', [ 'not_id' => $noticeId ], __METHOD__ );
			if ( $row ) {
				return $row->not_name;
			}
		}
		return null;
	}

	public static function getNoticeProjects( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$row = $dbr->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $noticeName ], __METHOD__ );
		$projects = [];
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_projects', 'np_project',
				[ 'np_notice_id' => $row->not_id ], __METHOD__ );
			foreach ( $res as $projectRow ) {
				$projects[] = $projectRow->np_project;
			}
		}
		sort( $projects );
		return $projects;
	}

	public static function getNoticeLanguages( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$row = $dbr->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $noticeName ], __METHOD__ );
		$languages = [];
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_languages', 'nl_language',
				[ 'nl_notice_id' => $row->not_id ], __METHOD__ );
			foreach ( $res as $langRow ) {
				$languages[] = $langRow->nl_language;
			}
		}
		sort( $languages );
		return $languages;
	}

	/**
	 * @param string $noticeName
	 *
	 * @return string[]
	 */
	public static function getNoticeCountries( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$row = $dbr->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $noticeName ], __METHOD__ );
		$countries = [];
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_countries', 'nc_country',
				[ 'nc_notice_id' => $row->not_id ], __METHOD__ );
			foreach ( $res as $countryRow ) {
				$countries[] = $countryRow->nc_country;
			}
		}
		sort( $countries );
		return $countries;
	}

	/**
	 * @param string $noticeName
	 *
	 * @return string[]
	 */
	public static function getNoticeRegions( $noticeName ) {
		$dbr = CNDatabase::getDb();
		$row = $dbr->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $noticeName ], __METHOD__ );
		$regions = [];
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_regions', 'nr_region',
				[ 'nr_notice_id' => $row->not_id ], __METHOD__ );
			foreach ( $res as $regionRow ) {
				$regions[] = $regionRow->nr_region;
			}
		}
		sort( $regions );
		return $regions;
	}

	/**
	 * Returns a Title object to use in obtaining the URL of a campaign.
	 * @return Title
	 */
	public static function getTitleForURL() {
		return SpecialPage::getTitleFor( 'CentralNotice' );
	}

	/**
	 * Returns an array with key/value pairs for a query string, to use in obtaining the
	 * URL of the campaign with the specified name.
	 *
	 * @param string $campaignName
	 * @return string[]
	 */
	public static function getQueryForURL( $campaignName ) {
		return [
			'subaction' => 'noticeDetail',
			'notice' => $campaignName
		];
	}

	/**
	 * Returns the canonical URL for campaign with the specified name (as returned by
	 * Title::getCanonicalURL()).
	 *
	 * Usage note: This method should be considered part of CentralNotice's public API.
	 * It's called from outside the extension in EventBus::onCentralNoticeCampaignChange().
	 *
	 * @param string $campaignName
	 * @return string
	 */
	public static function getCanonicalURL( $campaignName ) {
		return self::getTitleForURL()->getCanonicalURL(
			self::getQueryForURL( $campaignName ) );
	}

	/**
	 * @param string $noticeName
	 * @param string $start Date
	 * @param string $end Date
	 * @return bool|string True on success, string with message key for error
	 */
	public static function updateNoticeDate( $noticeName, $start, $end ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );

		// Start/end don't line up
		if ( $start > $end || $end < $start ) {
			return 'centralnotice-invalid-date-range';
		}

		// Invalid campaign name
		if ( !self::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-doesnt-exist';
		}

		// Overlap over a date within the same project and language
		$startDate = $dbw->timestamp( $start );
		$endDate = $dbw->timestamp( $end );

		$dbw->update( 'cn_notices',
			[
				'not_start' => $startDate,
				'not_end'   => $endDate
			],
			[ 'not_name' => $noticeName ],
			__METHOD__
		);

		return true;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param string $noticeName Name of the campaign
	 * @param string $settingName Name of a boolean setting (enabled, locked, or geo)
	 * @param bool $settingValue Value to use for the setting, true or false
	 */
	public static function setBooleanCampaignSetting( $noticeName, $settingName, $settingValue ) {
		if ( !self::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			if ( !self::settingNameIsValid( $settingName ) ) {
				throw new InvalidArgumentException( "Invalid setting name" );
			}
			$dbw = CNDatabase::getDb( DB_PRIMARY );
			$dbw->update( 'cn_notices',
				[ 'not_' . $settingName => (int)$settingValue ],
				[ 'not_name' => $noticeName ],
				__METHOD__
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
	public static function setNumericCampaignSetting(
		$noticeName, $settingName, $settingValue, $max = 1, $min = 0
	) {
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

		if ( !self::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			if ( !self::settingNameIsValid( $settingName ) ) {
				throw new InvalidArgumentException( "Invalid setting name" );
			}
			$dbw = CNDatabase::getDb( DB_PRIMARY );
			$dbw->update( 'cn_notices',
				[ 'not_' . $settingName => $settingValue ],
				[ 'not_name' => $noticeName ],
				__METHOD__
			);
		}
	}

	/**
	 * Updates the weight of a banner in a campaign.
	 *
	 * @param string $noticeName Name of the campaign to update
	 * @param int $templateId ID of the banner in the campaign
	 * @param int $weight New banner weight
	 */
	public static function updateWeight( $noticeName, $templateId, $weight ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$noticeId = self::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			[ 'tmp_weight' => $weight ],
			[
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			],
			__METHOD__
		);
	}

	/**
	 * Updates the bucket of a banner in a campaign. Buckets alter what is shown to the end user
	 * which can affect the relative weight of the banner in a campaign.
	 *
	 * @param string $noticeName Name of the campaign to update
	 * @param int $templateId ID of the banner in the campaign
	 * @param int $bucket New bucket number
	 */
	public static function updateBucket( $noticeName, $templateId, $bucket ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$noticeId = self::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			[ 'asn_bucket' => $bucket ],
			[
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			],
			__METHOD__
		);
	}

	// @todo FIXME: Unused.
	public static function updateProjectName( $notice, $projectName ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->update( 'cn_notices',
			[ 'not_project' => $projectName ],
			[
				'not_name' => $notice
			],
			__METHOD__
		);
	}

	public static function updateProjects( $notice, $newProjects ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned projects
		$oldProjects = self::getNoticeProjects( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $notice ], __METHOD__ );

		// Add newly assigned projects
		$addProjects = array_diff( $newProjects, $oldProjects );
		$insertArray = [];
		foreach ( $addProjects as $project ) {
			$insertArray[] = [ 'np_notice_id' => $row->not_id, 'np_project' => $project ];
		}
		$dbw->insert( 'cn_notice_projects', $insertArray, __METHOD__, [ 'IGNORE' ] );

		// Remove disassociated projects
		$removeProjects = array_diff( $oldProjects, $newProjects );
		if ( $removeProjects ) {
			$dbw->delete( 'cn_notice_projects',
				[ 'np_notice_id' => $row->not_id, 'np_project' => $removeProjects ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	public static function updateProjectLanguages( $notice, $newLanguages ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned languages
		$oldLanguages = self::getNoticeLanguages( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $notice ], __METHOD__ );

		// Add newly assigned languages
		$addLanguages = array_diff( $newLanguages, $oldLanguages );
		$insertArray = [];
		foreach ( $addLanguages as $code ) {
			$insertArray[] = [ 'nl_notice_id' => $row->not_id, 'nl_language' => $code ];
		}
		$dbw->insert( 'cn_notice_languages', $insertArray, __METHOD__, [ 'IGNORE' ] );

		// Remove disassociated languages
		$removeLanguages = array_diff( $oldLanguages, $newLanguages );
		if ( $removeLanguages ) {
			$dbw->delete( 'cn_notice_languages',
				[ 'nl_notice_id' => $row->not_id, 'nl_language' => $removeLanguages ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Update countries targeted for a campaign
	 * @param string $notice
	 * @param array $newCountries
	 */
	public static function updateCountries( $notice, $newCountries ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned countries
		$oldCountries = self::getNoticeCountries( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $notice ], __METHOD__ );

		// Add newly assigned countries
		$addCountries = array_diff( $newCountries, $oldCountries );
		$insertArray = [];
		foreach ( $addCountries as $code ) {
			$insertArray[] = [ 'nc_notice_id' => $row->not_id, 'nc_country' => $code ];
		}
		$dbw->insert( 'cn_notice_countries', $insertArray, __METHOD__, [ 'IGNORE' ] );

		// Remove disassociated countries
		$removeCountries = array_diff( $oldCountries, $newCountries );
		if ( $removeCountries ) {
			$dbw->delete( 'cn_notice_countries',
				[ 'nc_notice_id' => $row->not_id, 'nc_country' => $removeCountries ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Update regions targeted for a campaign
	 * @param string $notice
	 * @param array $newRegions in format CountryCode_RegionCode
	 */
	public static function updateRegions( $notice, $newRegions ) {
		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Get the previously assigned regions
		$oldRegions = self::getNoticeRegions( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', [ 'not_name' => $notice ], __METHOD__ );

		// Add newly assigned regions
		$addRegions = array_diff( $newRegions, $oldRegions );
		$insertArray = [];
		foreach ( $addRegions as $code ) {
			$insertArray[] = [ 'nr_notice_id' => $row->not_id, 'nr_region' => $code ];
		}
		$dbw->insert( 'cn_notice_regions', $insertArray, __METHOD__, [ 'IGNORE' ] );

		// Remove disassociated regions
		$removeRegions = array_diff( $oldRegions, $newRegions );
		if ( $removeRegions ) {
			$dbw->delete( 'cn_notice_regions',
				[ 'nr_notice_id' => $row->not_id, 'nr_region' => $removeRegions ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Log any changes related to a campaign
	 *
	 * @param string $action 'created', 'modified', or 'removed'
	 * @param int $campaignId ID of the campaign
	 * @param string $campaignName Name of the campaign
	 * @param User $user User causing the change
	 * @param array $beginSettings array of campaign settings before changes (optional).
	 *   If provided, it should include at least start, end, enabled and archived.
	 * @param array $endSettings array of campaign settings after changes (optional).
	 *   If provided, it should include at least start, end, enabled and archived.
	 * @param string|null $summary Change summary provided by the user
	 */
	public static function processAfterCampaignChange(
		$action, $campaignId, $campaignName, $user, $beginSettings = [],
		$endSettings = [], $summary = null
	) {
		ChoiceDataProvider::invalidateCache();

		// Summary shouldn't actually come in null, but just in case...
		if ( $summary === null ) {
			$summary = '';
		}

		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$time = $dbw->timestamp();

		$log = [
			'notlog_timestamp' => $time,
			'notlog_user_id'   => $user->getId(),
			'notlog_action'    => $action,
			'notlog_not_id'    => $campaignId,
			'notlog_not_name'  => $campaignName,
			'notlog_comment'   => $summary,
		];

		foreach ( $beginSettings as $key => $value ) {
			if ( !self::settingNameIsValid( $key ) ) {
				throw new InvalidArgumentException( "Invalid setting name" );
			}
				$log[ 'notlog_begin_' . $key ] = $value;
		}

		foreach ( $endSettings as $key => $value ) {
			if ( !self::settingNameIsValid( $key ) ) {
				throw new InvalidArgumentException( "Invalid setting name" );
			}
				$log[ 'notlog_end_' . $key ] = $value;
		}

		Hooks::runWithoutAbort(
			'CentralNoticeCampaignChange',
			[
				$action,
				$time,
				$campaignName,
				$user,
				self::processSettingsForHook( $beginSettings ),
				self::processSettingsForHook( $endSettings ),
				$summary
			]
		);

		// Only log the change if it is done by an actual user (rather than a testing script)
		// FIXME There must be a cleaner way to do this?
		if ( $user->getId() > 0 ) { // User::getID returns 0 for anonymous or non-existant users
			$dbw->insert( 'cn_notice_log', $log, __METHOD__ );
		}
	}

	/**
	 * Prepare campaign settings to be sent to the CampaignChange hook. This is necessary
	 * since the settings provided to processAfterCampaignChange() are in a format
	 * that is appropriate for the cn_notice_log table, but not for the hook.
	 *
	 * @param array $settings
	 * @return array|null
	 */
	private static function processSettingsForHook( $settings ) {
		if ( !$settings ) {
			return null;
		}

		if ( isset( $settings[ 'banners' ] ) ) {
			$banners = json_decode( $settings[ 'banners' ] );

			// This should never happen, since the string should just have been json-encoded
			// in getCampaignSettings().
			if ( $banners === null ) {
				throw new UnexpectedValueException( 'Json decoding error for banner settings' );
			}

			// Names of banners are object properties
			$banners = array_keys( (array)$banners );

		} else {
			$banners = [];
		}

		return [
			'start' => $settings[ 'start' ],
			'end' => $settings[ 'end' ],
			'enabled' => (bool)$settings[ 'enabled' ],
			'archived' => (bool)$settings[ 'archived' ],
			'banners' => $banners,
		];
	}

	/**
	 * Check that a string is a valid setting name.
	 * @param string $settingName
	 * @return bool
	 */
	protected static function settingNameIsValid( $settingName ) {
		return ( preg_match( '/^[a-z_]*$/', $settingName ) === 1 );
	}

	public static function setType( $campaignName, $type ) {
		// Following pattern from setNumericaCampaignSettings() and exiting with no
		// error if the campaign doesn't exist. TODO Is this right?
		if ( !self::campaignExists( $campaignName ) ) {
			return;
		}

		$dbw = CNDatabase::getDb( DB_PRIMARY );
		$dbw->update(
			'cn_notices',
			[ 'not_type' => $type ],
			[ 'not_name' => $campaignName ],
			__METHOD__
		);
	}

	public static function campaignLogs(
		$campaign = false, $username = false, $start = false, $end = false, $limit = 50, $offset = 0
	) {
		// Read from the primary database to avoid concurrency problems
		$dbr = CNDatabase::getDb();
		$conds = [];
		if ( $start ) {
			$conds[] = "notlog_timestamp >= " . $dbr->addQuotes( $start );
		}
		if ( $end ) {
			$conds[] = "notlog_timestamp < " . $dbr->addQuotes( $end );
		}
		if ( $campaign ) {
			// This used to be a LIKE, but that was undocumented,
			// and filters prevented the % and \ character from being
			// used. The one character _ wildcard could have been used
			// from the api, but that was completely undocumented.
			// This was sketchy security wise, so the LIKE was removed.
			$conds["notlog_not_name"] = $campaign;
		}
		if ( $username ) {
			$user = User::newFromName( $username );
			if ( $user ) {
				$conds["notlog_user_id"] = $user->getId();
			}
		}

		$res = $dbr->select( 'cn_notice_log', '*', $conds,
			__METHOD__,
			[
				"ORDER BY" => "notlog_timestamp DESC",
				"LIMIT" => $limit,
				"OFFSET" => $offset,
			]
		);
		$logs = [];
		foreach ( $res as $row ) {
			$entry = new CampaignLog( $row );
			$logs[] = array_merge( get_object_vars( $entry ), $entry->changes() );
		}
		return $logs;
	}
}
