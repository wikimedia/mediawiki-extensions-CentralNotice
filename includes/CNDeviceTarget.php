<?php

/**
 * Manages device targeting for CentralNotice banners
 */
class CNDeviceTarget {
	/**
	 * Get a listing of all known targetable devices.
	 *
	 * @param bool $flip If true will return {<header string value>: {'id': <id>, 'label': <wiki text label>}}
	 *
	 * @return array Array of devices in format {id: {'header': <internal string value>, 'label': <wiki text label>}}
	 */
	public static function getAvailableDevices( $flip = false ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$devices = array();

		$res = $dbr->select(
			array( 'known_devices' => 'cn_known_devices' ),
			array( 'dev_id', 'dev_name', 'dev_display_label' )
		);

		foreach( $res as $row ) {
			if ( $flip ) {
				$devices[ $row->dev_name ] = array(
					'label' => $row->dev_display_label,
					'id' => intval( $row->dev_id ),
				);
			} else {
				$devices[ intval( $row->dev_id ) ] = array(
					'header' => $row->dev_name,
					'label' => $row->dev_display_label,
				);
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
		$dbr = CNDatabase::getDb();

		$devices = array();

		$res = $dbr->select(
			array(
				 'tdev' => 'cn_template_devices',
				 'devices' => 'cn_known_devices'
			),
			array( 'devices.dev_id', 'dev_name' ),
			array(
				 'tdev.tmp_id' => $bannerId,
				 'tdev.dev_id = devices.dev_id'
			),
			__METHOD__
		);

		foreach( $res as $row ) {
			$devices[ intval( $row->dev_id ) ] = $row->dev_name;
		}

		return $devices;
	}

	/**
	 * Add a new device target to the database
	 *
	 * @param string $deviceName   Name of the device as sent by the controller (read: MobileFrontEnd)
	 * @param string $displayLabel Friendly wikitext name of the device
	 */
	public static function addDeviceTarget( $deviceName, $displayLabel ) {
		$db = CNDatabase::getDb();

		$db->insert(
			'cn_known_devices',
			array(
				 'dev_name' => $deviceName,
				 'dev_display_label' => $displayLabel
			),
			__METHOD__
		);
	}

	/**
	 * Sets the associated devices with a banner
	 *
	 * @param int    $bannerId   Banner ID to modify
	 * @param string $newDevices Names of devices that should be associated
	 */
	public static function setBannerDeviceTargets( $bannerId, $newDevices ) {
		$db = CNDatabase::getDb();

		$knownDevices = CNDeviceTarget::getAvailableDevices( true );
		$oldDevices = CNDeviceTarget::getDevicesAssociatedWithBanner( $bannerId );

		// Add newly assigned countries
		$modifyArray = array();
		$addDevices = array_diff( $newDevices, $oldDevices );
		foreach ( $addDevices as $device ) {
			$modifyArray[ ] = array( 'tmp_id' => $bannerId, 'dev_id' => $knownDevices[ $device ][ 'id' ] );
		}
		$db->insert( 'cn_template_devices', $modifyArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated countries
		$modifyArray = array();
		$removeDevices = array_diff( $oldDevices, $newDevices );
		if ( $removeDevices ) {
			foreach( $removeDevices as $device ) {
				$modifyArray[] = $knownDevices[ $device ][ 'id' ];
			}
			$db->delete( 'cn_template_devices',
				array( 'tmp_id' => $bannerId, 'dev_id' => $modifyArray )
			);
		}
	}
}
