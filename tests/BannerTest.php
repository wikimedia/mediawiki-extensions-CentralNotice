<?php

/**
 * @group Fundraising
 * @group Database
 * @group CentralNotice
 */
class BannerTest extends PHPUnit_Framework_TestCase {
	const TEST_BANNER_NAME = 'PhpUnitTestBanner';

	protected $fixture;

	public static function setUpBeforeClass() {
		$banner = Banner::fromName( self::TEST_BANNER_NAME );
		if ( $banner->exists() ) {
			$banner->remove();
		}
	}

	protected function setUp() {
		parent::setUp();
		$this->fixture = new CentralNoticeTestFixtures();

		$this->fixture->setupTestCaseWithDefaults(
			[ 'setup' => [ 'campaigns' => [] ] ] );
	}

	public function tearDown() {
		$banner = Banner::fromName( self::TEST_BANNER_NAME );
		if ( $banner->exists() ) {
			$banner->remove();
		}
		$this->fixture->tearDownTestCases();
	}

	public function testNewFromName() {
		// Create what should be a new empty banner
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$this->assertFalse( $banner->exists(), 'Test precondition failed! Banner already exists!' );

		// Run down the basic metadata and ensure it is in fact empty
		$this->assertEquals( null, $banner->getId(),
			'New banner ID is not null; probably already exists!' );
		$this->assertEquals( self::TEST_BANNER_NAME, $banner->getName(),
			'Banner name did not get set' );
		$this->assertFalse( $banner->allocateToAnon(),
			'Initial anonymous allocation is set to true' );
		$this->assertFalse( $banner->allocateToLoggedIn(),
			'Initial logged in allocation is set to true' );
		$this->assertEquals( '{{{campaign}}}', $banner->getCategory(),
			'Initial category is not equal to {{{campaign}}}' );
		$this->assertFalse( $banner->isArchived(),
			'Initial banner is archived?' );

		// More complex metadata should also be empty
		$this->assertEquals( [], $banner->getDevices(),
			'Initial banner has associated device targets' );
		$this->assertEquals( [], $banner->getMixins(),
			'Initial banner has associated mixins' );
		$this->assertEquals( [], $banner->getPriorityLanguages(),
			'Initial banner has priority languages' );

		// And the body content should also be empty
		$this->assertEquals( '', $banner->getBodyContent(),
			'Initial banner has non empty body content' );

		// And finally; save this empty banner
		$banner->save();
		$this->assertTrue( $banner->exists(), 'Banner was not successfully saved' );

		// Did we get the right ID back?
		$banner2 = Banner::fromId( $banner->getId() );
		$this->assertTrue( $banner2->exists(), 'Banner does not exist by saved id!' );
	}

	/**
	 * @depends testNewFromName
	 */
	public function testEmptyFromName() {
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$banner->save();
		$this->assertTrue( $banner->exists(), 'Test banner that should exist does not!' );

		// Run down the basic metadata and ensure it is in fact empty
		$this->assertNotEquals( -1, $banner->getId(), 'Test banner has no ID' );
		$this->assertEquals( self::TEST_BANNER_NAME, $banner->getName(),
			'Banner name did not get saved' );
		$this->assertFalse( $banner->allocateToAnon(),
			'Initial anonymous allocation is set to true' );
		$this->assertFalse( $banner->allocateToLoggedIn(),
			'Initial logged in allocation is set to true' );
		$this->assertEquals( '{{{campaign}}}', $banner->getCategory(),
			'Initial category is not equal to {{{campaign}}}' );
		$this->assertFalse( $banner->isArchived(), 'Initial banner is archived?' );

		// More complex metadata should also be empty
		$this->assertEquals( [], $banner->getDevices(),
			'Initial banner has associated device targets' );
		$this->assertEquals( [], $banner->getMixins(),
			'Initial banner has associated mixins' );
		$this->assertEquals( [], $banner->getPriorityLanguages(),
			'Initial banner has priority languages' );

		// And the body content should also be empty
		$this->assertEquals( '', $banner->getBodyContent(),
			'Initial banner has non empty body content' );
	}

	/**
	 * @depends testEmptyFromName
	 */
	public function testBasicSave() {
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$banner->save();
		$this->assertTrue( $banner->exists() );

		// Attempt to populate basic metadata
		$banner->setAllocation( true, true )->setCategory( 'testCategory' );

		// And the more advanced metadata
		$banner->setDevices( 'desktop' );
		$banner->setPriorityLanguages( [ 'en', 'ru' ] );

		$banner->save();

		// Can we retrieve it from the same object?
		$this->assertTrue( $banner->allocateToAnon(),
			"Failed retrieve anon allocation from initial" );
		$this->assertTrue( $banner->allocateToLoggedIn(),
			"Failed retrieve logged in allocation from initial" );
		$this->assertEquals( 'testCategory', $banner->getCategory(),
			"Failed retrieve category from initial" );

		$this->assertEquals( [ 'desktop' ], array_values( $banner->getDevices() ),
			'Failed devices retrieve from initial' );
		$this->assertEquals( [ 'en', 'ru' ], $banner->getPriorityLanguages(),
			"Failed prilang retrieve from initial" );

		// Can we retrieve it from a different object
		$banner2 = Banner::fromName( self::TEST_BANNER_NAME );
		$this->assertTrue( $banner2->allocateToAnon(), "Failed anon allocation from copy" );
		$this->assertTrue( $banner2->allocateToLoggedIn(), "Failed loggedin allocation from copy" );
		$this->assertEquals( 'testCategory', $banner2->getCategory(), "Failed category from copy" );

		$this->assertEquals( [ 'desktop' ], array_values( $banner2->getDevices() ),
			"Failed devices from copy" );

		global $wgNoticeUseTranslateExtension;
		if ( $wgNoticeUseTranslateExtension ) {
			$this->assertEquals( [ 'en', 'ru' ], $banner2->getPriorityLanguages(),
				"Failed languages from copy" );
		}
	}

	/**
	 * @depends testBasicSave
	 * @dataProvider providerSetAllocation
	 */
	public function testSetAllocation( $anon, $loggedIn ) {
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$banner->setAllocation( $anon, $loggedIn );
		$banner->save();
		$this->assertEquals( $anon, $banner->allocateToAnon(), "Testing initial anon" );
		$this->assertEquals( $loggedIn, $banner->allocateToLoggedIn(),
			"Testing initial logged in" );

		$banner2 = Banner::fromName( self::TEST_BANNER_NAME );
		$this->assertEquals( $anon, $banner2->allocateToAnon(), "Testing post save anon" );
		$this->assertEquals( $loggedIn, $banner2->allocateToLoggedIn(),
			"Testing post save logged in" );
	}

	public function providerSetAllocation() {
		return [
			[ false, false ],
			[ true, false ],
			[ false, true ],
			[ true, true ]
		];
	}
}
