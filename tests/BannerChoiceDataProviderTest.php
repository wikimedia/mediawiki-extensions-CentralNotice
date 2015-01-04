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
		$this->cnFixtures->tearDownTestCases();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testProviderResponse( $name, $testCase ) {

		$this->cnFixtures->prepareTestcase( $testCase );
		$this->cnFixtures->setupTestCase( $testCase['setup'] );

		$allocationsProvider = new BannerChoiceDataProvider(
			CentralNoticeTestFixtures::$defaultCampaign['projects'][0],
			CentralNoticeTestFixtures::$defaultCampaign['project_languages'][0]
		);
		$choices = $allocationsProvider->getChoices();
		$this->assertTrue( ComparisonUtil::assertSuperset( $choices, $testCase['choices'] ) );

		if ( empty( $testCase['choices'] ) ) {
			$this->assertEmpty( $choices );
		}
	}
}
