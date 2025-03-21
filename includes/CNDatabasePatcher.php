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

		// 1.40
		$updater->dropExtensionIndex(
			'cn_notice_languages',
			'nl_notice_id_language',
			"$base/$dbType/patch-cn_notice_languages-unique-to-pk.sql"
		);
		$updater->dropExtensionIndex(
			'cn_notice_projects',
			'np_notice_id_project',
			"$base/$dbType/patch-cn_notice_projects-unique-to-pk.sql"
		);
		$updater->dropExtensionIndex(
			'cn_notice_countries',
			'nc_notice_id_country',
			"$base/$dbType/patch-cn_notice_countries-unique-to-pk.sql"
		);
		$updater->dropExtensionIndex(
			'cn_notice_regions',
			'nr_notice_id_region',
			"$base/$dbType/patch-cn_notice_regions-unique-to-pk.sql"
		);
	}
}
