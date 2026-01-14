<?php

namespace MediaWiki\Extension\CentralNotice\Tests\Structure;

use MediaWiki\Tests\Structure\BundleSizeTestBase;

class BundleSizeTest extends BundleSizeTestBase {

	/** @inheritDoc */
	public static function getBundleSizeConfigData(): string {
		return dirname( __DIR__, 3 ) . '/bundlesize.config.json';
	}

	/**
	 * @deprecated can be removed when support for MediaWiki 1.44 is dropped
	 */
	public function getBundleSizeConfig(): string {
		return self::getBundleSizeConfigData();
	}

}
