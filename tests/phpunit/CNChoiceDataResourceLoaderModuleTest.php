<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 * @covers CNChoiceDataResourceLoaderModule
 */
class CNChoiceDataResourceLoaderModuleTest extends MediaWikiTestCase {
	/** @var CentralNoticeTestFixtures */
	protected $cnFixtures;

	protected function setUp() {
		parent::setUp();
		$this->cnFixtures = new CentralNoticeTestFixtures();
	}

	protected function tearDown() {
		if ( $this->cnFixtures ) {
			$this->cnFixtures->tearDownTestCases();
		}
		parent::tearDown();
	}

	protected function getProvider() {
		return new TestingCNChoiceDataResourceLoaderModule();
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

			$fauxRequest = new FauxRequest( [
					'modules' => 'ext.centralNotice.choiceData',
					'skin' => 'fallback',
					'lang' => $contextAndOutput['context']['language']
			] );

			$rlContext = new ResourceLoaderContext( new ResourceLoader(), $fauxRequest );

			$choices = $this->getProvider()->getChoicesForTesting( $rlContext );

			$this->cnFixtures->assertChoicesEqual(
				$this, $contextAndOutput['choices'], $choices, $cAndOName );
		}
	}
}
