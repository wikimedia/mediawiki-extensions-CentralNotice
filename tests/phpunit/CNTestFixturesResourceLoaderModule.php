<?php

class CNTestFixturesResourceLoaderModule extends ResourceLoaderModule {

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
	public function getScript( ResourceLoaderContext $context ) {
		return 'mediaWiki.centralNoticeTestFixtures = ' .
			CentralNoticeTestFixtures::allocationsDataAsJson() .
			';';
	}
}
