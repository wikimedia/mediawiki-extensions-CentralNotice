<?php

class CentralNoticeTestFixtures {
	const FIXTURE_RELATIVE_PATH = 'data/AllocationsFixtures.json';

	public $spec = array();
	protected $user;
	protected $addedDeviceIds = array();
	protected $knownDevices = null;

	// For legacy test that don't use fixture data: use exactly the api defaults
	// where available
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
			'weight' => 25,
		);
	}

	static function getDefaultLanguage() {
		return 'en';
	}

	static function getDefaultProject() {
		return 'wikipedia';
	}

	static function getDefaultCountry() {
		return 'XX';
	}

	static function getDefaultDevice() {
		return 'desktop';
	}

	/**
	 * Get an associative array with data for setting mock global variables
	 * as appropriate for fixture data.
	 */
	function getGlobalsFromFixtureData() {
		$data = CentralNoticeTestFixtures::allocationsData();
		return $data['mock_config_values'];
	}

	/**
	 * Set up a test case as required for shared JSON data. Process the special
	 * start_days_from_now and end_days_from_now properties, ensure an empty
	 * countries property for non-geotargetted campaigns, and add dummy
	 * banner bodies.
	 *
	 * Test classes that call this method should also set MW globals as per
	 * getGlobalsFromFixtureData().
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
	 * Note: Some of this logic is repeated in client-side tests.
	 * @see setChoicesStartEnd() in ext.centralNotice.display.chooser.tests.js
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

		foreach ( $testCase['contexts_and_outputs'] as &$context_and_output ) {
			foreach ( $context_and_output['choices'] as &$choice ) {

				$choice['start'] = CentralNoticeTestFixtures::makeTimestamp(
						$now, $choice['start_days_from_now'] );

				$choice['end'] = CentralNoticeTestFixtures::makeTimestamp(
						$now, $choice['end_days_from_now'] );

				$choice['mixins'] = array();

				// Unset these special properties from choices, for tests that
				// compare fixture choices to actual choices produced by the code
				// under test.
				unset( $choice['start_days_from_now'] );
				unset( $choice['end_days_from_now'] );
			}
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
					throw new LogicException( "Campaign is not geotargetted but "
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

		// It is expected that when a test case is set up via fixture data,
		// this global will already have been set via
		// setupTestCaseFromFixtureData(). Legacy (non-fixture data) tests don't
		// use this (but may be dependant on non-test config).
		global $wgNoticeNumberOfBuckets;

		// Needed due to hardcoded default desktop device hack in Banner
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

			// bucket_count and archived  are also only in test
			// fixture data, not legacy tests.
			if ( isset( $campaign['bucket_count'] ) ) {

				$bucket_count = $campaign['bucket_count'];

				if ( $bucket_count < 1 ||
					$bucket_count > $wgNoticeNumberOfBuckets ) {
						throw new RangeException( 'Bucket count out of range.' );
				}

				Campaign::setNumericCampaignSetting(
					$campaign['name'],
					'buckets',
					$bucket_count,
					$wgNoticeNumberOfBuckets + 1,
					1
				);
			}

			if ( isset( $campaign['archived'] ) ) {
				Campaign::setBooleanCampaignSetting(
					$campaign['name'],
					'archived',
					$campaign['archived'] ? 1 : 0
				);
			}

			foreach ( $campaign['banners'] as $bannerSpec ) {
				Banner::addTemplate(
					$bannerSpec['name'],
					$bannerSpec['body'],
					$this->user,
					$bannerSpec['display_anon'],
					$bannerSpec['display_account'],
					$bannerSpec['fundraising']
				);

				Campaign::addTemplateTo(
					$campaign['name'],
					$bannerSpec['name'],
					$bannerSpec['weight']
				);

				$bannerObj = Banner::fromName( $bannerSpec['name'] );
				
				if ( isset( $bannerSpec['bucket'] ) ) {

					$bucket = $bannerSpec['bucket'];
					if ( $bucket < 0 || $bucket > $wgNoticeNumberOfBuckets ) {
						throw new RangeException( 'Bucket out of range' );
					}

					Campaign::updateBucket(
						$campaign['name'],
						$bannerObj->getId(),
						$bannerSpec['bucket'] );
				}

				if ( isset( $bannerSpec['devices'] ) ) {
					$devices = $bannerSpec['devices'];
					$this->ensureDevices( $devices );
					$bannerObj->setDevices( $devices );
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

		// Remove any devices we added
		if ( !empty( $this->addedDeviceIds ) ) {
			$dbw = CNDatabase::getDb( DB_MASTER );
			$dbw->delete(
				'cn_known_devices',
				array( 'dev_id' => $this->addedDeviceIds ),
				__METHOD__
			);
		}
	}

	/**
	 * Assert that two choices data structures are equal
	 *
	 * @param MediaWikiTestCase $testClass
	 * @param array $expected Expected choices data structure
	 * @param array $actual Actual choices data structure
	 */
	function assertChoicesEqual( MediaWikiTestCase $testClass, $expected, $actual,
			$message='' ) {

		// The order of the numerically indexed arrays in this data structure
		// shouldn't matter, so sort all of those by value.
		$this->deepMultisort( $expected );
		$this->deepMultisort( $actual );

		$testClass->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Convenience method used to compare choice data. Ensures that in a data
	 * structure, numerically indexed arrays are sorted by value.
	 * (If $a is a numerically indexed array, sort it by value. Traverse the
	 * array recursively and do the same for each value.)
	 */
	protected function deepMultisort( array &$a ) {
		array_multisort( $a );

		foreach ( $a as &$v ) {
			if ( is_array ( $v ) ) {
				$this->deepMultisort( $v );
			}
		}
	}

	/**
	 * Ensure there is a known device called "desktop". This is a workaround
	 * for a hack (or maybe a hack for a workaround?) in Banner.
	 */
	protected function ensureDesktopDevice() {
		$this->ensureDevices( array( 'desktop' ) );
	}

	/**
	 * Ensure that among the known devices in the database are all those named
	 * in $deviceNames.
	 *
	 * @param string[] $deviceNames
	 */
	protected function ensureDevices( $deviceNames ) {

		if ( !$this->knownDevices ) {
			$this->knownDevices = CNDeviceTarget::getAvailableDevices( true );
		}

		$devicesChanged = false;

		// Add any devices not in the database
		foreach ( $deviceNames as $deviceName ) {
			if ( !isset( $this->knownDevices[$deviceName] ) ) {

				// Remember the IDs for teardown
				$this->addedDeviceIds[] =
					CNDeviceTarget::addDeviceTarget( $deviceName, $deviceName );
				$devicesChanged = true;
			}
		}

		// If necessary, update in-memory list of available devices
		if ( $devicesChanged ) {
			$this->knownDevices = CNDeviceTarget::getAvailableDevices( true );
		}
	}

	// TODO refactor the next three method names

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
