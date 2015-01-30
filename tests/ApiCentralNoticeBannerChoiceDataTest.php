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
		$this->cnFixtures->removeFixtures();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsData
	 */
	public function testBannerChoiceResponse( $data ) {
		$this->cnFixtures->addFixtures( $data['fixture'] );

		$ret = $this->doApiRequest( array(
			'action' => 'centralnoticebannerchoicedata',
			'project' => CentralNoticeTestFixtures::$defaultCampaign['projects'][0],
			'language' => CentralNoticeTestFixtures::$defaultCampaign['project_languages'][0]
		) );
		$this->assertTrue( ComparisonUtil::assertSuperset( $ret[0]['choices'], $data['choices'] ) );
	}
}
