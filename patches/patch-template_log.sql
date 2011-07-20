-- Update to allow for logging of changes to banner settings.

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_template_log (
	`templog_id` int unsigned NOT NULL PRIMARY KEY auto_increment,
	`templog_timestamp` binary(14) NOT NULL,
	`templog_user_id` int unsigned NOT NULL,
	`templog_action` enum('created','modified','removed') NOT NULL DEFAULT 'modified',
	`templog_template_id` int unsigned NOT NULL,
	`templog_template_name` varchar(255) DEFAULT NULL,
	`templog_begin_anon_display` tinyint(1) DEFAULT NULL,
	`templog_end_anon_display` tinyint(1) DEFAULT NULL,
	`templog_begin_account_display` tinyint(1) DEFAULT NULL,
	`templog_end_account_display` tinyint(1) DEFAULT NULL,
	`templog_begin_fundraising` tinyint(1) DEFAULT NULL,
	`templog_end_fundraising` tinyint(1) DEFAULT NULL,
	`templog_begin_landing_pages` varchar(255) DEFAULT NULL,
	`templog_end_landing_pages` varchar(255) DEFAULT NULL,
	`templog_content_change` tinyint(1) DEFAULT 0
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/templog_timestamp ON /*_*/cn_template_log (templog_timestamp);
CREATE INDEX /*i*/templog_user_id ON /*_*/cn_template_log (templog_user_id, templog_timestamp);
CREATE INDEX /*i*/templog_template_id ON /*_*/cn_template_log (templog_template_id, templog_timestamp);