<?php

/**
 * Fetches the CentralNotice infrastructure database.
 */
class CNDatabase {
	/**
	 * Gets a database object. Will be the DB_MASTER if the user has the
	 * centralnotice-admin right. NOTE: $force is ignored for such users.
	 *
	 * We will either connect to the primary database, or a separate CentralNotice
	 * infrastructure DB specified by $wgCentralDBname.  This is metawiki for
	 * WMF sister projects.  Note that the infrastructure DB does not support
	 * table prefixes if running in multi-database mode.
	 *
	 * @param int $target Set to DB_MASTER or DB_SLAVE to force a connection
	 * to that database.  If no parameter is given, this will defaults to
	 * master for CentralNotice admin users, and the slave connection
	 * otherwise.
	 *
	 * @return DatabaseBase
	 */
	public static function getDb( $target = null ) {
		global $wgCentralDBname,
			$wgDBname,
			$wgUser;

		if ( $target === null ) {
			if ( $wgUser->isAllowed( 'centralnotice-admin' ) ) {
				$target = DB_MASTER;
			} else {
				$target = DB_SLAVE;
			}
		}

		if ( $wgCentralDBname === false || $wgCentralDBname === $wgDBname ) {
			return wfGetDB( $target );
		} else {
			return wfGetDB( $target, array(), $wgCentralDBname );
		}
	}
}
