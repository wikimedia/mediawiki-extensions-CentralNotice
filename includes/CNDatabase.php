<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Fetches the CentralNotice infrastructure database.
 */
class CNDatabase {
	public static function getPrimaryDb(): IDatabase {
		return MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-centralnotice' );
	}

	public static function getReplicaDb(): IReadableDatabase {
		return MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getReplicaDatabase( 'virtual-centralnotice' );
	}
}
