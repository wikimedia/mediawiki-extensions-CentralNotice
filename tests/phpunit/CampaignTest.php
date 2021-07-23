<?php

/**
 * @group Fundraising
 * @group Database
 * @group CentralNotice
 * @covers Campaign
 */
class CampaignTest extends MediaWikiIntegrationTestCase {
	protected $userUser;

	protected $campaignArray;

	protected $fixture;

	protected function setUp(): void {
		$this->userUser = $this->getTestUser()->getUser();
		$noticeName = 'PHPUnitTestCampaign';
		$enabled = false;
		$startTs = '20110718' . '235500';
		$projects = [ 'wikipedia', 'wikibooks' ];
		$languages = [ 'en', 'de' ];
		$geotargeted = true;
		$countries = [ 'US', 'AF' ];
		$regions = [];
		$priority = 1;

		$this->fixture = new CentralNoticeTestFixtures();
		$this->fixture->setupTestCaseWithDefaults(
			[ 'setup' => [ 'campaigns' => [] ] ] );

		$this->campaignArray = [
			'enabled' => '0',
			'end' => '20110719005500',
			'geo' => '1',
			'locked' => '0',
			'preferred' => '1',
			'start' => '20110718235500',
			'buckets' => '1',
			'projects' => 'wikibooks, wikipedia',
			'languages' => 'de, en',
			'countries' => 'AF, US',
			'regions' => '',
			'archived' => 0,
			'throttle' => 100,
			'mixins' => '[]',
			'type' => null
		];

		Campaign::addCampaign( $noticeName, $enabled, $startTs, $projects,
			$languages, $geotargeted, $countries, $regions,
			100, $priority, $this->userUser, null );

		$bannerName = 'PHPUnitTestBanner';
		$body = 'testing';
		$displayAnon = true;
		$displayAccount = true;
		$category = 'fundraising';

		$this->campaignBannersJson = '[{"name":"PHPUnitTestBanner","weight":25,"display_anon":1,' .
			'"display_account":1,"fundraising":1,"device":"desktop",' .
			'"campaign":"PHPUnitTestCampaign","campaign_z_index":"1","campaign_num_buckets":1,' .
			'"campaign_throttle":100,"bucket":0}]';

		Banner::addBanner( $bannerName, $body, $this->userUser, $displayAnon, $displayAccount,
			[], [], null, null, false, $category );
		Campaign::addTemplateTo( 'PHPUnitTestCampaign', 'PHPUnitTestBanner', '25' );
	}

	protected function tearDown(): void {
		parent::tearDown();
		Campaign::removeCampaign( 'PHPUnitTestCampaign', $this->userUser );
		Campaign::removeTemplateFor( 'PHPUnitTestCampaign', 'PHPUnitTestBanner' );
		Banner::removeBanner( 'PHPUnitTestBanner', $this->userUser );
		$this->fixture->tearDownTestCases();
	}

	public function testGetNoticeProjects() {
		$projects = Campaign::getNoticeProjects( 'PHPUnitTestCampaign' );
		sort( $projects );
		$this->assertEquals(
			[ 'wikibooks', 'wikipedia' ],
			$projects
		);
	}

	public function testGetNoticeLanguages() {
		$languages = Campaign::getNoticeLanguages( 'PHPUnitTestCampaign' );
		sort( $languages );
		$this->assertEquals(
			[ 'de', 'en' ],
			$languages
		);
	}

	public function testGetNoticeCountries() {
		$countries = Campaign::getNoticeCountries( 'PHPUnitTestCampaign' );
		sort( $countries );
		$this->assertEquals(
			[ 'AF', 'US' ],
			$countries
		);
	}

	public function testGetCampaignBanners() {
		$campaignId = Campaign::getNoticeId( 'PHPUnitTestCampaign' );
		$this->assertEquals(
			$this->campaignBannersJson,
			json_encode( Banner::getCampaignBanners( $campaignId ) )
		);
	}

	public function testGetCampaignSettings() {
		$settings = Campaign::getCampaignSettings( 'PHPUnitTestCampaign' );
		$banners = json_decode( $settings[ 'banners' ], true );

		$this->assertCount( 1, $banners );
		$this->assertEquals( [ 'PHPUnitTestBanner' ], array_keys( $banners ) );
		unset( $settings[ 'banners' ] );

		$this->assertEquals(
			$this->campaignArray,
			$settings
		);
	}

}
