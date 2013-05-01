<?php
/**
 * @file
 * @license GNU General Public Licence 2.0 or later
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
	 * @param $updater DatabaseUpdater|null
	 * @return bool
	 */
	public static function applyUpdates( $updater = null ) {
		$base = __DIR__;

		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_notices',
					 $base . '/../CentralNotice.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_notices', 'not_preferred',
					 $base . '/patch-notice_preferred.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_notice_languages',
					 $base . '/patch-notice_languages.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_templates', 'tmp_display_anon',
					 $base . '/patch-template_settings.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_templates', 'tmp_fundraising',
					 $base . '/patch-template_fundraising.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_notice_countries',
					 $base . '/patch-notice_countries.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_notice_projects',
					 $base . '/patch-notice_projects.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_notice_log',
					 $base . '/patch-notice_log.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_template_log',
					 $base . '/patch-template_log.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_templates', 'tmp_autolink',
					 $base . '/patch-template_autolink.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_template_log', 'tmplog_begin_prioritylangs',
					 $base . '/patch-prioritylangs.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_notices', 'not_buckets',
					 $base . '/patch-bucketing.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addTable', 'cn_template_mixins',
					 $base . '/patch-centralnotice-2_3.sql', true
				)
			);
			$updater->addExtensionUpdate(
				array(
					 'addField', 'cn_template_mixins', 'mixin_name',
					 $base . '/patch-mixin_modules.sql', true
				)
			);
		} elseif ( $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionUpdate(
				array(
					'addTable', 'cn_notices',
					$base . '/../CentralNotice.sql', true
				)
			);
		}
		return true;
	}
}
