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

		$this->setMwGlobals( array(
			'wgCentralNoticeChooseBannerOnClient' => true,
			'wgNoticeProject' => 'wikipedia',
		) );
		$this->cnFixtures = new CentralNoticeTestFixtures();

		$fauxRequest = new FauxRequest( array(
			'modules' => 'ext.centralNotice.bannerChoiceData',
			'skin' => 'fallback',
			'user' => false,
			'uselang' => 'en' // Note: this is a temporary measure 
		) );
		$this->rlContext = new ResourceLoaderContext( new ResourceLoader(), $fauxRequest );
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

	protected function addSomeBanners() {
		$fixtures = CentralNoticeTestFixtures::allocationsData();
		$completeness = $fixtures['test_cases']['completeness'];
		$this->cnFixtures->setupTestCaseFromFixtureData( $completeness );
	}

	public function testDisabledByConfig() {
		$this->setMwGlobals( 'wgCentralNoticeChooseBannerOnClient', false );

		$this->addSomeBanners();
		$script = $this->getProvider()->getScript( $this->rlContext );

		$this->assertEmpty( $script );
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsTestCasesProvision
	 */
	public function testChoicesFromDb( $name, $testCase ) {
		$this->setMwGlobals( 'wgCentralDBname', wfWikiID() );

		$this->cnFixtures->setupTestCaseFromFixtureData( $testCase );

		$choices = $this->getProvider()->getChoicesForTesting( $this->rlContext );
		$this->assertTrue( ComparisonUtil::assertSuperset( $choices, $testCase['choices'] ) );

		if ( empty( $testCase['choices'] ) ) {
			$this->assertEmpty( $choices );
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
