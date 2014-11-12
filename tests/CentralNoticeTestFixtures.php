<?php

class CentralNoticeTestFixtures {
	public $spec = array();
	protected $user;

	// Use exactly the api defaults where available
	static public $defaultCampaign;
	static public $defaultBanner;
	
	function __construct() {
		$this->user = User::newFromName( 'UTSysop' );

		static::$defaultCampaign = array(
			'enabled' => 1,
			// inclusive comparison is used, so this does not cause a race condition.
			'startTs' => wfTimestamp( TS_MW ),
			'projects' => array( ApiCentralNoticeAllocations::DEFAULT_PROJECT ),
			'project_languages' => array( ApiCentralNoticeAllocations::DEFAULT_LANGUAGE ),
			'preferred' => CentralNotice::NORMAL_PRIORITY,
			'geotargetted' => 0,
			'geo_countries' => array( ApiCentralNoticeAllocations::DEFAULT_COUNTRY ),
			'throttle' => 100,
			'banners' => array(),
		);
		static::$defaultBanner = array(
			'body' => 'testing',
			'displayAnon' => ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS === 'true',
			'displayAccount' => ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS === 'false',
			'fundraising' => 1,
			'autolink' => 0,
			'landingPages' => 'JA1, JA2',
			'campaign_z_index' => 0,
			'weight' => 25,
		);
	}

	function addFixtures( $spec ) {
		CNDeviceTarget::addDeviceTarget( 'desktop', '{{int:centralnotice-devicetype-desktop}}' );

		foreach ( $spec['campaigns'] as $campaignSpec ) {
			$campaign = $campaignSpec + static::$defaultCampaign + array(
				'name' => 'TestCampaign_' . rand(),
			);

			$campaign['id'] = Campaign::addCampaign(
				$campaign['name'],
				$campaign['enabled'],
				$campaign['startTs'],
				$campaign['projects'],
				$campaign['project_languages'],
				$campaign['geotargetted'],
				$campaign['geo_countries'],
				$campaign['throttle'],
				$campaign['preferred'],
				$this->user
			);

			$banners = array();
			foreach ( $campaign['banners'] as $bannerSpec ) {
				$banner = $bannerSpec + static::$defaultBanner + array(
					'name' => 'TestBanner_' . rand(),
				);

				Banner::addTemplate(
					$banner['name'],
					$banner['body'],
					$this->user,
					$banner['displayAnon'],
					$banner['displayAccount'],
					$banner['fundraising'],
					$banner['autolink'],
					$banner['landingPages']
				);
				Campaign::addTemplateTo(
					$campaign['name'],
					$banner['name'],
					$banner['weight']
				);

				$banners[] = $banner;
			}
			$campaign['banners'] = $banners;

			$this->spec['campaigns'][] = $campaign;
		}
	}

	function removeFixtures() {
		if ( $this->spec ) {
			foreach ( $this->spec['campaigns'] as $campaign ) {
				foreach ( $campaign['banners'] as $banner ) {
					Campaign::removeTemplateFor( $campaign['name'], $banner['name'] );
					Banner::removeTemplate( $banner['name'], $this->user );
				}

				Campaign::removeCampaign( $campaign['name'], $this->user );
			}
		}
	}

	static function allocationsData() {
		return array(
			CentralNoticeTestFixtures::completenessScenario(),
			CentralNoticeTestFixtures::throttlingScenario(),
			CentralNoticeTestFixtures::overallocationScenario(),
			//CentralNoticeTestFixtures::blanksScenario(),
			//CentralNoticeTestFixtures::priorityScenario(),
			CentralNoticeTestFixtures::geoInUsaScenario(),
			CentralNoticeTestFixtures::geoNotInUsaScenario(),
			CentralNoticeTestFixtures::notInProjectScenario(),
			CentralNoticeTestFixtures::notInLanguageScenario(),
			CentralNoticeTestFixtures::notInTimeWindowScenario(),
			CentralNoticeTestFixtures::notAnonymousScenario(),
		);
	}

