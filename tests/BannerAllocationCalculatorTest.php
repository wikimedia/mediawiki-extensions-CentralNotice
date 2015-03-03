<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class BannerAllocationCalculatorTest extends MediaWikiTestCase {
	/** @var CentralNoticeTestFixtures */
	protected $cnFixtures;

	protected function setUp() {
		parent::setUp();
		$this->cnFixtures = new CentralNoticeTestFixtures();
		$this->setMwGlobals( $this->cnFixtures->getGlobalsFromFixtureData() );
	}

	protected function tearDown() {
		$this->cnFixtures->tearDownTestCases();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testAllocations( $name, $testCase ) {

		global $wgNoticeNumberOfBuckets;

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $context_and_output ) {

			$allocationsProvider = new BannerChoiceDataProvider(
				$context_and_output['context']['project'],
				$context_and_output['context']['language']
			);

			$choices = $allocationsProvider->getChoicesForCountry(
				$context_and_output['context']['country']
			);

			$logged_in_status =
				BannerAllocationCalculator::getLoggedInStatusFromString(
				$context_and_output['context']['logged_in_status'] );

			// Each entry in the allocations array represents the expected
			// allocations for one bucket. Cycle through all buckets and test
			// results each.
			for ( $bucket = 0; $bucket < $wgNoticeNumberOfBuckets; $bucket++ ) {

				$banners = BannerAllocationCalculator::filterAndTransformBanners(
					$choices,
					$logged_in_status,
					$context_and_output['context']['device'],
					$bucket
				);

				$allocations = BannerAllocationCalculator::calculateAllocations( $banners );

				// Note: test fixture data must explicitly provide the expected
				// allocations for all buckets as per $wgNoticeNumberOfBuckets
				$this->assertTrue(
					ComparisonUtil::assertEqualAllocations( $allocations,
					$context_and_output['allocations'][$bucket] )
				);
			}
		}
	}
}
