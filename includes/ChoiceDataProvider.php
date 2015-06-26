<?php

/***
 * Provides a set of campaign and banner choices based on allocations for a
 * given project and language combination.
 */
class ChoiceDataProvider {

	protected $project;
	protected $language;

	/**
	 * @param string $project The project to get choices for
	 * @param string $language The language to get choices for
	 */
	public function __construct( $project, $language ) {

		$this->project = $project;
		$this->language = $language;
	}

	/**
	 * Get a data structure with the allocation choices.
	 *
	 * @return array A structure of arrays. The outer array contains associative
	 *   arrays that represent campaigns. One campaign property is 'banners',
	 *   which has as its value an array of asociative arrays that represent
	 *   banners. Note that only some properties of campaigns and banners
	 *   are provided.
	 */
	public function getChoices() {
		// For speed, we'll do our own queries instead of using methods in
		// Campaign and Banner.

		$dbr = CNDatabase::getDb( DB_SLAVE );

		// Set up conditions
		$quotedNow = $dbr->addQuotes( $dbr->timestamp() );
		$conds = array(
			'notices.not_start <= ' . $quotedNow,
			'notices.not_end >= ' . $quotedNow,
			'notices.not_enabled' => 1,
			'notices.not_archived' => 0,
			'notice_projects.np_project' => $this->project,
			'notice_languages.nl_language' => $this->language
		);

		// Query campaigns and banners at once
		$dbRows = $dbr->select(
			array(
				'notices' => 'cn_notices',
				'assignments' => 'cn_assignments',
				'templates' => 'cn_templates',
				'notice_projects' => 'cn_notice_projects',
				'notice_languages' => 'cn_notice_languages',
			),
			array(
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
			),
			$conds,
			__METHOD__,
			array(),
			array(
				'assignments' => array(
					'INNER JOIN', 'notices.not_id = assignments.not_id'
				),
				'templates' => array(
					'INNER JOIN', 'assignments.tmp_id = templates.tmp_id'
				),
				'notice_projects' => array(
					'INNER JOIN', 'notices.not_id = notice_projects.np_notice_id'
				),
				'notice_languages' => array(
					'INNER JOIN', 'notices.not_id = notice_languages.nl_notice_id'
				)
			)
		);

		// Pare it down into a nicer data structure and prepare the next queries.
		// We'll create a structure with keys that are useful for piecing the
		// data together. But before returning it, we'll change associative
		// arrays to indexed ones at levels where the keys are not needed by the
		// client.
		$choices = array();
		$bannerIds = array();
		$assignmentKeysByBannerIdAndCampaignId = array();

		foreach ( $dbRows as $dbRow ) {

			$campaignId = $dbRow->not_id;
			$bannerId = $dbRow->tmp_id;
			$bucket = $dbRow->asn_bucket;

			// The first time we see any campaign, create the corresponding
			// outer K/V entry. The campaign-specific properties should be
			// repeated on every row for any campaign. Note that these
			// keys don't make it into data structure we return.
			if ( !isset ( $choices[$campaignId] ) ) {
				$choices[$campaignId] = array(
					'name' => $dbRow->not_name,
					'start' => intval( wfTimestamp( TS_UNIX, $dbRow->not_start ) ),
					'end' => intval( wfTimestamp( TS_UNIX, $dbRow->not_end ) ),
					'preferred' => intval( $dbRow->not_preferred ),
					'throttle' => intval( $dbRow->not_throttle ),
					'bucket_count' => intval( $dbRow->not_buckets ),
					'geotargeted' => (bool) $dbRow->not_geo,
					'banners' => array()
				);
			}

			// A temporary assignment key so we can get back to this part of the
			// data structure quickly and add in devices.
			$assignmentKey = $bannerId . ':' . $bucket;

			$choices[$campaignId]['banners'][$assignmentKey] = array(
				'name' => $dbRow->tmp_name,
				'bucket' => intval( $bucket ),
				'weight' => intval( $dbRow->tmp_weight ),
				'category' => $dbRow->tmp_category,
				'display_anon' => (bool) $dbRow->tmp_display_anon,
				'display_account' => (bool) $dbRow->tmp_display_account,
				'devices' => array() // To be filled by the last query
			);

			$bannerIds[] = $bannerId;

			// Add to the index so we can get back here.
			// Note that PHP creates arrays here as needed.
			$assignmentKeysByBannerIdAndCampaignId[$bannerId][$campaignId][] =
				$assignmentKey;
		}

		// If there's nothing, return the empty array now
		if ( count ( $choices ) === 0 ) {
			return $choices;
		}

		// Fetch countries.
		// We have to eliminate notices that are not geotargeted, since they
		// may have residual data in the cn_notice_countries table.
		$dbRows = $dbr->select(
			array(
				'notices' => 'cn_notices',
				'notice_countries' => 'cn_notice_countries',
			),
			array(
				'notices.not_id',
				'notice_countries.nc_country'
			),
			array (
				'notices.not_geo' => 1,
				'notices.not_id' => array_keys( $choices )
			),
			__METHOD__,
			array(),
			array(
				'notice_countries' => array(
					'INNER JOIN', 'notices.not_id = notice_countries.nc_notice_id'
				)
			)
		);

		// Add countries to our data structure.
		// Note that PHP creates an empty array for countries as needed.
		foreach ( $dbRows as $dbRow ) {
			$choices[$dbRow->not_id]['countries'][] = $dbRow->nc_country;
		}

		// Add campaign-asociated mixins to the data structure
		foreach ( $choices as &$campaignInfo ) {

			//Get info for enabled mixins for this campaign
			$campaignInfo['mixins'] =
				Campaign::getCampaignMixins( $campaignInfo['name'], true );
		}

		// Fetch the devices
		$dbRows = $dbr->select(
			array(
				'template_devices' => 'cn_template_devices',
				'known_devices' => 'cn_known_devices',
			),
			array(
				'template_devices.tmp_id',
				'known_devices.dev_name'
			),
			array(
				'template_devices.tmp_id' => $bannerIds
			),
			__METHOD__,
			array(),
			array(
				'known_devices' => array(
					'INNER JOIN', 'template_devices.dev_id = known_devices.dev_id'
				)
			)
		);

		// Add devices to the data structure.
		foreach ( $dbRows as $dbRow ) {

			$bannerId = $dbRow->tmp_id;

			// Traverse the data structure to add in devices

			$assignmentKeysByCampaignId =
				$assignmentKeysByBannerIdAndCampaignId[$bannerId];

			foreach ( $assignmentKeysByCampaignId
				as $campaignId => $assignmentKeys ) {

				foreach ( $assignmentKeys as $assignmentKey ) {
					$choices[$campaignId]['banners'][$assignmentKey]['devices'][] =
						$dbRow->dev_name;
				}
			}
		}

		// Make arrays that are associative into plain indexed ones, since the
		// keys aren't used by the clients.
		// Also make very sure we don't have duplicate devices or countries.

		$choices = array_values( $choices );

		$uniqueDevFn = function ( $b ) {
			$b['devices'] = array_unique( $b['devices'] );
			return $b;
		};

		$fixCampaignPropsFn = function ( $c ) use ( $uniqueDevFn ) {

			$c['banners'] = array_map( $uniqueDevFn, array_values( $c['banners'] ) );

			if ( $c['geotargeted'] ) {
				$c['countries'] = array_unique( $c['countries'] );
			}

			return $c;
		};

		$choices = array_map( $fixCampaignPropsFn, $choices );

		return $choices;
	}
}
