<?php

use MediaWiki\ResourceLoader as RL;

class CNTestFixturesResourceLoaderModule extends RL\Module {

	/**
	 * @inheritDoc
	 */
	public function getScript( RL\Context $context ) {
		return 'mediaWiki.centralNoticeTestFixtures = ' .
			CentralNoticeTestFixtures::allocationsDataAsJson() .
			';';
	}
}
