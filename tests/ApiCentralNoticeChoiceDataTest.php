<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class ApiCentralNoticeChoiceDataTest extends ApiTestCase {
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
	public function testChoiceDataResponse( $name, $testCase ) {

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $cAndOName => $contextAndOutput ) {

			$ret = $this->doApiRequest( array(
				'action' => 'centralnoticechoicedata',
				'project' => $contextAndOutput['context']['project'],
				'language' => $contextAndOutput['context']['language']
			) );

			$this->cnFixtures->assertChoicesEqual(
				$this, $contextAndOutput['choices'], $ret[0]['choices'], $cAndOName );
		}
	}
}
