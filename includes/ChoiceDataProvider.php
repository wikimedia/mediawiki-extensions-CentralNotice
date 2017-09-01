<?php

/***
 * Provides a set of campaign and banner choices based on allocations for a
 * given project and language combination.
 */
class ChoiceDataProvider {

	/** @var string Common prefix for choice data cache keys */
	const CACHE_KEY_NAMESPACE = 'CentralNoticeChoiceData';

	/** @var int Time-to-live for choice data cache entries, in seconds */
	const CACHE_TTL = 3600;

	/**
	 * Invalidate the shared global cache.
	 */
	public static function invalidateCache() {
		$cache = ObjectCache::getMainWANInstance();
		return $cache->touchCheckKey(
			$cache->makeGlobalKey( self::CACHE_KEY_NAMESPACE, 'check' ) );
	}

	/**
	 * Get a data structure with the allocation choices.
	 *
	 * @param string $project The project to get choices for
	 * @param string $language The language to get choices for
	 * @return array A structure of arrays. The outer array contains associative
	 *   arrays that represent campaigns. One campaign property is 'banners',
	 *   which has as its value an array of associative arrays that represent
	 *   banners. Note that only some properties of campaigns and banners
	 *   are provided.
	 */
	public static function getChoices( $project, $language ) {
		$cache = ObjectCache::getMainWANInstance();

		$dataKey = $cache->makeGlobalKey( self::CACHE_KEY_NAMESPACE, $project, $language );
		$checkKey = $cache->makeGlobalKey( self::CACHE_KEY_NAMESPACE, 'check' );

		$choices = $cache->getWithSetCallback(
			$dataKey,
			self::CACHE_TTL,
			function ( $oldValue, &$ttl, array &$setOpts )
				use ( $project, $language ) {
				$dbr = CNDatabase::getDb( DB_SLAVE );

				// Account for slave lag to prevent a race condition when
				// campaigns are updated, the cache is invalidated, and
				// a client queries a yet-unsynced slave DB. Also, gracefully
				// fail if we're running an old version of core (<1.27).
				if ( method_exists( 'Database', 'getCacheSetOptions' ) ) {
					$setOpts += Database::getCacheSetOptions( $dbr );
				}

				return self::fetchChoices( $project, $language, $dbr );
			},
			[
				// We don't bother with the lockTSE option because the only
				// potentially high-volume requests that would ask for this
				// object are heavily cached by Varnish, for all users. (Those
				// requests are for load.php.)
				'checkKeys' => [ $checkKey ],
				'pcTTL' => $cache::TTL_PROC_LONG,
			]
		);

		// Filter out campaigns that have ended since we last queried the
		// database or which have not started yet.
		$now = time();
		return array_filter( $choices, function ( $choice ) use ( $now ) {
			return $choice['end'] >= $now && $choice['start'] <= $now;
		} );
	}

