<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 * @covers CNChoiceDataResourceLoaderModule
 */
class CNChoiceDataResourceLoaderModuleTest extends ResourceLoaderTestCase {
	/** @var CentralNoticeTestFixtures */
	protected $cnFixtures;

	protected function setUp(): void {
		parent::setUp();
		$this->cnFixtures = new CentralNoticeTestFixtures();
	}

	protected function tearDown(): void {
		if ( $this->cnFixtures ) {
			$this->cnFixtures->tearDownTestCases();
		}
		parent::tearDown();
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testChoicesFromDb( $name, $testCase ) {
		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $cAndOName => $contextAndOutput ) {
			$this->setMwGlobals( [
					'wgNoticeProject' => $contextAndOutput['context']['project'],
			] );

			// Following pattern in other RL module tests
			$rlContext = $this->getResourceLoaderContext(
				[ 'lang' => $contextAndOutput['context']['language'] ] );

			$module = new TestingCNChoiceDataResourceLoaderModule();
			$module->setConfig( $rlContext->getResourceLoader()->getConfig() );
			$choices = $module->getChoicesForTesting( $rlContext );

			$this->cnFixtures->assertChoicesEqual(
				$this, $contextAndOutput['choices'], $choices, $cAndOName );
		}
	}
}
