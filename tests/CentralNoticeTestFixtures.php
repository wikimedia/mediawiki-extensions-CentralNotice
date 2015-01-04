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
			'geotargeted' => 0,
			'geo_countries' => array( CentralNoticeTestFixtures::getDefaultCountry() ),
			'throttle' => 100,
			'banners' => array(),
		);
		static::$defaultBanner = array(
			'body' => 'testing',
			'displayAnon' => true,
			'displayAccount' => true,
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

	/**
	 *
	 * @see bannerController.lib.tests.js
	 *
	 * @param TS_UNIX $testCase
	 */
	function prepareTestcase( &$testCase ) {
		$now = wfTimestamp();

		foreach ( $testCase['setup']['campaigns'] as &$campaign ) {

			$start = CentralNoticeTestFixtures::makeTimestamp(
				$now, $campaign['startDaysFromNow'] );

			$campaign['startTs'] = wfTimestamp( TS_MW, $start );

			$end = CentralNoticeTestFixtures::makeTimestamp(
					$now, $campaign['endDaysFromNow'] );

			$campaign['endTs'] = wfTimestamp( TS_MW, $end );
		}

		foreach ( $testCase['choices'] as &$choice ) {

			$choice['start'] = CentralNoticeTestFixtures::makeTimestamp(
					$now, $choice['startDaysFromNow'] );

			$choice['end'] = CentralNoticeTestFixtures::makeTimestamp(
					$now, $choice['endDaysFromNow'] );

			// Unset these special properties from choices, for tests that
			// compare fixture choices to actual choices produced by the code
			// under test.
			unset( $choice['startDaysFromNow'] );
			unset( $choice['endDaysFromNow'] );
		}
	}

	private static function makeTimestamp( $now, $offsetInDays ) {
		return $now + ( 86400 * $offsetInDays );
	}

	function setupTestCase( $spec ) {
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
				$campaign['geotargeted'],
				$campaign['geo_countries'],
				$campaign['throttle'],
				$campaign['preferred'],
				$this->user
			);

			// Update notice end date only if that property was sent in.
			// It may not be there since it's not in the defaults; not adding
			// since defaults will soon be removed (for json-based test
			// fixtures).
			if ( isset( $campaign['endTs'] ) ) {
				Campaign::updateNoticeDate( $campaign['name'],
					$campaign['startTs'], $campaign['endTs'] );
			}

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

				// FIXME Can't test buckets other than 0, the (!) default
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

	function tearDownTestCases() {
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

	//FIXME review, possibly trim and/or document device-related stuff here
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

	/**
	 * Return an array containing arrays containing test cases, as needed for
	 * PHPUnit data provision. (Each inner array is a list of arguments for
	 * a test method.)
	 */
	public static function allocationsTestCasesProvision() {

		$data = CentralNoticeTestFixtures::allocationsData();
		$dataForTests = array();

		foreach  ( $data['testCases'] as $name => $testCase ) {
			$dataForTests[] = array( $name, $testCase );
		}

		return $dataForTests;
	}

	/**
	 * Return allocations data as a PHP array where each element is a different
	 * scenario for testing.
	 */
	public static function allocationsData() {
		$json = CentralNoticeTestFixtures::allocationsDataAsJson();
		$data = FormatJson::decode( $json, true );
		return $data;
	}

	/**
	 * Return the raw JSON allocations data (from the file indicated by
	 * CentralNoticeTestFixtures::FIXTURE_RELATIVE_PATH).
	 */
	public static function allocationsDataAsJson() {
		$path = __DIR__ . '/' . CentralNoticeTestFixtures::FIXTURE_RELATIVE_PATH;
		return file_get_contents( $path );
	}
}
