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

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $context_and_output ) {

			$ret = $this->doApiRequest( array(
				'action' => 'centralnoticebannerchoicedata',
				'project' => $context_and_output['context']['project'],
				'language' => $context_and_output['context']['language']
			) );

			$this->cnFixtures->assertChoicesEqual(
				$this, $context_and_output['choices'], $ret[0]['choices'] );
		}
	}
}
