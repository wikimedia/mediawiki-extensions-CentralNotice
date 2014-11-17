<?php

class CentralNoticeTestFixtures {
	const FIXTURE_RELATIVE_PATH = 'data/AllocationsFixtures.json';

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
		$path = __DIR__ . '/' . CentralNoticeTestFixtures::FIXTURE_RELATIVE_PATH;
		$json = file_get_contents( $path );
		$data = FormatJson::decode( $json, true );

		return $data;
	}
}
