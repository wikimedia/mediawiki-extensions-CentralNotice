-- Update of CentralNotice for planned 2.3 features
-- These include
-- * Mobile integration (carrier, device type selection)
-- * Ability to archive campaigns and banners
-- * Partial slot filling (campaign weighting)
-- * Stacked slots (controller mixins & optional banners)
-- * Tracking of used exported messages
-- * Support for banner categories beyond 'fundraising' and 'general'

ALTER TABLE /*_*/cn_notices
	DROP COLUMN `not_language`,
	DROP COLUMN `not_project`,
	ADD COLUMN `not_weight` int(11) NOT NULL DEFAULT '100',
	ADD COLUMN `not_mobile_carrier` tinyint(1) NOT NULL DEFAULT '0',
	ADD COLUMN `not_archived` tinyint(1) NOT NULL DEFAULT '0';

ALTER TABLE /*_*/cn_templates
	ADD COLUMN `tmp_archived` tinyint(1) NOT NULL DEFAULT '0',
	ADD COLUMN `tmp_category` tinyint NOT NULL DEFAULT '0',
	ADD COLUMN `tmp_preview_sandbox` tinyint(1) NOT NULL DEFAULT '0';

CREATE TABLE IF NOT EXISTS /*_*/cn_template_mixins (
	`tmxn_id` int PRIMARY KEY AUTO_INCREMENT,
	`tmp_id` int(11) NOT NULL,
	`page_id` int NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tmxn_tmp_id ON /*_*/cn_template_mixins (tmp_id);
CREATE INDEX /*i*/tmxn_page_id ON /*_*/cn_template_mixins (page_id);

CREATE TABLE IF NOT EXISTS /*_*/cn_known_devices (
	`dev_id` int PRIMARY KEY AUTO_INCREMENT,
	`dev_name` varchar(255) NOT NULL,
	`dev_display_label` varchar(255) binary NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/dev_name ON /*_*/cn_known_devices (dev_name);
INSERT INTO cn_known_devices VALUES (0, 'desktop', '{{int:centralnotice-devicetype-desktop}}');

CREATE TABLE IF NOT EXISTS /*_*/cn_template_devices (
	`tdev_id` int PRIMARY KEY AUTO_INCREMENT,
	`tmp_id` int(11) NOT NULL,
	`dev_id` int NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tdev_template_id ON /*_*/cn_template_devices (tmp_id);
INSERT INTO cn_template_devices (tmp_id, dev_id)
	SELECT tmp_id, dev_id
	FROM cn_templates, cn_known_devices
	WHERE dev_name='desktop';

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

ALTER TABLE /*_*/cn_notice_log
	ADD COLUMN `notlog_begin_mobile_carrier` int DEFAULT NULL,
	ADD COLUMN `notlog_end_mobile_carrier` int DEFAULT NULL,
	ADD COLUMN `notlog_begin_weight` int DEFAULT NULL,
	ADD COLUMN `notlog_end_weight` int DEFAULT NULL,
	ADD COLUMN `notlog_begin_archived` tinyint DEFAULT NULL,
	ADD COLUMN `notlog_end_archived` tinyint DEFAULT NULL;

ALTER TABLE /*_*/cn_template_log
	ADD COLUMN `tmplog_begin_archived` tinyint(1) DEFAULT NULL,
	ADD COLUMN `tmplog_end_archived` tinyint(1) DEFAULT NULL,
	ADD COLUMN `tmplog_begin_category` tinyint DEFAULT NULL,
	ADD COLUMN `tmplog_end_category` tinyint DEFAULT NULL,
	ADD COLUMN `tmplog_begin_preview_sandbox` tinyint(1) DEFAULT NULL,
	ADD COLUMN `tmplog_end_preview_sandbox` tinyint(1) DEFAULT NULL,
	ADD COLUMN `tmplog_begin_controller_mixin` varbinary(4096) DEFAULT NULL,
	ADD COLUMN `tmplog_end_controller_mixin` varbinary(4096) DEFAULT NULL;