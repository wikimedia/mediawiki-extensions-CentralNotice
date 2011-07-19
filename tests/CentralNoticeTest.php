<?php

/**
 * @group Fundraising
 */
class CentralNoticeTest extends PHPUnit_Framework_TestCase {

	protected static $centralNotice;

	protected function setUp() {
		self::$centralNotice = new CentralNotice;
		$noticeName        = 'PHPUnitTestCampaign';
		$enabled           = 0;
		$start             = array( 
  			"month" => 07,
  			"day"   => 18,
  			"year"  => 2011,
			"hour"  => 23,
			"min"   => 55,
		);
		$projects          = array( 'wikipedia', 'wikibooks' );
		$project_languages = array( 'en', 'de' );
		$geotargeted       = 1;
		$geo_countries     = array( 'US', 'AF' );
		self::$centralNotice->addCampaign( $noticeName, $enabled, $start, $projects,
			$project_languages, $geotargeted, $geo_countries );
	}
	
	protected function tearDown() {
		self::$centralNotice->removeCampaign( 'PHPUnitTestCampaign' );
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
			CentralNotice::getNoticeProjects( 'PHPUnitTestCampaign' )
		);
	}
	
	public function testGetNoticeLanguages() {
		$this->assertEquals(
			array ( 'de', 'en' ),
			CentralNotice::getNoticeLanguages( 'PHPUnitTestCampaign' )
		);
	}
	
	public function testGetNoticeCountries() {
		$this->assertEquals(
			array ( 'AF', 'US' ),
			CentralNotice::getNoticeCountries( 'PHPUnitTestCampaign' )
		);
	}

}
