<?php

/**
 * @group Fundraising
 */
class CentralNoticeTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var CentralNotice
	 */
	protected static $centralNotice;
	/**
	 * @var CentralNoticeDB
	 */
	protected static $centralNoticeDB;
	/**
	 * @var SpecialNoticeTemplate
	 */
	protected static $noticeTemplate;

	protected $userUser;

	var $campaignId;

	protected function setUp() {
		parent::setUp();
		self::$centralNotice = new CentralNotice;
		$noticeName        = 'PHPUnitTestCampaign';
		$enabled           = 0;
		$startTs           = '20110718' . '235500';
		$projects          = array( 'wikipedia', 'wikibooks' );
		$project_languages = array( 'en', 'de' );
		$geotargeted       = 1;
		$geo_countries     = array( 'US', 'AF' );

		$this->campaignArray = array(
			'enabled' => '0',
			'end' => '20110719005500',
			'geo' => '1',
			'locked' => '0',
			'preferred' => '1',
			'start' => '20110718235500',
			'buckets' => '1',
		);

		//get User
		$this->userUser = User::newFromName( 'TestUser' );
		if ( !$this->userUser->getID() ) {
			$this->userUser = User::createNew( 'TestUser', array(
				"email" => "test@example.com",
				"real_name" => "Test User" ) );
			$this->userUser->load();
		}
		self::$centralNoticeDB = new CentralNoticeDB();
		self::$centralNoticeDB->addCampaign( $noticeName, $enabled, $startTs, $projects,
			$project_languages, $geotargeted, $geo_countries, $this->userUser );

		$this->campaignId = self::$centralNoticeDB->getNoticeId( 'PHPUnitTestCampaign' );

		self::$noticeTemplate = new SpecialNoticeTemplate;
		$bannerName = 'PHPUnitTestBanner';
		$body = 'testing';
		$displayAnon = 1;
		$displayAccount = 1;
		$fundraising = 1;
		$autolink = 0;
		$landingPages = 'JA1, JA2';
		$campaign_z_index = 0;

		$this->campaignBannersJson = '[{"name":"PHPUnitTestBanner","weight":25,"display_anon":1,"display_account":1,"fundraising":1,"autolink":0,"landing_pages":"JA1, JA2","campaign":"PHPUnitTestCampaign","campaign_z_index":"1","campaign_num_buckets":1,"bucket":0}]';

		self::$noticeTemplate->addTemplate( $bannerName, $body, $displayAnon, $displayAccount,
			$fundraising, $autolink, $landingPages );
		self::$centralNoticeDB->addTemplateTo( 'PHPUnitTestCampaign', 'PHPUnitTestBanner', '25' );
	}

	protected function tearDown() {
		parent::tearDown();
		self::$centralNoticeDB->removeCampaign( 'PHPUnitTestCampaign', $this->userUser );
		self::$centralNoticeDB->removeTemplateFor( 'PHPUnitTestCampaign', 'PHPUnitTestBanner' );
		self::$noticeTemplate->removeTemplate ( 'PHPUnitTestBanner' );
	}

	public function testDropDownList() {
		$text = 'Weight';
		$values = range ( 0, 50, 10 );
		$this->assertEquals(
			"*Weight\n**0\n**10\n**20\n**30\n**40\n**50\n",
			CentralNotice::dropDownList( $text, $values ) );
	}

	public function testGetNoticeProjects() {
		$this->assertEquals(
			array ( 'wikibooks', 'wikipedia' ),
			self::$centralNoticeDB->getNoticeProjects( 'PHPUnitTestCampaign' )
		);
	}

	public function testGetNoticeLanguages() {
		$this->assertEquals(
			array ( 'de', 'en' ),
			self::$centralNoticeDB->getNoticeLanguages( 'PHPUnitTestCampaign' )
		);
	}

	public function testGetNoticeCountries() {
		$this->assertEquals(
			array ( 'AF', 'US' ),
			self::$centralNoticeDB->getNoticeCountries( 'PHPUnitTestCampaign' )
		);
	}

	public function testGetCampaignBanners() {
		$campaignId = self::$centralNoticeDB->getNoticeId( 'PHPUnitTestCampaign' );
		$this->assertEquals(
			$this->campaignBannersJson,
			json_encode( self::$centralNoticeDB->getCampaignBanners( $campaignId ) )
		);
	}

	public function testGetCampaignSettings() {
		$this->assertEquals(
			$this->campaignArray,
			self::$centralNoticeDB->getCampaignSettings( 'PHPUnitTestCampaign', false )
		);
	}
}
