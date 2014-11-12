<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class BannerChoiceDataProviderTest extends MediaWikiTestCase {
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
	public function testProviderResponse( $fixtures, $expected ) {
		$this->cnFixtures->addFixtures( $fixtures );

		$allocationsProvider = new BannerChoiceDataProvider(
			CentralNoticeTestFixtures::$defaultCampaign['projects'][0],
			CentralNoticeTestFixtures::$defaultCampaign['project_languages'][0],
			BannerChoiceDataProvider::ANONYMOUS
		);
		$choices = $allocationsProvider->getChoices();
		$this->assertTrue( ComparisonUtil::assertSuperset( $choices, $expected ) );

		if ( empty( $expected ) ) {
			$this->assertEmpty( $choices );
		}
	}
}
