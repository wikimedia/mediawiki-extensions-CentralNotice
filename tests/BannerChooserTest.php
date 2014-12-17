<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class AllocationsTest extends MediaWikiTestCase {
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

	public function testThrottlingCampaign() {
		$this->cnFixtures->setupTestCase( array(
			'campaigns' => array(
				array(
					'preferred' => CentralNotice::NORMAL_PRIORITY,
					'throttle' => 60,
					'banners' => array(
						array(),
						array()
					),
				),
				array(
					'preferred' => CentralNotice::LOW_PRIORITY,
					'throttle' => 100,
					'banners' => array(
						array(),
						array()
					),
				),
			),
		) );
		$expected = array(
			array (
				'name' => $this->cnFixtures->spec['campaigns'][1]['banners'][0]['name'],
				'fundraising' => 1,
				'campaign' => $this->cnFixtures->spec['campaigns'][1]['name'],
				'bucket' => 0,
				'allocation' => .2,
			),
			array (
				'name' => $this->cnFixtures->spec['campaigns'][1]['banners'][1]['name'],
				'fundraising' => 1,
				'campaign' => $this->cnFixtures->spec['campaigns'][1]['name'],
				'bucket' => 0,
				'allocation' => .2,
			),
			array (
				'name' => $this->cnFixtures->spec['campaigns'][0]['banners'][0]['name'],
				'fundraising' => 1,
				'campaign' => $this->cnFixtures->spec['campaigns'][0]['name'],
				'bucket' => 0,
				'allocation' => .3,
			),
			array (
				'name' => $this->cnFixtures->spec['campaigns'][0]['banners'][1]['name'],
				'fundraising' => 1,
				'campaign' => $this->cnFixtures->spec['campaigns'][0]['name'],
				'bucket' => 0,
				'allocation' => .3,
			),
		);

		$allocContext = new AllocationContext( 'US', 'en', 'wikipedia', 'true', 'desktop', null );
		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		$this->assertTrue( ComparisonUtil::assertSuperset( $banners, $expected ) );
	}

	public function testOverAllocation() {
		$this->cnFixtures->setupTestCase( array(
			'campaigns' => array(
				array(
					'banners' => array(
						array(
							'weight' => 5,
						),
						array(
							'weight' => 100,
						),
						array(
							'weight' => 100,
						),
					),
				),
			),
		) );
		$expected = array(
			array (
				'weight' => 5,
				'slots' => 1,
			),
			array (
				'weight' => 100,
				'slots' => 15,
			),
			array (
				'weight' => 100,
				'slots' => 14,
			),
		);

		$allocContext = new AllocationContext( 'US', 'en', 'wikipedia', 'true', 'desktop', null );
		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		$this->assertTrue( ComparisonUtil::assertSuperset( $banners, $expected ) );
	}

	public function testBlanks() {
		$this->cnFixtures->setupTestCase( array(
			'campaigns' => array(
				array(
					'throttle' => 10,
					'banners' => array(
						array(),
					),
				),
			),
		) );
		$expected = array(
			array(
				'slots' => 3,
			),
		);

		$allocContext = new AllocationContext( 'US', 'en', 'wikipedia', 'true', 'desktop', null );
		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		$this->assertTrue( ComparisonUtil::assertSuperset( $banners, $expected ) );

		$this->assertNotNull( $chooser->chooseBanner( 3 ) );

		$this->assertNull( $chooser->chooseBanner( 4 ) );
	}

	public function testPriority() {
		$this->cnFixtures->setupTestCase( array(
			'campaigns' => array(
				array(
					'preferred' => CentralNotice::LOW_PRIORITY,
					'banners' => array(
						array(),
					),
				),
				array(
					'preferred' => CentralNotice::NORMAL_PRIORITY,
					'banners' => array(
						array(),
					),
				),
			),
		) );
		$expected = array(
			array(
				'campaign' => $this->cnFixtures->spec['campaigns'][0]['name'],
				'slots' => 0,
			),
			array(
				'campaign' => $this->cnFixtures->spec['campaigns'][1]['name'],
				'slots' => 30,
			),
		);

		$allocContext = new AllocationContext( 'US', 'en', 'wikipedia', 'true', 'desktop', null );
		$chooser = new BannerChooser( $allocContext );
		$banners = $chooser->getBanners();

		$this->assertTrue( ComparisonUtil::assertSuperset( $banners, $expected ) );
	}
}
