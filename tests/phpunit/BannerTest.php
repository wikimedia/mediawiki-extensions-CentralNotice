<?php

/**
 * @group Fundraising
 * @group Database
 * @group CentralNotice
 * @covers Banner
 */
class BannerTest extends MediaWikiIntegrationTestCase {
	private const TEST_BANNER_NAME = 'PhpUnitTestBanner';
	private const TEST_BANNER_TEMPLATE_NAME = 'PhpUnitTestBannerTemplate';

	protected $fixture;

	protected function setUp(): void {
		parent::setUp();

		$this->fixture = new CentralNoticeTestFixtures();

		$this->fixture->setupTestCaseWithDefaults(
			[ 'setup' => [ 'campaigns' => [] ] ] );
	}

	protected function tearDown(): void {
		$this->deleteTemplateIfExists( static::TEST_BANNER_NAME );
		$this->deleteTemplateIfExists( static::TEST_BANNER_TEMPLATE_NAME );

		$this->fixture->tearDownTestCases();
	}

	public function testNewFromName() {
		// Create what should be a new empty banner
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$this->assertFalse( $banner->exists(), 'Test precondition failed! Banner already exists!' );

		// Run down the basic metadata and ensure it is in fact empty
		$this->assertNull( $banner->getId(),
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
		$this->assertSame( '', $banner->getBodyContent(),
			'Initial banner has non empty body content' );

		// And finally; save this empty banner
		$user = $this->getTestUser()->getUser();
		$banner->save( $user );
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
		$user = $this->getTestUser()->getUser();
		$banner->save( $user );
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
		$this->assertSame( '', $banner->getBodyContent(),
			'Initial banner has non empty body content' );
	}

	/**
	 * @depends testEmptyFromName
	 */
	public function testBasicSave() {
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$user = $this->getTestUser()->getUser();
		$banner->save( $user );
		$this->assertTrue( $banner->exists() );

		// Attempt to populate basic metadata
		$banner->setAllocation( true, true )->setCategory( 'testCategory' );

		// And the more advanced metadata
		$banner->setDevices( 'desktop' );
		$banner->setPriorityLanguages( [ 'en', 'ru' ] );

		$banner->save( $user );

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

	public function testAddTemplate() {
		$res = Banner::addBanner(
			static::TEST_BANNER_NAME,
			'<!-- empty -->',
			$this->getTestUser()->getUser(),
			true, false,
			[], [ 'en' ], null, '',
			false, 'fundraising'
		);

		$this->assertFalse( is_string( $res ), 'Banner::addBanner must not return a string' );
		$banner = Banner::fromName( static::TEST_BANNER_NAME );

		$this->assertTrue(
			$banner->exists(), 'Banner::addBanner must add the banner to the database'
		);

		$this->assertEquals(
			'fundraising', $banner->getCategory(),
			"Category should be 'fundraising'"
		);
	}

	/**
	 * @depends testAddTemplate
	 */
	public function testAddFromBannerTemplate() {
		Banner::addBanner(
			static::TEST_BANNER_TEMPLATE_NAME,
			'Dummy body',
			$this->getTestUser()->getUser(),
			true, false,
			[], [ 'en' ], null, '',
			true, 'Dummy'
		);

		$bannerTemplate = Banner::fromName( static::TEST_BANNER_TEMPLATE_NAME );
		$this->assertTrue( $bannerTemplate->isTemplate(), 'Failed to mark banner as template' );

		Banner::addFromBannerTemplate(
			static::TEST_BANNER_NAME,
			$this->getTestUser()->getUser(),
			$bannerTemplate
		);

		$banner = Banner::fromName( static::TEST_BANNER_NAME );

		$this->assertEquals(
			static::TEST_BANNER_NAME, $banner->getName(),
			'Failed to retrieve correct banner name'
		);
		$this->assertEquals(
			'Dummy body', $banner->getBodyContent(),
			'Failed to retrieve correct banner name'
		);
		$this->assertEquals(
			'Dummy', $banner->getCategory(), 'Failed to retrieve correct category'
		);
		$this->assertTrue( $banner->allocateToAnon(), 'Failed to retrieve correct allocation to anon' );
		$this->assertFalse( $banner->isTemplate(), 'Failed to retrieve correct template designation' );
	}

	/**
	 * @depends testBasicSave
	 * @dataProvider providerSetAllocation
	 */
	public function testSetAllocation( $anon, $loggedIn ) {
		$banner = Banner::newFromName( self::TEST_BANNER_NAME );
		$user = $this->getTestUser()->getUser();

		$banner->setAllocation( $anon, $loggedIn );
		$banner->save( $user );
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

	/**
	 * Delete template on teardown
	 *
	 * @param string $name
	 * @throws BannerDataException
	 */
	protected function deleteTemplateIfExists( $name ) {
		$banner = Banner::fromName( $name );
		if ( $banner->exists() ) {
			$user = $this->getTestUser()->getUser();
			$banner->remove( $user );
		}
	}
}
