<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class ChoiceDataProviderTest extends MediaWikiIntegrationTestCase {
	/** @var CentralNoticeTestFixtures */
	protected $cnFixtures;

	protected function setUp(): void {
		parent::setUp();

		$this->cnFixtures = new CentralNoticeTestFixtures();
	}

	protected function tearDown(): void {
		$this->cnFixtures->tearDownTestCases();
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 * @covers ChoiceDataProvider::getChoices
	 */
	public function testProviderResponse( $name, $testCase ) {
		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $cANdOName => $contextAndOutput ) {
			$choices = ChoiceDataProvider::getChoices(
				$contextAndOutput['context']['project'],
				$contextAndOutput['context']['language']
			);

			$this->cnFixtures->assertChoicesEqual(
				$this, $contextAndOutput['choices'], $choices, $cANdOName );
		}
	}
}
