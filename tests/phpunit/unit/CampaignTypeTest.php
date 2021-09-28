<?php

/**
 * @group Fundraising
 * @group CentralNotice
 * @covers CampaignType::getId
 * @covers CampaignType::getOnForAll
 * @covers CampaignType::getMessageKey
 * @covers CampaignType::getPreferenceKey
 */
class CampaignTypeTest extends MediaWikiUnitTestCase {
	protected $campaignType;

	protected function setUp(): void {
		parent::setUp();
		$this->campaignType = new CampaignType(
			'testtype',
			false
		);
	}

	public function testGetId() {
		$id = $this->campaignType->getId();
		$this->assertSame( 'testtype', $id );
	}

	public function testGetOnForAll() {
		$onForAll = $this->campaignType->getOnForAll();
		$this->assertSame( false, $onForAll );
	}

	public function testGetMessageKey() {
		// Coordinate with CampaignType::MESSAGE_KEY_PREFIX
		$this->assertSame( 'centralnotice-campaign-type-testtype',
			$this->campaignType->getMessageKey() );
	}

	public function testGetPreferenceKey() {
		// Coordinate with CampaignType::PREFERENCE_KEY_PREFIX
		$this->assertSame( 'centralnotice-display-campaign-type-testtype',
			$this->campaignType->getPreferenceKey() );
	}
}
