<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class ApiCentralNoticeBannerChoiceDataTest extends ApiTestCase {
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
	public function testBannerChoiceResponse( $name, $testCase ) {
		$this->cnFixtures->setupTestCase( $testCase['setup'] );

		$ret = $this->doApiRequest( array(
			'action' => 'centralnoticebannerchoicedata',
			'project' => CentralNoticeTestFixtures::$defaultCampaign['projects'][0],
			'language' => CentralNoticeTestFixtures::$defaultCampaign['project_languages'][0]
		) );
		$this->assertTrue( ComparisonUtil::assertSuperset( $ret[0]['choices'], $testCase['choices'] ) );
	}
}
