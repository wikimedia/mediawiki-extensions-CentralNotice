<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class CNBannerChoicesResourceLoaderModuleTest extends MediaWikiTestCase {
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
		return new TestingCNBannerChoiceDataResourceLoaderModule();
	}

	public function testDisabledByConfig() {

		// Disable choices on client but make sure a method for obtaining choices
		// is configured (to be sure that if the test fails it's due to a
		// failure of the choices-on-client config).
		$this->setMwGlobals( array(
			'wgCentralNoticeChooseBannerOnClient' => false,
			'wgCentralDBname' => wfWikiID()
		) );

		$fauxRequest = new FauxRequest( array(
			'modules' => 'ext.centralNotice.bannerChoiceData',
			'skin' => 'fallback',
			'uselang' => 'en' // dummy value, just in case it makes a difference
		) );

		$rlContext = new ResourceLoaderContext( new ResourceLoader(), $fauxRequest );
		$script = $this->getProvider()->getScript( $rlContext );

		$this->assertEmpty( $script );
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testChoicesFromDb( $name, $testCase ) {
		$this->setMwGlobals( 'wgCentralDBname', wfWikiID() );

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		foreach ( $testCase['contexts_and_outputs'] as $context_and_output ) {

			$this->setMwGlobals( array(
					'wgCentralNoticeChooseBannerOnClient' => true,
					'wgNoticeProject' => $context_and_output['context']['project']
			) );

			$fauxRequest = new FauxRequest( array(
					'modules' => 'ext.centralNotice.bannerChoiceData',
					'skin' => 'fallback',
					'uselang' => $context_and_output['context']['language']
			) );

			$rlContext = new ResourceLoaderContext( new ResourceLoader(), $fauxRequest );

			$choices = $this->getProvider()->getChoicesForTesting( $rlContext );

			$this->cnFixtures->assertChoicesEqual(
				$this, $context_and_output['choices'], $choices );
		}
	}
}

/**
 * Wrapper to circumvent access control
 */
class TestingCNBannerChoiceDataResourceLoaderModule extends CNBannerChoiceDataResourceLoaderModule {
	public function getChoicesForTesting( $rlContext ) {
		return $this->getChoices( $rlContext );
	}
}
