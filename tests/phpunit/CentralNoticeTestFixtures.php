<?php

use MediaWiki\Json\FormatJson;
use MediaWiki\User\User;

class CentralNoticeTestFixtures {
	private const FIXTURE_RELATIVE_PATH = 'data/AllocationsFixtures.json';

	/** @var array */
	public $spec = [];
	/** @var User */
	private $user;
	/** @var array */
	private $addedDeviceIds = [];
	/** @var array|null */
	private $knownDevices = null;

	/** @var array For legacy test that don't use fixture data: use exactly the api defaults where available */
	private static $defaultCampaign;
	/** @var array */
	private static $defaultBanner;

	public function __construct( User $user ) {
		$this->user = $user;

		static::$defaultCampaign = [
			'enabled' => 1,
			// inclusive comparison is used, so this does not cause a race condition.
			'startTs' => wfTimestamp( TS_MW ),
			'projects' => [ self::getDefaultProject() ],
			'languages' => [ self::getDefaultLanguage() ],
			'preferred' => CentralNotice::NORMAL_PRIORITY,
			'geotargeted' => 0,
			'countries' => [ self::getDefaultCountry() ],
			'regions' => [ self::getDefaultRegion() ],
			'throttle' => 100,
			'banners' => [],
		];
		static::$defaultBanner = [
			'bucket' => 0,
			'body' => 'testing',
			'display_anon' => true,
			'display_account' => true,
			'category' => 'fundraising',
			'weight' => 25,
		];
	}

	public static function getDefaultLanguage(): string {
		return 'en';
	}

	public static function getDefaultProject(): string {
		return 'wikipedia';
	}

	public static function getDefaultCountry(): string {
		return 'XX';
	}

	public static function getDefaultRegion(): string {
		return 'XX';
	}

	public static function getDefaultDevice(): string {
		return 'desktop';
	}

	/**
	 * Get an associative array with data for setting mock config variables
	 * as appropriate for fixture data.
	 * @return array
	 */
	public function getConfigsFromFixtureData() {
		$data = self::allocationsData();
		return $data['mock_config_values'];
	}

	/**
	 * Set up a test case as required for shared JSON data. Process the special
	 * start_days_from_now and end_days_from_now properties, ensure an empty
	 * countries property for non-geotargetted campaigns, and add dummy
	 * banner bodies.
	 *
	 * Test classes that call this method should also set config variables as per
	 * getConfigsFromFixtureData().
	 *
	 * @param array &$testCase A data structure with the test case specification
	 */
	public function setupTestCaseFromFixtureData( &$testCase ) {
		$this->setTestCaseStartEnd( $testCase );
		$this->preprocessSetupCountriesProp( $testCase['setup'] );
		$this->preprocessSetupRegionsProp( $testCase['setup'] );
		$this->addDummyBannerBody( $testCase['setup'] );
		$this->setupTestCase( $testCase['setup'] );
	}

	/**
	 * Set up a test case with defaults, for legacy tests that don't use the
	 * shared JSON fixture data.
	 *
	 * @param array $testCase A data structure with the test case specification
	 */
	public function setupTestCaseWithDefaults( $testCase ) {
		$this->addTestCaseDefaults( $testCase['setup'] );
		$this->setupTestCase( $testCase['setup'] );
	}

