<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Maintenance helper class that updates the database schema when required.
 *
 * Apply patches with /maintenance/update.php
 */
class CNDatabasePatcher implements LoadExtensionSchemaUpdatesHook {
	/**
	 * LoadExtensionSchemaUpdates hook handler
	 * This function makes sure that the database schema is up to date.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . '/../sql';
		$dbType = $updater->getDB()->getType();

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			'addTable',
			'cn_notices',
			"$base/$dbType/tables-generated.sql",
			true
		] );

		if ( $dbType === 'mysql' ) {
			// 1.35
			// Adds geotargeted regions for notices and the corresponding log columns
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'addTable',
				'cn_notice_regions',
				$base . '/mysql/patch-notice_regions.sql',
				true
			] );
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'addField',
				'cn_templates',
				'tmp_is_template',
				$base . '/mysql/patch_template_banners.sql',
				true
			] );
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'modifyField',
				'cn_templates',
				'tmp_is_template',
				$base . '/mysql/patch_template_banners_field_update.sql',
				true
			] );

			// 1.36
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'addField',
				'cn_notices',
				'not_type',
				$base . '/mysql/patch-notice_not_type.sql',
				true
			] );
			// This adds both notlog_begin_type and notlog_end_type fields
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'addField',
				'cn_notice_log',
				'notlog_begin_type',
				$base . '/mysql/patch-notice-type-log.sql',
				true
			] );

			// 1.39
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'modifyField',
				'cn_notices',
				'not_end',
				$base . '/mysql/patch-cn_notices-timestamps.sql',
				true
			] );
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-centralnotice',
				'modifyField',
				'cn_notice_log',
				'notlog_end_end',
				$base . '/mysql/patch-cn_notice_log-timestamps.sql',
				true
			] );
		}

		// 1.40
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			'dropIndex',
			'cn_notice_languages',
			'nl_notice_id_language',
			"$base/$dbType/patch-cn_notice_languages-unique-to-pk.sql"
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			'dropIndex',
			'cn_notice_projects',
			'np_notice_id_project',
			"$base/$dbType/patch-cn_notice_projects-unique-to-pk.sql"
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			'dropIndex',
			'cn_notice_countries',
			'nc_notice_id_country',
			"$base/$dbType/patch-cn_notice_countries-unique-to-pk.sql"
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			'dropIndex',
			'cn_notice_regions',
			'nr_notice_id_region',
			"$base/$dbType/patch-cn_notice_regions-unique-to-pk.sql"
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-centralnotice',
			[ __CLASS__, 'doOnSchemaUpdatesPopulateKnownDevices' ],
		] );
	}

	/**
	 * While this is pre-1.24... The schema updates do not insert these necessary rows.
	 */
	public static function doOnSchemaUpdatesPopulateKnownDevices( DatabaseUpdater $updater ): void {
		$updateKey = 'populateKnownDevices-1.24';
		if ( $updater->updateRowExists( $updateKey ) ) {
			$updater->output( "...default known devices already added\n" );
			return;
		}

		$updater->output( "Adding known devices...\n" );
		$dbw = $updater->getDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'cn_known_devices' )
			->ignore()
			->rows( [
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
			] )
			->caller( __METHOD__ )
			->execute();

		$updater->output( "Done\n" );
		$updater->insertUpdateRow( $updateKey );
	}
}
