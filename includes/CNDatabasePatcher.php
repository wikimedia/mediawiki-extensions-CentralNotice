<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

/**
 * Maintenance helper class that updates the database schema when required.
 *
 * Apply patches with /maintenance/update.php
 */
class CNDatabasePatcher {
	/**
	 * LoadExtensionSchemaUpdates hook handler
	 * This function makes sure that the database schema is up to date.
	 *
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function applyUpdates( $updater = null ) {
		$base = __DIR__ . '/../sql';
		$dbType = $updater->getDB()->getType();
		$updater->addExtensionTable( 'cn_notices', "$base/$dbType/tables-generated.sql" );

		if ( $dbType === 'mysql' ) {
			// 1.35
			// Adds geotargeted regions for notices and the corresponding log columns
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_regions',
					$base . '/mysql/patch-notice_regions.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_templates', 'tmp_is_template',
					$base . '/mysql/patch_template_banners.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'modifyField', 'cn_templates', 'tmp_is_template',
					$base . '/mysql/patch_template_banners_field_update.sql', true
				]
			);

			// 1.36
			$updater->addExtensionField(
				'cn_notices',
				'not_type',
				$base . '/mysql/patch-notice_not_type.sql'
			);
			// This adds both notlog_begin_type and notlog_end_type fields
			$updater->addExtensionField(
				'cn_notice_log',
				'notlog_begin_type',
				$base . '/mysql/patch-notice-type-log.sql'
			);

			// 1.39
			$updater->modifyExtensionField(
				'cn_notices',
				'not_end',
				$base . '/mysql/patch-cn_notices-timestamps.sql'
			);
			$updater->modifyExtensionField(
				'cn_notice_log',
				'notlog_end_end',
				$base . '/mysql/patch-cn_notice_log-timestamps.sql'
			);
		}

		$updater->addExtensionUpdate( [
			[ __CLASS__, 'doOnSchemaUpdatesPopulateKnownDevices' ],
		] );

		return true;
	}

	public static function doOnSchemaUpdatesPopulateKnownDevices( DatabaseUpdater $updater ): void {
		$updateKey = 'populateKnownDevices-1.24';
		if ( $updater->updateRowExists( $updateKey ) ) {
			$updater->output( "...default known devices already added\n" );
			return;
		}

		$updater->output( "Adding known devices...\n" );
		$dbw = $updater->getDB();
		$dbw->insert(
			'cn_known_devices', [
				[ 'dev_id' => 1, 'dev_name' => 'desktop',
					'dev_display_label' => '{{int:centralnotice-devicetype-desktop}}' ],
				// 1.24
				[ 'dev_id' => 2, 'dev_name' => 'android',
					'dev_display_label' => '{{int:centralnotice-devicetype-android}}' ],
				[ 'dev_id' => 3, 'dev_name' => 'iphone',
					'dev_display_label' => '{{int:centralnotice-devicetype-iphone}}' ],
				[ 'dev_id' => 4, 'dev_name' => 'ipad',
					'dev_display_label' => '{{int:centralnotice-devicetype-ipad}}' ],
				[ 'dev_id' => 5, 'dev_name' => 'unknown',
					'dev_display_label' => '{{int:centralnotice-devicetype-unknown}}' ],
			],
			__METHOD__,
			[ 'IGNORE' ]
		);

		$updater->output( "Done\n" );
		$updater->insertUpdateRow( $updateKey );
	}
}
