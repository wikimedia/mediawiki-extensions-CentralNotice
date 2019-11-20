-- CentralNotice Schema Install File
-- Last Update: patch-add_devices.sql
-- See documentation at https://www.mediawiki.org/wiki/Extension:CentralNotice/Database_schema

CREATE TABLE IF NOT EXISTS /*_*/cn_notices (
	`not_id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`not_name` varchar(255) NOT NULL,
	`not_start` char(14) NOT NULL,
	`not_end` char(14) NOT NULL,
	`not_enabled` tinyint(1) NOT NULL DEFAULT '0',
	`not_preferred` tinyint(1) NOT NULL DEFAULT '0',
	`not_throttle` int NOT NULL DEFAULT '100',
	`not_locked` tinyint(1) NOT NULL DEFAULT '0',
	`not_geo` tinyint(1) NOT NULL DEFAULT '0',
	`not_buckets` tinyint(1) NOT NULL DEFAULT '1',
	`not_weight` int(11) NOT NULL DEFAULT '100',
	`not_mobile_carrier` tinyint(1) NOT NULL DEFAULT '0',
	`not_archived` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/cn_assignments (
	`asn_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`not_id` int(11) NOT NULL,
	`tmp_id` int(11) NOT NULL,
	`tmp_weight` int(11) NOT NULL,
	`asn_bucket` tinyint(1) DEFAULT '0'
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/asn_not ON /*_*/cn_assignments (not_id);
CREATE INDEX /*i*/asn_tmp ON /*_*/cn_assignments (tmp_id);
CREATE INDEX /*i*/asn_bucket ON /*_*/cn_assignments (asn_bucket);

CREATE TABLE IF NOT EXISTS /*_*/cn_templates (
	`tmp_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`tmp_name` varchar(255) DEFAULT NULL,
	`tmp_display_anon` tinyint(1) NOT NULL DEFAULT '1',
	`tmp_display_account` tinyint(1) NOT NULL DEFAULT '1',
	`tmp_fundraising` tinyint(1) NOT NULL DEFAULT '0',
	`tmp_autolink` tinyint(1) NOT NULL DEFAULT '0', -- Note: no longer used
	`tmp_landing_pages` varchar(255) DEFAULT NULL, -- Note: no longer used
	`tmp_archived` tinyint(1) NOT NULL DEFAULT '0',
	`tmp_category` varchar(255) DEFAULT NULL,
	`tmp_preview_sandbox` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tmp_name ON /*_*/cn_templates (tmp_name);
CREATE INDEX /*i*/tmp_category ON /*_*/cn_templates (tmp_category);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_languages (
	`nl_notice_id` int(10) unsigned NOT NULL,
	`nl_language` varchar(32) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/nl_notice_id_language ON /*_*/cn_notice_languages (nl_notice_id, nl_language);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_projects (
	`np_notice_id` int(10) unsigned NOT NULL,
	`np_project` varchar(32) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/np_notice_id_project ON /*_*/cn_notice_projects (np_notice_id, np_project);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_countries (
	`nc_notice_id` int(10) unsigned NOT NULL,
	`nc_country` varchar(2) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/nc_notice_id_country ON /*_*/cn_notice_countries (nc_notice_id, nc_country);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_regions (
	`nr_notice_id` int(10) unsigned NOT NULL,
	`nr_region` varchar(6) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/nr_notice_id_region ON /*_*/cn_notice_regions (nr_notice_id, nr_region);

CREATE TABLE IF NOT EXISTS /*_*/cn_template_mixins (
	`tmxn_id` int PRIMARY KEY AUTO_INCREMENT,
	`tmp_id` int(11) NOT NULL,
	`page_id` int NOT NULL,
	`mixin_name` varchar(255) NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tmxn_tmp_id ON /*_*/cn_template_mixins (tmp_id);
CREATE INDEX /*i*/tmxn_page_id ON /*_*/cn_template_mixins (page_id);
CREATE INDEX /*i*/tmxn_mixin_name ON /*_*/cn_template_mixins (mixin_name);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_mixins (
	`nmxn_id` int PRIMARY KEY AUTO_INCREMENT,
	`nmxn_not_id` int(11) NOT NULL,
	`nmxn_mixin_name` varchar(255) NOT NULL,
	`nmxn_enabled` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/nmxn_not_id_idx ON /*_*/cn_notice_mixins (nmxn_not_id);
CREATE INDEX /*i*/nmxn_mixin_name_idx ON /*_*/cn_notice_mixins (nmxn_mixin_name);
CREATE UNIQUE INDEX /*i*/nmxn_not_id_mixin_name ON /*_*/cn_notice_mixins (nmxn_not_id, nmxn_mixin_name);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_mixin_params (
	`nmxnp_id` int PRIMARY KEY AUTO_INCREMENT,
	`nmxnp_notice_mixin_id` int(11) NOT NULL,
	`nmxnp_param_name` varchar(255) NOT NULL,
	`nmxnp_param_value` TEXT NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/nmxnp_notice_mixin_id_idx ON /*_*/cn_notice_mixin_params (nmxnp_notice_mixin_id);
CREATE INDEX /*i*/nmxnp_param_name_value_idx ON /*_*/cn_notice_mixin_params (nmxnp_param_name, nmxnp_param_value(50));
CREATE UNIQUE INDEX /*i*/nmxn_notice_mixin_id_param_name ON /*_*/cn_notice_mixin_params (nmxnp_notice_mixin_id, nmxnp_param_name);

CREATE TABLE IF NOT EXISTS /*_*/cn_known_devices (
	`dev_id` int PRIMARY KEY AUTO_INCREMENT,
	`dev_name` varchar(255) NOT NULL,
	`dev_display_label` varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/dev_name ON /*_*/cn_known_devices (dev_name);
INSERT INTO /*_*/cn_known_devices VALUES (1, 'desktop', '{{int:centralnotice-devicetype-desktop}}');
INSERT INTO /*_*/cn_known_devices VALUES (2, 'android', '{{int:centralnotice-devicetype-android}}');
INSERT INTO /*_*/cn_known_devices VALUES (3, 'iphone', '{{int:centralnotice-devicetype-iphone}}');
INSERT INTO /*_*/cn_known_devices VALUES (4, 'ipad', '{{int:centralnotice-devicetype-ipad}}');
INSERT INTO /*_*/cn_known_devices VALUES (5, 'unknown', '{{int:centralnotice-devicetype-unknown}}');

CREATE TABLE IF NOT EXISTS /*_*/cn_template_devices (
	`tdev_id` int PRIMARY KEY AUTO_INCREMENT,
	`tmp_id` int(11) NOT NULL,
	`dev_id` int NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tdev_template_id ON /*_*/cn_template_devices (tmp_id);

CREATE TABLE IF NOT EXISTS /*_*/cn_known_mobile_carriers (
	`mc_id` int PRIMARY KEY AUTO_INCREMENT,
	`mc_name` varchar(255) NOT NULL,
	`mc_display_label` varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mc_name ON /*_*/cn_known_mobile_carriers (mc_name);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_mobile_carriers (
	`nmc_id` int PRIMARY KEY AUTO_INCREMENT,
	`not_id` int NOT NULL,
	`mc_id` int NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/nmc_not_id ON /*_*/cn_notice_mobile_carriers (not_id);
CREATE INDEX /*i*/nmc_carrier_id ON /*_*/cn_notice_mobile_carriers (mc_id);

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_log (
	`notlog_id` int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`notlog_timestamp` binary(14) NOT NULL,
	`notlog_user_id` int(10) unsigned NOT NULL,
	`notlog_action` enum('created','modified','removed') NOT NULL DEFAULT 'modified',
	`notlog_not_id` int(10) unsigned NOT NULL,
	`notlog_not_name` varchar(255) DEFAULT NULL,
	`notlog_begin_projects` varchar(255) DEFAULT NULL,
	`notlog_end_projects` varchar(255) DEFAULT NULL,
	`notlog_begin_languages` text,
	`notlog_end_languages` text,
	`notlog_begin_countries` text,
	`notlog_end_countries` text,
	`notlog_begin_regions` text,
	`notlog_end_regions` text,
	`notlog_begin_start` char(14) DEFAULT NULL,
	`notlog_end_start` char(14) DEFAULT NULL,
	`notlog_begin_end` char(14) DEFAULT NULL,
	`notlog_end_end` char(14) DEFAULT NULL,
	`notlog_begin_enabled` tinyint(1) DEFAULT NULL,
	`notlog_end_enabled` tinyint(1) DEFAULT NULL,
	`notlog_begin_preferred` tinyint(1) DEFAULT NULL,
	`notlog_end_preferred` tinyint(1) DEFAULT NULL,
	`notlog_begin_throttle` int DEFAULT NULL,
	`notlog_end_throttle` int DEFAULT NULL,
	`notlog_begin_locked` tinyint(1) DEFAULT NULL,
	`notlog_end_locked` tinyint(1) DEFAULT NULL,
	`notlog_begin_geo` tinyint(1) DEFAULT NULL,
	`notlog_end_geo` tinyint(1) DEFAULT NULL,
	`notlog_begin_banners` text,
	`notlog_end_banners` text,
	`notlog_begin_buckets` tinyint(1) DEFAULT NULL,
	`notlog_end_buckets` tinyint(1) DEFAULT NULL,
	`notlog_begin_mobile_carrier` int DEFAULT NULL,
	`notlog_end_mobile_carrier` int DEFAULT NULL,
	`notlog_begin_weight` int DEFAULT NULL,
	`notlog_end_weight` int DEFAULT NULL,
	`notlog_begin_archived` tinyint DEFAULT NULL,
	`notlog_end_archived` tinyint DEFAULT NULL,
	`notlog_begin_mixins` BLOB DEFAULT NULL,
	`notlog_end_mixins` BLOB DEFAULT NULL,
	`notlog_comment` varchar(255) DEFAULT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/notlog_timestamp ON /*_*/cn_notice_log (notlog_timestamp);
CREATE INDEX /*i*/notlog_user_id ON /*_*/cn_notice_log (notlog_user_id, notlog_timestamp);
CREATE INDEX /*i*/notlog_not_id ON /*_*/cn_notice_log (notlog_not_id, notlog_timestamp);

CREATE TABLE IF NOT EXISTS /*_*/cn_template_log (
	`tmplog_id` int(10) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`tmplog_timestamp` binary(14) NOT NULL,
	`tmplog_user_id` int(10) unsigned NOT NULL,
	`tmplog_action` enum('created','modified','removed') NOT NULL DEFAULT 'modified',
	`tmplog_template_id` int(10) unsigned NOT NULL,
	`tmplog_template_name` varchar(255) DEFAULT NULL,
	`tmplog_begin_anon` tinyint(1) DEFAULT NULL,
	`tmplog_end_anon` tinyint(1) DEFAULT NULL,
	`tmplog_begin_account` tinyint(1) DEFAULT NULL,
	`tmplog_end_account` tinyint(1) DEFAULT NULL,
	`tmplog_begin_fundraising` tinyint(1) DEFAULT NULL,
	`tmplog_end_fundraising` tinyint(1) DEFAULT NULL,
	`tmplog_begin_autolink` tinyint(1) DEFAULT NULL,
	`tmplog_end_autolink` tinyint(1) DEFAULT NULL,
	`tmplog_begin_landingpages` varchar(255) DEFAULT NULL,
	`tmplog_end_landingpages` varchar(255) DEFAULT NULL,
	`tmplog_content_change` tinyint(1) DEFAULT '0',
	`tmplog_begin_prioritylangs` text,
	`tmplog_end_prioritylangs` text,
	`tmplog_begin_archived` tinyint(1) DEFAULT NULL,
	`tmplog_end_archived` tinyint(1) DEFAULT NULL,
	`tmplog_begin_category` varchar(255) DEFAULT NULL,
	`tmplog_end_category` varchar(255) DEFAULT NULL,
	`tmplog_begin_preview_sandbox` tinyint(1) DEFAULT NULL,
	`tmplog_end_preview_sandbox` tinyint(1) DEFAULT NULL,
	`tmplog_begin_controller_mixin` varbinary(4096) DEFAULT NULL,
	`tmplog_end_controller_mixin` varbinary(4096) DEFAULT NULL,
	`tmplog_begin_devices` varbinary(512) DEFAULT NULL,
	`tmplog_end_devices` varbinary(512) DEFAULT NULL,
	`tmplog_comment` varchar(255) DEFAULT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tmplog_timestamp ON /*_*/cn_template_log (tmplog_timestamp);
CREATE INDEX /*i*/tmplog_user_id ON /*_*/cn_template_log (tmplog_user_id, tmplog_timestamp);
CREATE INDEX /*i*/tmplog_template_id ON /*_*/cn_template_log (tmplog_template_id, tmplog_timestamp);
