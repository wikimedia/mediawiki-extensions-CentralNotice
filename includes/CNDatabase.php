<?php

/**
 * Fetches the CentralNotice infrastructure database.
 */
class CNDatabase {

	protected static $masterUsedBefore = false;

	/**
	 * Get a DB handle.
	 *
	 * If $target is not set, we use DB_MASTER if the current context is an HTTP
	 * POST request, or if DB_MASTER was used previously.
	 *
	 * We will either connect to the primary database, or a separate
	 * CentralNotice infrastructure DB specified by $wgCentralDBname. This is
	 * metawiki for WMF sister projects. Note that the infrastructure DB does
	 * not support table prefixes if running in multi-database mode.
	 *
	 * @param int $target Set to DB_MASTER or DB_SLAVE to force a connection
	 * to that database.  If no parameter is given, we attempt to choose a
	 * sane default (see above).
	 *
	 * @return DatabaseBase
	 */
	public static function getDb( $target = null ) {
		global $wgCentralDBname, $wgDBname, $wgRequest, $wgUser;

		// If target is null, and the request was POSTed, force DB_MASTER.
		// This is because changes are normally expected to come through
		// Special:CentralNotice form submissions.

		// Also, if DB_MASTER was used before, use DB_MASTER.
		// This might help in the case of read-for-write updates, if DB_MASTER
		// was used previously in an execution that didn't come through a POST.

		// XXX: global state usage :(
		// XXX: lack of separation of concerns
		// XXX: preventive hack for meandering code

		if ( ( $target === null ) &&
			( $wgRequest->wasPosted() || self::$masterUsedBefore ) ) {
			$target = DB_MASTER;
		}

		// If target is still null, use DB_SLAVE.
		if ( $target === null ) {
			$target = DB_SLAVE;
		}

		// If we got DB_MASTER for whatever reason, make sure that's remembered
		if ( $target === DB_MASTER ) {
			self::$masterUsedBefore = true;
		}

		// Always use the database with CentralNotice data
		if ( $wgCentralDBname === false || $wgCentralDBname === $wgDBname ) {
			return wfGetDB( $target );
		} else {
			return wfGetDB( $target, array(), $wgCentralDBname );
		}
	}
}
