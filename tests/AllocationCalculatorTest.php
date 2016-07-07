<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class AllocationCalculatorTest extends MediaWikiTestCase {
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

		// Set up database with campaigns and banners from fixtures
		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		// Run through the contexts and outputs to calculate and test
		// allocations for each.
		foreach ( $testCase['contexts_and_outputs'] as $cAndOName => $cAndO ) {

			$fixtureIdMsg = "Test case: {$name}. Context: {$cAndOName}.";

			// Get choices for this context, then filter and allocate campaigns

			$choices = ChoiceDataProvider::getChoices(
				$cAndO['context']['project'],
				$cAndO['context']['language']
			);

			$logged_in_status =
				AllocationCalculator::getLoggedInStatusFromString(
				$cAndO['context']['logged_in_status'] );

			AllocationCalculator::filterChoiceData(
				$choices,
				$cAndO['context']['country'],
				$logged_in_status,
				$cAndO['context']['device']
			);

			// Choices (passed by reference) will have the results to test
			AllocationCalculator::calculateCampaignAllocations( $choices );
			$expectedAllocations = $cAndO['allocations'];

			// Test that we have the expceted number of campaigns
			$this->assertEquals(
				count( $expectedAllocations ),
				count( $choices ),
				$fixtureIdMsg . " Number of campaigns allocated."
			);

			foreach ( $choices as $campaign ) {

				$campaignName = $campaign['name'];

				// Test that the campaign was expected and was correctly allocated
				$this->assertTrue(
					array_key_exists( $campaignName, $expectedAllocations ),
					$fixtureIdMsg . " Campaign {$campaignName} not expected."
				);

				$expectedCampaign = $expectedAllocations[$campaignName];

				$this->assertEquals(
					round( $expectedCampaign['allocation'], 3),
					round( $campaign['allocation'], 3 ),
					$fixtureIdMsg . " Campaign {$campaignName} allocation."
				);

				// Skip testing banners for campaigns with 0 expected allocation.
				// (This makes fixture data more readable.)
				if ( $expectedCampaign['allocation'] === 0 ) {
					continue;
				}

				// Cycle through buckets and allocate banners for each
				for ( $bucket = 0; $bucket < $wgNoticeNumberOfBuckets; $bucket++ ) {

					$expectedBanners = $expectedCampaign['banners'][$bucket];

					$banners = AllocationCalculator::makePossibleBanners(
						$campaign,
						$bucket,
						$logged_in_status,
						$cAndO['context']['device']
					);

					AllocationCalculator::calculateBannerAllocations( $banners );

					// Test that we have the expceted number of banners
					$this->assertEquals(
						count( $expectedBanners ),
						count( $banners ),
						$fixtureIdMsg . " Number of banners allocated."
					);

					foreach ( $banners as $banner ) {

						$bannerName = $banner['name'];

						// Test the banner was expected and was correctly allocated
						$this->assertTrue(
							array_key_exists( $bannerName, $expectedBanners),
							$fixtureIdMsg .
								" Banner ${bannerName} for " .
								"campaign {$campaignName} not expected."
						);

						$this->assertEquals(
							round( $banner['allocation'], 3 ),
							round( $expectedBanners[$bannerName], 3 ),
							$fixtureIdMsg .
							" Allocation of banner {$bannerName} for {$campaignName}."
						);
					}
				}
			}
		}
	}
}
