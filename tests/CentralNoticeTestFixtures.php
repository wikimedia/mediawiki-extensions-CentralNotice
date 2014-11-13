<?php

class CentralNoticeTestFixtures {
	public $spec = array();
	protected $user;
	protected $fixtureDeviceId;

	// Use exactly the api defaults where available
	static public $defaultCampaign;
	static public $defaultBanner;

	function __construct() {
		$this->user = User::newFromName( 'UTSysop' );

		static::$defaultCampaign = array(
			'enabled' => 1,
			// inclusive comparison is used, so this does not cause a race condition.
			'startTs' => wfTimestamp( TS_MW ),
			'projects' => array( CentralNoticeTestFixtures::getDefaultProject() ),
			'project_languages' => array( CentralNoticeTestFixtures::getDefaultLanguage() ),
			'preferred' => CentralNotice::NORMAL_PRIORITY,
			'geotargetted' => 0,
			'geo_countries' => array( CentralNoticeTestFixtures::getDefaultCountry() ),
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

	static function getDefaultLanguage() {
		return ApiCentralNoticeAllocations::DEFAULT_LANGUAGE;
	}

	static function getDefaultProject() {
		return ApiCentralNoticeAllocations::DEFAULT_PROJECT;
	}

	static function getDefaultCountry() {
		return ApiCentralNoticeAllocations::DEFAULT_COUNTRY;
	}

	static function getDefaultDevice() {
		return 'desktop';
	}

	function addFixtures( $spec ) {
		$this->ensureDesktopDevice();

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

		if ( $this->fixtureDeviceId ) {
			$dbw = CNDatabase::getDb( DB_MASTER );
			$dbw->delete(
				'cn_known_devices',
				array( 'dev_id' => $this->fixtureDeviceId ),
				__METHOD__
			);
		}
	}

	protected function getDesktopDevice() {
		$dbr = CNDatabase::getDb();

		$res = $dbr->select(
			array(
				 'cn_known_devices'
			),
			array(
				'dev_id',
				'dev_name'
			),
			array(
				'dev_name' => 'desktop',
			)
		);
		$ids = array();
		foreach ( $res as $row ) {
			$ids[] = $row->dev_id;
		}
		return $ids;
	}

	protected function ensureDesktopDevice() {
		$ids = $this->getDesktopDevice();
		if ( !$ids ) {
			CNDeviceTarget::addDeviceTarget( 'desktop', '{{int:centralnotice-devicetype-desktop}}' );
			$ids = $this->getDesktopDevice();
			$this->fixtureDeviceId = $ids[0];
		}
	}

	static function allocationsData() {
		return array(
			CentralNoticeTestFixtures::completenessScenario(),
			CentralNoticeTestFixtures::throttlingScenario(),
			CentralNoticeTestFixtures::overallocationScenario(),
			CentralNoticeTestFixtures::blanksScenario(),
			CentralNoticeTestFixtures::priorityScenario(),
			CentralNoticeTestFixtures::geoInCountryScenario(),
			CentralNoticeTestFixtures::geoNotInCountryScenario(),
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
							'XX',
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
						'XX',
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
			//alloc
			array(
				'b1' => '0.500',
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
			//alloc
			array(
				'c1b1' => '0.300',
				'c1b2' => '0.300',
				'c2b1' => '0.200',
				'c2b2' => '0.200',
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
			//alloc
			array(
				'b1' => '0.024',
				'b2' => '0.488',
				'b3' => '0.488',
			),
		);
	}

	static function blanksScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'throttle' => 10,
						'banners' => array(
							array(
								'name' => 'b1',
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'banners' => array(
						array(
							'name' => 'b1',
						),
					),
				),
			),
			//alloc
			array(
				'b1' => 0.100,
			),
		);
	}

	static function priorityScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'name' => 'c1',
						'preferred' => CentralNotice::LOW_PRIORITY,
						'banners' => array(
							array(
								'name' => 'c1b1',
							),
						),
					),
					array(
						'name' => 'c2',
						'preferred' => CentralNotice::NORMAL_PRIORITY,
						'banners' => array(
							array(
								'name' => 'c2b1',
							),
						),
					),
				),
			),
			// expected
			array(
				array(
					'name' => 'c1',
				),
				array(
					'name' => 'c2',
				),
			),
			//alloc
			array(
				'c1b1' => 0.000,
				'c2b1' => 1.000,
			),
		);
	}

	static function geoInCountryScenario() {
		return array(
			// input
			array(
				'campaigns' => array(
					array(
						'geotargetted' => true,
						'geo_countries' => array(
							'FR',
							'XX',
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
						'XX',
					),
					'banners' => array(
						array(
							'name' => 'b1',
							'weight' => 5,
						),
					),
				),
			),
			//alloc
			array(
				'b1' => 1.000,
			),
		);
	}

	static function geoNotInCountryScenario() {
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
			//alloc
			array(
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
			//alloc
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
			//alloc
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
			//alloc
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
			//alloc
			array(
			),
		);
	}
}
