<?php

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Seeds a test banner + campaign so selenium tests that look for
 * #centralnotice_testbanner pass on a fresh environment.
 *
 * CentralNotice's selenium test (tests/selenium/specs/centralnotice.js) expects a
 * banner to be showing on the Main Page. Betacluster keeps one as persistent data, but
 * ephemeral environments (e.g. catalyst) start empty, so they must seed it before the
 * test runs. Idempotent: skips anything that already exists.
 *
 * See https://phabricator.wikimedia.org/T429531
 */
class SeedTestData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralNotice' );
		$this->addDescription(
			'Seed a test banner + campaign for selenium runs on fresh environments (e.g. catalyst).'
		);
	}

	public function execute() {
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

		if ( Banner::fromName( 'testbanner' )->exists() ) {
			$this->output( "Banner 'testbanner' already exists; skipping.\n" );
		} else {
			Banner::addBanner(
				name: 'testbanner',
				body: '<div id="centralnotice_testbanner">CI test banner</div>',
				user: $user,
				displayAnon: true,
				displayAccount: true
			);
			$this->output( "Created banner 'testbanner'.\n" );
		}

		if ( Campaign::campaignExists( 'TestCampaign' ) ) {
			$this->output( "Campaign 'TestCampaign' already exists; skipping.\n" );
		} else {
			Campaign::addCampaign(
				noticeName: 'TestCampaign',
				enabled: true,
				startTs: wfTimestamp( TS_MW ),
				projects: [ 'wikipedia' ],
				project_languages: [ 'en' ],
				geotargeted: false,
				geo_countries: [],
				geo_regions: [],
				throttle: 100,
				priority: CentralNotice::NORMAL_PRIORITY,
				user: $user,
				type: null
			);
			Campaign::addTemplateTo( 'TestCampaign', 'testbanner', 25 );
			$this->output( "Created campaign 'TestCampaign' with banner 'testbanner'.\n" );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = SeedTestData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
