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
			'languages' => array( CentralNoticeTestFixtures::getDefaultLanguage() ),
			'preferred' => CentralNotice::NORMAL_PRIORITY,
			'geotargeted' => 0,
			'countries' => array( CentralNoticeTestFixtures::getDefaultCountry() ),
			'throttle' => 100,
			'banners' => array(),
		);
		static::$defaultBanner = array(
			'bucket' => 0,
			'body' => 'testing',
			'display_anon' => true,
			'display_account' => true,
			'fundraising' => 1,
			'autolink' => 0,
			'landingPages' => 'JA1, JA2',
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
	 * Set up a test case as required for shared JSON data. Process the special
	 * start_days_from_now and end_days_from_now properties, ensure an empty
	 * countries property for non-geotargetted campaigns, and add dummy
	 * banner bodies.
	 *
	 * @param array $testCase A data structure with the test case specification
	 */
	function setupTestCaseFromFixtureData( &$testCase ) {
		$this->setTestCaseStartEnd( $testCase );
		$this->preprocessSetupCountriesProp( $testCase['setup'] );
		$this->addDummyBannerBody( $testCase['setup'] );
		$this->setupTestCase( $testCase['setup'] );
	}

	/**
	 * Set up a test case with defaults, for legacy tests that don't use the
	 * shared JSON fixture data.
	 *
	 * @param array $testCase A data structure with the test case specification
	 */
	function setupTestCaseWithDefaults( $testCase ) {
		$this->addTestCaseDefaults( $testCase['setup'] );
		$this->setupTestCase( $testCase['setup'] );
	}

	/**
	 * Add defaults to the test case setup specification, for legacy tests that
	 * don't use the shared JSON fixture data. 
	 *
	 * @param array $testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	protected function addTestCaseDefaults( &$testCaseSetup ) {

		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			$campaign = $campaign + static::$defaultCampaign + array(
					'name' => 'TestCampaign_' . rand(),
			);

			foreach ( $campaign['banners'] as &$banner ) {
				$banner = $banner + static::$defaultBanner + array(
						'name' => 'TestBanner_' . rand(),
				);
			}
		}
	}

	/**
	 * Set campaign start and end times for test case fixtures using the
	 * start_days_from_now and end_days_from_now properties.
	 *
	 * Note: this logic is repeated in client-side tests.
	 * @see setTestCaseStartEnd() in bannerController.lib.tests.js
	 *
	 * @param array $testCase A data structure with the test case specification
	 */
	protected function setTestCaseStartEnd( &$testCase ) {

		$now = wfTimestamp();

		foreach ( $testCase['setup']['campaigns'] as &$campaign ) {

			$start = CentralNoticeTestFixtures::makeTimestamp(
					$now, $campaign['start_days_from_now'] );

			$campaign['startTs'] = wfTimestamp( TS_MW, $start );

			$end = CentralNoticeTestFixtures::makeTimestamp(
					$now, $campaign['end_days_from_now'] );

			$campaign['endTs'] = wfTimestamp( TS_MW, $end );
		}

		foreach ( $testCase['choices'] as &$choice ) {

			$choice['start'] = CentralNoticeTestFixtures::makeTimestamp(
					$now, $choice['start_days_from_now'] );

			$choice['end'] = CentralNoticeTestFixtures::makeTimestamp(
					$now, $choice['end_days_from_now'] );

			// Unset these special properties from choices, for tests that
			// compare fixture choices to actual choices produced by the code
			// under test.
			unset( $choice['start_days_from_now'] );
			unset( $choice['end_days_from_now'] );
		}
	}

	/**
	 * For test case setup, provide an empty array of countries for
	 * non-geotargeted campaigns, and check for mistakenly set countries for
	 * such campaigns.
	 *
	 * @param array $testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	protected function preprocessSetupCountriesProp( &$testCaseSetup ) {

		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {

			if ( !$campaign['geotargeted'] ) {
				if ( !isset( $campaign['countries'] ) ) {
					$campaign['countries'] = array();
				} else {
					throw new MWException( "Campaign is not geotargetted but "
							. "'countries' property is set." );
				}
			}
		}
	}

	/**
	 * Add dummy banner properties throughout a test case setup specification. 
	 *
	 * @param array $testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	protected function addDummyBannerBody( &$testCaseSetup ) {
		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			foreach ( $campaign['banners'] as &$banner ) {
				$banner['body'] = $banner['name'] . ' body';
			}
		}
	}

	/**
	 * Make a timestamp offset from the current time by a number of days.
	 *
	 * @param MW_TS $now Timestamp of the current time
	 * @param unknown $offsetInDays
	 * @return MW_TS
	 */
	protected static function makeTimestamp( $now, $offsetInDays ) {
		return $now + ( 86400 * $offsetInDays );
	}

	/**
	 * Create campaigns and related banners according to a test case setup
	 * specification.
	 *
	 * @param array $testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	protected function setupTestCase( $testCaseSetup ) {
		$this->ensureDesktopDevice();

		foreach ( $testCaseSetup['campaigns'] as $campaign ) {

			$campaign['id'] = Campaign::addCampaign(
				$campaign['name'],
				$campaign['enabled'],
				$campaign['startTs'],
				$campaign['projects'],
				$campaign['languages'],
				$campaign['geotargeted'],
				$campaign['countries'],
				$campaign['throttle'],
				$campaign['preferred'],
				$this->user
			);

			// Update notice end date only if that property was sent in.
			// It may not be there since it's not in the defaults
			// (used by legacy tests).
			if ( isset( $campaign['endTs'] ) ) {
				Campaign::updateNoticeDate( $campaign['name'],
					$campaign['startTs'], $campaign['endTs'] );
			}

			// autolink and landingPage properties are not relevant to tests set
			// up via fixture data and may not be provided. For those
			// parameters, in that case, provide the default value in the
			// Banner::addTemplate method signature.
			foreach ( $campaign['banners'] as $bannerSpec ) {
				Banner::addTemplate(
					$bannerSpec['name'],
					$bannerSpec['body'],
					$this->user,
					$bannerSpec['display_anon'],
					$bannerSpec['display_account'],
					$bannerSpec['fundraising'],
					isset( $bannerSpec['autolink'] ) ? $bannerSpec['autolink'] : 0,
					isset( $bannerSpec['landingPages'] ) ? $bannerSpec['landingPages'] : ''
				);

				Campaign::addTemplateTo(
					$campaign['name'],
					$bannerSpec['name'],
					$bannerSpec['weight']
				);

				$bannerObj = Banner::fromName( $bannerSpec['name'] );
				
				if ( isset( $bannerSpec['bucket'] ) ) {
					Campaign::updateBucket(
						$campaign['name'],
						$bannerObj->getId(),
						$bannerSpec['bucket'] );
				}

				if ( isset( $bannerSpec['devices'] ) ) {
					$bannerObj->setDevices( $bannerSpec['devices'] );
					$bannerObj->save();
				}
			}

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

		foreach  ( $data['test_cases'] as $name => $testCase ) {
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
