<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class HistoryTest extends CentralNoticeTest {
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

	public function testStaleHistoricalCampaigns() {
		// Bug was that expired campaigns would still be included in the
		// history, as long as they were enabled.
		$this->cnFixtures->setupTestCaseWithDefaults(
			[
				'setup' => [
					'campaigns' => [
						[
							'banners' => [
								[]
							],
						],
					],
				]
			] );

		$made_by_ts = wfTimestamp( TS_MW );
		$this->assertEquals( 1, count( Campaign::getHistoricalCampaigns( $made_by_ts ) ) );

		$initialSettings = Campaign::getCampaignSettings(
			$this->cnFixtures->spec['campaigns'][0]['name'] );

		sleep( 2 );

		// FIXME: Campaign::updateSettings
		$newSettings = [
			'end' => $made_by_ts,
		] + $initialSettings;
		Campaign::updateNoticeDate(
			$this->cnFixtures->spec['campaigns'][0]['name'],
			$newSettings['start'],
			$newSettings['end']
		);
		Campaign::logCampaignChange(
			'modified',
			$this->cnFixtures->spec['campaigns'][0]['id'],
			$this->userUser,
			$initialSettings,
			$newSettings
		);

		$modified_ts = wfTimestamp( TS_MW );
		$this->assertEquals( 0, count( Campaign::getHistoricalCampaigns( $modified_ts ) ) );
	}
}