	static function completenessScenario() {
		$startTs = wfTimestamp( TS_MW );
		$endTime = strtotime( '+1 hour', wfTimestamp( TS_UNIX, $startTs ) );
		$endTs = wfTimestamp( TS_MW, $endTime );

		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'category' => 'fundraising',
						'preferred' => CentralNotice::NORMAL_PRIORITY,
						'throttle' => 50,
						'startTs' => $startTs,
						'geotargetted' => true,
						'geo_countries' => array(
							'FR',
							'GR',
						),
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'preferred' => 1,
					'throttle' => 50,
					'bucket_count' => 1,
					'geotargetted' => true,
					'start' => $startTs,
					'end' => $endTs,
					'countries' => array(
						'FR',
						'GR',
					),
					'banners' => array(
						array(
							'name' => 'b1',
							'weight' => 5,
							'bucket' => 0,
							'category' => 'fundraising',
							'devices' => array(
								'desktop',
							),
						),
					),
				),
			),
		);
	}

	static function throttlingScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'name' => 'c1',
						'preferred' => CentralNotice::NORMAL_PRIORITY,
						'throttle' => 60,
						'banners' => array(
							array( 'name' => 'c1b1' ),
							array( 'name' => 'c1b2' ),
						),
					),
					array(
						'name' => 'c2',
						'preferred' => CentralNotice::LOW_PRIORITY,
						'throttle' => 100,
						'banners' => array(
							array( 'name' => 'c2b1' ),
							array( 'name' => 'c2b2' ),
						),
					),
				),
			),
			// expected output
			array(
				array(
					'name' => 'c1',
					'throttle' => 60,
					'geotargetted' => false,
					'banners' => array(
						array (
							'name' => 'c1b1',
							'weight' => 25,
							'category' => 'fundraising',
						),
						array (
							'name' => 'c1b2',
							'weight' => 25,
							'category' => 'fundraising',
						),
					),
				),
				array(
					'name' => 'c2',
					'banners' => array(
						array (
							'name' => 'c2b1',
							'weight' => 25,
							'category' => 'fundraising',
						),
						array (
							'name' => 'c2b2',
							'weight' => 25,
							'category' => 'fundraising',
						),
					),
				),
			),
		);
	}

	static function overallocationScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
							array(
								'name' => 'b2',
								'weight' => 100,
							),
							array(
								'name' => 'b3',
								'weight' => 100,
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'throttle' => 100,
					'banners' => array(
						array(
							'name' => 'b1',
							'weight' => 5,
						),
						array(
							'name' => 'b2',
							'weight' => 100,
						),
						array(
							'name' => 'b3',
							'weight' => 100,
						),
					),
				),
			),
		);
	}

	// FIXME: unused
	static function blanksScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'throttle' => 10,
						'banners' => array(
							array(),
						),
					),
				),
			),
			// expected
			array(
				array(
					'slots' => 3,
				),
			)
		);
	}

	// FIXME: unused
	static function priorityScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'name' => 'c1',
						'preferred' => CentralNotice::LOW_PRIORITY,
						'banners' => array(
							array(),
						),
					),
					array(
						'name' => 'c2',
						'preferred' => CentralNotice::NORMAL_PRIORITY,
						'banners' => array(
							array(),
						),
					),
				),
			),
			// expected
			array(
				array(
					'campaign' => 'c1',
					'slots' => 0,
				),
				array(
					'campaign' => 'c2',
					'slots' => 30,
				),
			),
		);
	}

	static function geoInUsaScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'geotargetted' => true,
						'geo_countries' => array(
							'FR',
							'US',
						),
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'geotargetted' => true,
					'countries' => array(
						'FR',
						'US',
					),
					'banners' => array(
						array(
							'name' => 'b1',
							'weight' => 5,
						),
					),
				),
			),
		);
	}

	static function geoNotInUsaScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'geotargetted' => true,
						'geo_countries' => array(
							'FR',
							'GR',
						),
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'geotargetted' => true,
					'countries' => array(
						'FR',
						'GR',
					),
					'banners' => array(
						array(
							'name' => 'b1',
							'weight' => 5,
						),
					),
				),
			),
		);
	}

	static function notInProjectScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'projects' => array( 'wikisource' ),
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
			),
		);
	}

	static function notInLanguageScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'project_languages' => array( 'zh' ),
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
			),
		);
	}

	static function notInTimeWindowScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'startTs' => '20010101123456',
						'banners' => array(
							array(
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
			),
		);
	}

	static function notAnonymousScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'banners' => array(
							array(
								'displayAnon' => false,
								'displayAccount' => true,
								'name' => 'b1',
								'weight' => 5,
							),
						),
					),
				),
			),
			// expected
			array(
			),
		);
	}
}