	private static function fetchChoices( $project, $language,
		IDatabase $dbr
	) {
		// For speed, we'll do our own queries instead of using methods in
		// Campaign and Banner.

		// Set up conditions
		// Choice data will be cached for up to an hour, so we want to include
		// campaigns that will start during that interval.
		$start = $dbr->timestamp( time() + self::CACHE_TTL );
		$end = $dbr->timestamp();
		$conds = [
			'notices.not_start <= ' . $dbr->addQuotes( $start ),
			'notices.not_end >= ' . $dbr->addQuotes( $end ),
			'notices.not_enabled' => 1,
			'notices.not_archived' => 0,
			'notice_projects.np_project' => $project,
			'notice_languages.nl_language' => $language
		];

		// Query campaigns and banners at once
		$dbRows = $dbr->select(
			[
				'notices' => 'cn_notices',
				'assignments' => 'cn_assignments',
				'templates' => 'cn_templates',
				'notice_projects' => 'cn_notice_projects',
				'notice_languages' => 'cn_notice_languages',
			],
			[
				'notices.not_id',
				'notices.not_name',
				'notices.not_start',
				'notices.not_end',
				'notices.not_preferred',
				'notices.not_throttle',
				'notices.not_geo',
				'notices.not_buckets',
				'assignments.tmp_weight',
				'assignments.asn_bucket',
				'templates.tmp_id',
				'templates.tmp_name',
				'templates.tmp_display_anon',
				'templates.tmp_display_account',
				'templates.tmp_category'
			],
			$conds,
			__METHOD__,
			[],
			[
				'assignments' => [
					'INNER JOIN', 'notices.not_id = assignments.not_id'
				],
				'templates' => [
					'INNER JOIN', 'assignments.tmp_id = templates.tmp_id'
				],
				'notice_projects' => [
					'INNER JOIN', 'notices.not_id = notice_projects.np_notice_id'
				],
				'notice_languages' => [
					'INNER JOIN', 'notices.not_id = notice_languages.nl_notice_id'
				]
			]
		);

		// Pare it down into a nicer data structure and prepare the next queries.
		// We'll create a structure with keys that are useful for piecing the
		// data together. But before returning it, we'll change associative
		// arrays to indexed ones at levels where the keys are not needed by the
		// client.
		$choices = [];
		$bannerIds = [];
		$assignmentKeysByBannerIdAndCampaignId = [];

		foreach ( $dbRows as $dbRow ) {
			$campaignId = $dbRow->not_id;
			$campaignName = $dbRow->not_name;
			$bannerId = $dbRow->tmp_id;
			$bannerName = $dbRow->tmp_name;
			$bucket = $dbRow->asn_bucket;

			// FIXME Temporary hack to substitute the magic words {{{campaign}}}
			// and {{{banner}}} in banner categories. (These are the magic
			// words mentioned in the CN Admin UI.)
			$category = $dbRow->tmp_category;
			$category = str_replace( '{{{campaign}}}', $campaignName, $category );
			$category = str_replace( '{{{banner}}}', $bannerName, $category );
			$category = Banner::sanitizeRenderedCategory( $category );

			// The first time we see any campaign, create the corresponding
			// outer K/V entry. The campaign-specific properties should be
			// repeated on every row for any campaign. Note that these
			// keys don't make it into data structure we return.
			if ( !isset( $choices[$campaignId] ) ) {
				$choices[$campaignId] = [
					'name' => $campaignName,
					'start' => intval( wfTimestamp( TS_UNIX, $dbRow->not_start ) ),
					'end' => intval( wfTimestamp( TS_UNIX, $dbRow->not_end ) ),
					'preferred' => intval( $dbRow->not_preferred ),
					'throttle' => intval( $dbRow->not_throttle ),
					'bucket_count' => intval( $dbRow->not_buckets ),
					'geotargeted' => (bool)$dbRow->not_geo,
					'banners' => []
				];
			}

			// A temporary assignment key so we can get back to this part of the
			// data structure quickly and add in devices.
			$assignmentKey = $bannerId . ':' . $bucket;

			$choices[$campaignId]['banners'][$assignmentKey] = [
				'name' => $bannerName,
				'bucket' => intval( $bucket ),
				'weight' => intval( $dbRow->tmp_weight ),
				'category' => $category,
				'display_anon' => (bool)$dbRow->tmp_display_anon,
				'display_account' => (bool)$dbRow->tmp_display_account,
				'devices' => [] // To be filled by the last query
			];

			$bannerIds[] = $bannerId;

			// Add to the index so we can get back here.
			// Note that PHP creates arrays here as needed.
			$assignmentKeysByBannerIdAndCampaignId[$bannerId][$campaignId][] =
				$assignmentKey;
		}

		// If there's nothing, return the empty array now
		if ( count( $choices ) === 0 ) {
			return $choices;
		}

		// Fetch countries.
		// We have to eliminate notices that are not geotargeted, since they
		// may have residual data in the cn_notice_countries table.
		$dbRows = $dbr->select(
			[
				'notices' => 'cn_notices',
				'notice_countries' => 'cn_notice_countries',
			],
			[
				'notices.not_id',
				'notice_countries.nc_country'
			],
			[
				'notices.not_geo' => 1,
				'notices.not_id' => array_keys( $choices )
			],
			__METHOD__,
			[],
			[
				'notice_countries' => [
					'INNER JOIN', 'notices.not_id = notice_countries.nc_notice_id'
				]
			]
		);

		// Add countries to our data structure.
		// Note that PHP creates an empty array for countries as needed.
		foreach ( $dbRows as $dbRow ) {
			$choices[$dbRow->not_id]['countries'][] = $dbRow->nc_country;
		}

		// FIXME: looks like this is only sorting the last banner's list!
		if ( isset( $choices[$dbRow->not_id]['countries'] ) ) {
			sort( $choices[$dbRow->not_id]['countries'] );
		}

		// Add campaign-associated mixins to the data structure
		foreach ( $choices as &$campaignInfo ) {
			// Get info for enabled mixins for this campaign
			$campaignInfo['mixins'] =
				Campaign::getCampaignMixins( $campaignInfo['name'], true );
		}

		// Fetch the devices
		$dbRows = $dbr->select(
			[
				'template_devices' => 'cn_template_devices',
				'known_devices' => 'cn_known_devices',
			],
			[
				'template_devices.tmp_id',
				'known_devices.dev_name'
			],
			[
				'template_devices.tmp_id' => $bannerIds
			],
			__METHOD__,
			[],
			[
				'known_devices' => [
					'INNER JOIN', 'template_devices.dev_id = known_devices.dev_id'
				]
			]
		);

		// Add devices to the data structure.
		foreach ( $dbRows as $dbRow ) {
			$bannerId = $dbRow->tmp_id;

			// Traverse the data structure to add in devices

			$assignmentKeysByCampaignId =
				$assignmentKeysByBannerIdAndCampaignId[$bannerId];

			foreach ( $assignmentKeysByCampaignId
				as $campaignId => $assignmentKeys
			) {
				foreach ( $assignmentKeys as $assignmentKey ) {
					$choices[$campaignId]['banners'][$assignmentKey]['devices'][] =
						$dbRow->dev_name;
				}

				// Ensure consistent ordering (see comment below)
				// FIXME: only sorting list for last assignmentKey?
				sort( $choices[$campaignId]['banners'][$assignmentKey]['devices'] );
			}
		}

		// Make arrays that are associative into plain indexed ones, since the
		// keys aren't used by the clients.
		// Also make very sure we don't have duplicate devices or countries.
		// Finally, ensure consistent ordering, since it's needed for
		// CNChoiceDataResourceLoaderModule for consistent RL module hashes.

		$choices = array_values( $choices );

		$uniqueDevFn = function ( $b ) {
			$b['devices'] = array_unique( $b['devices'] );
			return $b;
		};

		$compareNames = function ( $a, $b ) {
			if ( $a['name'] == $b['name'] ) {
				return 0;
			}
			return ( $a['name'] < $b['name'] ) ? -1 : 1;
		};

		$fixCampaignPropsFn = function ( $c ) use ( $uniqueDevFn, $compareNames ) {
			$c['banners'] = array_map( $uniqueDevFn, array_values( $c['banners'] ) );
			usort( $c['banners'], $compareNames );

			if ( $c['geotargeted'] ) {
				$c['countries'] = array_unique( $c['countries'] );
				sort( $c['countries'] );
			}

			return $c;
		};

		$choices = array_map( $fixCampaignPropsFn, $choices );
		usort( $choices, $compareNames );

		return $choices;
	}
}
