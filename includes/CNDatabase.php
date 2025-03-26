<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Fetches the CentralNotice infrastructure database.
 */
class CNDatabase {

	/** @var bool */
	private static $primaryUsedBefore = false;

	/**
	 * Get a DB handle.
	 *
	 * If $target is not set, we use DB_PRIMARY if the current context is an HTTP
	 * POST request, or if DB_PRIMARY was used previously.
	 *
	 * We will either connect to the primary database, or a separate
	 * CentralNotice infrastructure DB specified by virtual-centralnotice database
	 * virtual domain. This is metawiki for WMF sister projects. Note that the infrastructure
	 * DB does not support table prefixes if running in multi-database mode.
	 *
	 * @param int|null $target Set to DB_PRIMARY or DB_REPLICA to force a connection
	 * to that database. If no parameter is given, we attempt to choose a
	 * reasonable default (see above).
	 *
	 * @deprecated
	 *
	 * @return IDatabase|IReadableDatabase
	 */
	public static function getDb( $target = null ) {
		global $wgRequest;

		// If the target is null, and the request was POSTed, force DB_PRIMARY.
		// This is because changes are normally expected to come through
		// Special:CentralNotice form submissions.

		// Also, if DB_PRIMARY was used before, use DB_PRIMARY.
		// This might help in the case of read-for-write updates if DB_PRIMARY
		// was used previously in an execution that didn't come through a POST.

		// XXX: global state usage :(
		// XXX: lack of separation of concerns
		// XXX: preventive hack for meandering code

		if (
			( $target === null ) &&
			( $wgRequest->wasPosted() || self::$primaryUsedBefore )
		) {
			$target = DB_PRIMARY;
		}

		// If the target is still null, use DB_REPLICA.
		$target ??= DB_REPLICA;

		// TODO: Many callers do not specify primary/replica, relying on... behaviour defined above
		if ( $target === DB_PRIMARY ) {
			return self::getPrimaryDb();
		}

		return self::getReplicaDb();
	}

	public static function getPrimaryDb(): IDatabase {
		// Comment out the line to find things that are clearly not defining their DB requirements correctly...
		// If we got DB_PRIMARY for whatever reason, make sure that's remembered
		self::$primaryUsedBefore = true;
		return MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getPrimaryDatabase( 'virtual-centralnotice' );
	}

	public static function getReplicaDb(): IReadableDatabase {
		return MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getReplicaDatabase( 'virtual-centralnotice' );
	}
}
