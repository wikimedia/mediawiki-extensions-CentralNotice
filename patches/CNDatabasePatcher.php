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
		$base = __DIR__;

		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notices',
					$base . '/../CentralNotice.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_notices', 'not_preferred',
					$base . '/patch-notice_preferred.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_languages',
					$base . '/patch-notice_languages.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_templates', 'tmp_display_anon',
					$base . '/patch-template_settings.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_templates', 'tmp_fundraising',
					$base . '/patch-template_fundraising.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_countries',
					$base . '/patch-notice_countries.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_projects',
					$base . '/patch-notice_projects.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_log',
					$base . '/patch-notice_log.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_template_log',
					$base . '/patch-template_log.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_templates', 'tmp_autolink',
					$base . '/patch-template_autolink.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_template_log', 'tmplog_begin_prioritylangs',
					$base . '/patch-prioritylangs.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_notices', 'not_buckets',
					$base . '/patch-bucketing.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_template_mixins',
					$base . '/patch-centralnotice-2_3.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_template_mixins', 'mixin_name',
					$base . '/patch-mixin_modules.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_template_log', 'tmplog_begin_devices',
					$base . '/patch-template-device-logging.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addIndex', 'cn_templates', 'tmp_category',
					$base . '/patch-custom-groups.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_notices', 'not_throttle',
					$base . '/patch-campaign_throttle.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					/* This is a hack... we're adding rows not modifying a field */
					'modifyField', 'cn_known_devices', 'dev_name',
					$base . '/patch-add_devices.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_template_log', 'tmplog_comment',
					$base . '/patch-template-logging-comments.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_notice_log', 'notlog_comment',
					$base . '/patch-notice-logging-comments.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addIndex', 'cn_assignments', 'asn_bucket',
					$base . '/patch-assignments_index.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_mixins',
					$base . '/patch-notice-mixins.sql', true
				]
			);
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notice_mixin_params',
					$base . '/patch-notice-mixins-params.sql', true
				]
			);
			// This adds both notlog_begin_mixins and notlog_end_mixins fields
			$updater->addExtensionUpdate(
				[
					'addField', 'cn_notice_log', 'notlog_begin_mixins',
					$base . '/patch-notice-mixins-log.sql', true
				]
			);
		} elseif ( $updater->getDB()->getType() == 'sqlite' ) {
			// Add the entire schema...
			$updater->addExtensionUpdate(
				[
					'addTable', 'cn_notices',
					$base . '/../CentralNotice.sql', true
				]
			);
		}
		return true;
	}
}
