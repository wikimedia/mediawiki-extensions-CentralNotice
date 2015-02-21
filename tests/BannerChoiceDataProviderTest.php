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

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $cANdOName => $contextAndOutput ) {

			$allocationsProvider = new BannerChoiceDataProvider(
				$contextAndOutput['context']['project'],
				$contextAndOutput['context']['language']
			);

			$choices = $allocationsProvider->getChoices();

			$this->cnFixtures->assertChoicesEqual(
				$this, $contextAndOutput['choices'], $choices, $cANdOName );
		}
	}
}
