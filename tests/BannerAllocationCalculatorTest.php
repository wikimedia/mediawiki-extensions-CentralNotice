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
		$this->cnFixtures->removeFixtures();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsData
	 */
	public function testAllocations( $fixtures, $expectedChoices, $expectedAllocations ) {
		$this->cnFixtures->addFixtures( $fixtures );

		$allocationsProvider = new BannerChoiceDataProvider(
			CentralNoticeTestFixtures::getDefaultProject(),
			CentralNoticeTestFixtures::getDefaultLanguage(),
			BannerChoiceDataProvider::ANONYMOUS
		);
		$choices = $allocationsProvider->getChoicesForCountry(
			CentralNoticeTestFixtures::getDefaultCountry()
		);
		$banners = BannerAllocationCalculator::filterAndTransformBanners(
			$choices,
			CentralNoticeTestFixtures::getDefaultDevice(),
			0
		);
		$allocations = BannerAllocationCalculator::calculateAllocations( $banners );

		$this->assertTrue(
			ComparisonUtil::assertEqualAllocations( $allocations, $expectedAllocations )
		);
	}
}
