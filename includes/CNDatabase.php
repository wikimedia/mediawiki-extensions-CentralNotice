<?php

/**
 * Utility functions for CentralNotice that don't belong elsewhere
 */
class CNDatabase {
	/**
	 * Gets a database object. Will be the master if the user is logged in.
	 *
	 * @param string|bool $wiki        Wiki database to connect to, if false will be
	 *                                 the Infrastructure DB
	 * @param bool        $forceSlave  If true will force a slave connection; otherwise
	 *                                 this will return a master if logged in.
	 *
	 * @return DatabaseBase
	 */
	public static function getDb( $wiki = false, $forceSlave = false ) {
		global $wgCentralDBname;
		global $wgUser;

		$dbmode = DB_SLAVE;
		if ( $wgUser->isLoggedIn() && !$forceSlave ) {
			$dbmode = DB_MASTER;
		}

		$db = ( $wiki === false ) ? $wgCentralDBname : $wiki;

		return wfGetDB( $dbmode, array(), $db );
	}
}
