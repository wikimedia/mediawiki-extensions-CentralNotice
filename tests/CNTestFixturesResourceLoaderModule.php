<?php

class CNTestFixturesResourceLoaderModule extends ResourceLoaderModule {

	/**
	 * We use the same targets as core's test.mediawiki.qunit.testrunner (in
	 * QUnitTestResources).
	 *
	 * @see ResourceLoaderModule::targets
	 */
	protected $targets = array( 'desktop', 'mobile' );

	/**
	 * @see ResourceLoaderModule::getScript()
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return 'mediaWiki.centralNoticeTestFixtures = ' .
			CentralNoticeTestFixtures::allocationsDataAsJson() .
			';';
	}
}
