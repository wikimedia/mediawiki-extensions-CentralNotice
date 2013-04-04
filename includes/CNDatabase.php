<?php

/**
 * Utility functions for CentralNotice that don't belong elsewhere
 */
class CNDatabase {
	/**
	 * Gets a database object. Will be the master if the user is logged in.
	 *
	 * @param int|bool    $force   If false will return a DB master/slave based on users permissions.
	 *                             Set to DB_MASTER or DB_SLAVE to force that type.
	 * @param string|bool $wiki    Wiki database to connect to, if false will be the Infrastructure DB
	 *
	 * @return DatabaseBase
	 */
	public static function getDb( $force = false, $wiki = false ) {
		global $wgCentralDBname;
		global $wgUser;

		if ( $wgUser->isAllowed( 'centralnotice-admin' ) ) {
			$dbmode = DB_MASTER;
		} elseif ( $force === false ) {
			$dbmode = DB_SLAVE;
		} else {
			$dbmode = $force;
		}

		$db = ( $wiki === false ) ? $wgCentralDBname : $wiki;

		return wfGetDB( $dbmode, array(), $db );
	}
}
