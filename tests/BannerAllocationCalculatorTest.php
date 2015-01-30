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
	}

	protected function tearDown() {
		$this->cnFixtures->tearDownTestCases();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testAllocations( $name, $testCase ) {

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		$allocationsProvider = new BannerChoiceDataProvider(
			CentralNoticeTestFixtures::getDefaultProject(),
			CentralNoticeTestFixtures::getDefaultLanguage()
		);
		$choices = $allocationsProvider->getChoicesForCountry(
			CentralNoticeTestFixtures::getDefaultCountry()
		);
		$banners = BannerAllocationCalculator::filterAndTransformBanners(
			$choices,
			BannerAllocationCalculator::ANONYMOUS,
			CentralNoticeTestFixtures::getDefaultDevice(),
			0
		);
		$allocations = BannerAllocationCalculator::calculateAllocations( $banners );

		$this->assertTrue(
			ComparisonUtil::assertEqualAllocations( $allocations, $testCase['allocations'] )
		);
	}
}
