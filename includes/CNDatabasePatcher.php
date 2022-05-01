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

		if ( $updater->getDB()->getType() === 'mysql' ) {
			$updater->addExtensionTable(
				'cn_notices',
					$base . '/CentralNotice.sql'
			);

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
		} elseif ( $updater->getDB()->getType() === 'sqlite' ) {
			// Add the entire schema...
			$updater->addExtensionTable(
				'cn_notices',
				$base . '/CentralNotice.sql'
			);
		}
		return true;
	}
}
