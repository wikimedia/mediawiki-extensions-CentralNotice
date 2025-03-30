<?php

/**
 * Manages device targeting for CentralNotice banners
 */
class CNDeviceTarget {
	/**
	 * Get a listing of all known targetable devices.
	 *
	 * @param bool $flip If true will return
	 *   {<header string value>: {'id': <id>, 'label': <wiki text label>}}
	 *
	 * @return array[] Array of devices in format
	 *   {id: {'header': <internal string value>, 'label': <wiki text label>}}
	 */
	public static function getAvailableDevices( $flip = false ) {
		$dbr = CNDatabase::getReplicaDb();

		$devices = [];

		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'dev_id', 'dev_name', 'dev_display_label' ] )
			->from( 'cn_known_devices' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			if ( $flip ) {
				$devices[ $row->dev_name ] = [
					'label' => $row->dev_display_label,
					'id' => intval( $row->dev_id ),
				];
			} else {
				$devices[ intval( $row->dev_id ) ] = [
					'header' => $row->dev_name,
					'label' => $row->dev_display_label,
				];
			}

		}

		return $devices;
	}

	/**
	 * Obtain all device IDs associated with a given banner ID
	 *
	 * @param int $bannerId
	 *
	 * @return array Device names that are associated with the banner
	 */
	public static function getDevicesAssociatedWithBanner( $bannerId ) {
		$dbr = CNDatabase::getReplicaDb();

		$devices = [];

		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'devices.dev_id', 'dev_name' ] )
			->from( 'cn_template_devices', 'tdev' )
			->join( 'cn_known_devices', 'devices', 'tdev.dev_id = devices.dev_id' )
			->where( [
				'tdev.tmp_id' => $bannerId,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$devices[ intval( $row->dev_id ) ] = $row->dev_name;
		}

		return $devices;
	}

	/**
	 * Add a new device target to the database
	 *
	 * @param string $deviceName Name of the device as sent by the controller (read: MobileFrontEnd)
	 * @param string $displayLabel Friendly wikitext name of the device
	 * @return int The ID of the device added
	 */
	public static function addDeviceTarget( $deviceName, $displayLabel ) {
		$db = CNDatabase::getPrimaryDb();

		$db->newInsertQueryBuilder()
			->insertInto( 'cn_known_devices' )
			->row( [
				'dev_name' => $deviceName,
				'dev_display_label' => $displayLabel
			] )
			->caller( __METHOD__ )
			->execute();

		return $db->insertId();
	}
}
