<?php

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 */
class HistoryTest extends MediaWikiIntegrationTestCase {
	protected $userUser;

	/** @var CentralNoticeTestFixtures */
	protected $cnFixtures;

	protected function setUp(): void {
		parent::setUp();

		$this->userUser = $this->getTestUser()->getUser();

		$this->cnFixtures = new CentralNoticeTestFixtures();
	}

	protected function tearDown(): void {
		$this->cnFixtures->tearDownTestCases();
		parent::tearDown();
	}

	/**
	 * @covers Campaign::getHistoricalCampaigns
	 */
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
		$this->assertCount( 1, Campaign::getHistoricalCampaigns( $made_by_ts ) );

		$initialSettings = Campaign::getCampaignSettings(
			$this->cnFixtures->spec['campaigns'][0]['name'] );

		sleep( 1 );

		// FIXME: Campaign::updateSettings
		$newSettings = [
			'end' => $made_by_ts,
		] + $initialSettings;
		Campaign::updateNoticeDate(
			$this->cnFixtures->spec['campaigns'][0]['name'],
			$newSettings['start'],
			$newSettings['end']
		);
		Campaign::processAfterCampaignChange(
			'modified',
			$this->cnFixtures->spec['campaigns'][0]['id'],
			$this->cnFixtures->spec['campaigns'][0]['name'],
			$this->userUser,
			$initialSettings,
			$newSettings
		);

		$modified_ts = wfTimestamp( TS_MW );
		$this->assertCount( 0, Campaign::getHistoricalCampaigns( $modified_ts ) );
	}
}