	/**
	 * Add defaults to the test case setup specification, for legacy tests that
	 * don't use the shared JSON fixture data.
	 *
	 * @param array &$testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	private function addTestCaseDefaults( &$testCaseSetup ) {
		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			$campaign = $campaign + static::$defaultCampaign + [
				'name' => 'TestCampaign_' . rand(),
			];

			foreach ( $campaign['banners'] as &$banner ) {
				$banner = $banner + static::$defaultBanner + [
					'name' => 'TestBanner_' . rand(),
				];
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
	 * @param array &$testCase A data structure with the test case specification
	 */
	private function setTestCaseStartEnd( &$testCase ) {
		$now = wfTimestamp();

		foreach ( $testCase['setup']['campaigns'] as &$campaign ) {
			$start = self::makeTimestamp(
				$now, $campaign['start_days_from_now']
			);

			$campaign['startTs'] = wfTimestamp( TS_MW, $start );

			$end = self::makeTimestamp(
				$now, $campaign['end_days_from_now']
			);

			$campaign['endTs'] = wfTimestamp( TS_MW, $end );
		}

		foreach ( $testCase['contexts_and_outputs'] as &$context_and_output ) {
			foreach ( $context_and_output['choices'] as &$choice ) {
				$choice['start'] = self::makeTimestamp(
					$now, $choice['start_days_from_now']
				);

				$choice['end'] = self::makeTimestamp(
					$now, $choice['end_days_from_now']
				);

				$choice['type'] = null;
				$choice['mixins'] = [];

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
	 * @param array &$testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	private function preprocessSetupCountriesProp( &$testCaseSetup ) {
		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			if ( !$campaign['geotargeted'] ) {
				if ( !isset( $campaign['countries'] ) ) {
					$campaign['countries'] = [];
				} else {
					throw new LogicException( "Campaign is not geotargetted but "
						. "'countries' property is set." );
				}
			}
		}
	}

	/**
	 * For test case setup, provide an empty array of regions for
	 * non-geotargeted campaigns, and check for mistakenly set regions for
	 * such campaigns.
	 *
	 * @param array &$testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	private function preprocessSetupRegionsProp( &$testCaseSetup ) {
		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			if ( !$campaign['geotargeted'] ) {
				if ( !isset( $campaign['regions'] ) ) {
					$campaign['regions'] = [];
				} else {
					throw new LogicException( "Campaign is not geotargetted but "
					. "'regions' property is set." );
				}
			}
		}
	}

	/**
	 * Add a dummy banner properties throughout a test case setup specification.
	 *
	 * @param array &$testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	private function addDummyBannerBody( &$testCaseSetup ) {
		foreach ( $testCaseSetup['campaigns'] as &$campaign ) {
			foreach ( $campaign['banners'] as &$banner ) {
				$banner['body'] = $banner['name'] . ' body';
			}
		}
	}

	/**
	 * Make a timestamp offset from the current time by a number of days.
	 *
	 * @param int $now Timestamp of the current time
	 * @param int $offsetInDays
	 * @return int
	 */
	private static function makeTimestamp( $now, $offsetInDays ) {
		return $now + ( 86400 * $offsetInDays );
	}

	/**
	 * Create campaigns and related banners according to a test case setup
	 * specification.
	 *
	 * @param array $testCaseSetup A data structure with the setup section of a
	 *  test case specification
	 */
	private function setupTestCase( $testCaseSetup ) {
		// It is expected that when a test case is set up via fixture data,
		// this global will already have been set via
		// setupTestCaseFromFixtureData(). Legacy (non-fixture data) tests don't
		// use this (but may be dependent on non-test config).
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
				$campaign['regions'],
				$campaign['throttle'],
				$campaign['preferred'],
				$this->user,
				// no campaign type assigned
				null
			);

			// Update notice end date only if that property was sent in.
			// It may not be there since it's not in the defaults
			// (used by legacy tests).
			if ( isset( $campaign['endTs'] ) ) {
				Campaign::updateNoticeDate( $campaign['name'],
					$campaign['startTs'], $campaign['endTs'] );
			}

			// bucket_count and archived are also only in test
			// fixture data, not legacy tests.
			if ( isset( $campaign['bucket_count'] ) ) {
				$bucket_count = $campaign['bucket_count'];

				if ( $bucket_count < 1 ||
					$bucket_count > $wgNoticeNumberOfBuckets
				) {
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
					$campaign['archived']
				);
			}

			foreach ( $campaign['banners'] as $bannerSpec ) {
				Banner::addBanner(
					$bannerSpec['name'],
					$bannerSpec['body'],
					$this->user,
					$bannerSpec['display_anon'],
					$bannerSpec['display_account'],
					[], [], null, null, false,
					$bannerSpec['category']
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
						$bannerSpec['bucket']
					);
				}

				if ( isset( $bannerSpec['devices'] ) ) {
					$devices = $bannerSpec['devices'];
					$this->ensureDevices( $devices );
					$bannerObj->setDevices( $devices );
					$bannerObj->save( $this->user );
				}
			}

			$this->spec['campaigns'][] = $campaign;
		}
	}

	public function tearDownTestCases() {
		if ( $this->spec ) {
			foreach ( $this->spec['campaigns'] as $campaign ) {
				foreach ( $campaign['banners'] as $banner ) {
					Campaign::removeTemplateFor( $campaign['name'], $banner['name'] );
					Banner::removeBanner( $banner['name'], $this->user );
				}

				Campaign::removeCampaign( $campaign['name'], $this->user );
			}
		}

		// Remove any devices we added
		if ( !empty( $this->addedDeviceIds ) ) {
			$dbw = CNDatabase::getPrimaryDb();
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'cn_known_devices' )
				->where( [ 'dev_id' => $this->addedDeviceIds ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Assert that two choices data structures are equal
	 *
	 * @param MediaWikiIntegrationTestCase $testClass
	 * @param array $expected Expected choices data structure
	 * @param array $actual Actual choices data structure
	 * @param string $message
	 */
	public function assertChoicesEqual( MediaWikiIntegrationTestCase $testClass, $expected, $actual,
		$message = ''
	) {
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
	private function deepMultisort( array &$a ) {
		array_multisort( $a );

		foreach ( $a as &$v ) {
			if ( is_array( $v ) ) {
				$this->deepMultisort( $v );
			}
		}
	}

	/**
	 * Ensure there is a known device called "desktop". This is a workaround
	 * for a hack (or maybe a hack for a workaround?) in Banner.
	 */
	private function ensureDesktopDevice() {
		$this->ensureDevices( [ 'desktop' ] );
	}

	/**
	 * Ensure that among the known devices in the database are all those named
	 * in $deviceNames.
	 *
	 * @param string[] $deviceNames
	 */
	private function ensureDevices( $deviceNames ) {
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

		// If necessary, update the in-memory list of available devices
		if ( $devicesChanged ) {
			$this->knownDevices = CNDeviceTarget::getAvailableDevices( true );
		}
	}

	// TODO refactor the next three method names

	/**
	 * Return an array containing arrays containing test cases, as needed for
	 * PHPUnit data provision. (Each inner array is a list of arguments for
	 * a test method.)
	 *
	 * @return array[]
	 */
	public static function allocationsTestCasesProvision() {
		$data = self::allocationsData();
		$dataForTests = [];

		foreach ( $data['test_cases'] as $name => $testCase ) {
			$dataForTests[] = [ $name, $testCase ];
		}

		return $dataForTests;
	}

	/**
	 * Return allocations data as a PHP array where each element is a different
	 * scenario for testing.
	 * @return array
	 */
	public static function allocationsData() {
		$json = self::allocationsDataAsJson();
		return FormatJson::decode( $json, true );
	}

	/**
	 * Return the raw JSON allocations data (from the file indicated by
	 * CentralNoticeTestFixtures::FIXTURE_RELATIVE_PATH).
	 * @return string
	 */
	public static function allocationsDataAsJson() {
		$path = __DIR__ . '/' . self::FIXTURE_RELATIVE_PATH;
		return file_get_contents( $path );
	}
}
