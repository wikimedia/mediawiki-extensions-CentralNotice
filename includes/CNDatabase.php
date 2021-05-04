<?php

use Wikimedia\Rdbms\IDatabase;

/**
 * Fetches the CentralNotice infrastructure database.
 */
class CNDatabase {

	/** @var bool */
	protected static $primaryUsedBefore = false;

	/**
	 * Get a DB handle.
	 *
	 * If $target is not set, we use DB_PRIMARY if the current context is an HTTP
	 * POST request, or if DB_PRIMARY was used previously.
	 *
	 * We will either connect to the primary database, or a separate
	 * CentralNotice infrastructure DB specified by $wgCentralDBname. This is
	 * metawiki for WMF sister projects. Note that the infrastructure DB does
	 * not support table prefixes if running in multi-database mode.
	 *
	 * @param int|null $target Set to DB_PRIMARY or DB_REPLICA to force a connection
	 * to that database.  If no parameter is given, we attempt to choose a
	 * sane default (see above).
	 *
	 * @return IDatabase
	 */
	public static function getDb( $target = null ) {
		global $wgCentralDBname, $wgDBname, $wgRequest;

		// If target is null, and the request was POSTed, force DB_PRIMARY.
		// This is because changes are normally expected to come through
		// Special:CentralNotice form submissions.

		// Also, if DB_PRIMARY was used before, use DB_PRIMARY.
		// This might help in the case of read-for-write updates, if DB_PRIMARY
		// was used previously in an execution that didn't come through a POST.

		// XXX: global state usage :(
		// XXX: lack of separation of concerns
		// XXX: preventive hack for meandering code

		if ( ( $target === null ) &&
			( $wgRequest->wasPosted() || self::$primaryUsedBefore )
		) {
			$target = DB_PRIMARY;
		}

		// If target is still null, use DB_REPLICA.
		if ( $target === null ) {
			$target = DB_REPLICA;
		}

		// If we got DB_PRIMARY for whatever reason, make sure that's remembered
		if ( $target === DB_PRIMARY ) {
			self::$primaryUsedBefore = true;
		}

		// Always use the database with CentralNotice data
		if ( $wgCentralDBname === false || $wgCentralDBname === $wgDBname ) {
			return wfGetDB( $target );
		} else {
			return wfGetDB( $target, [], $wgCentralDBname );
		}
	}
}
