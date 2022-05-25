<?php

use MediaWiki\ResourceLoader as RL;

class CNTestFixturesResourceLoaderModule extends RL\Module {

	/**
	 * We use the same targets as core's test.mediawiki.qunit.testrunner (in
	 * QUnitTestResources).
	 *
	 * @see ResourceLoaderModule::targets
	 * @var string[]
	 */
	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * @inheritDoc
	 */
	public function getScript( RL\Context $context ) {
		return 'mediaWiki.centralNoticeTestFixtures = ' .
			CentralNoticeTestFixtures::allocationsDataAsJson() .
			';';
	}
}
