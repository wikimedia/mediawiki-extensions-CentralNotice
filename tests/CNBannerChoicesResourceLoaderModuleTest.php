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
			'uselang' => CentralNoticeTestFixtures::$defaultCampaign['project_languages'][0],
		) );
		$this->rlContext = new ResourceLoaderContext( new ResourceLoader(), $fauxRequest );
	}

	protected function tearDown() {
		if ( $this->cnFixtures ) {
			$this->cnFixtures->removeFixtures();
		}
		parent::tearDown();
	}

	protected function getProvider() {
		return new TestingCNBannerChoiceDataResourceLoaderModule();
	}

	protected function addSomeBanners() {
		$scenarios = CentralNoticeTestFixtures::allocationsData();
		$a_scenario = $scenarios[0][0];
		$this->cnFixtures->addFixtures( $a_scenario['fixture'] );
	}

	public function testDisabledByConfig() {
		$this->setMwGlobals( 'wgCentralNoticeChooseBannerOnClient', false );

		$this->addSomeBanners();
		$script = $this->getProvider()->getScript( $this->rlContext );

		$this->assertEmpty( $script );
	}

	/**
	 * @dataProvider CentralNoticeTestFixtures::allocationsData
	 */
	public function testChoicesFromDb( $data ) {
		$this->setMwGlobals( 'wgCentralDBname', wfWikiID() );

		$this->cnFixtures->addFixtures( $data['fixture'] );

		$choices = $this->getProvider()->getChoicesForTesting( $this->rlContext );
		$this->assertTrue( ComparisonUtil::assertSuperset( $choices, $data['choices'] ) );

		if ( empty( $data['choices'] ) ) {
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
