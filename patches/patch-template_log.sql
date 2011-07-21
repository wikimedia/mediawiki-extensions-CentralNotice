-- Update to allow for logging of changes to banner settings.

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_template_log (
	`tmplog_id` int unsigned NOT NULL PRIMARY KEY auto_increment,
	`tmplog_timestamp` binary(14) NOT NULL,
	`tmplog_user_id` int unsigned NOT NULL,
	`tmplog_action` enum('created','modified','removed') NOT NULL DEFAULT 'modified',
	`tmplog_template_id` int unsigned NOT NULL,
	`tmplog_template_name` varchar(255) DEFAULT NULL,
	`tmplog_begin_anon_display` tinyint(1) DEFAULT NULL,
	`tmplog_end_anon_display` tinyint(1) DEFAULT NULL,
	`tmplog_begin_account_display` tinyint(1) DEFAULT NULL,
	`tmplog_end_account_display` tinyint(1) DEFAULT NULL,
	`tmplog_begin_fundraising` tinyint(1) DEFAULT NULL,
	`tmplog_end_fundraising` tinyint(1) DEFAULT NULL,
	`tmplog_begin_landing_pages` varchar(255) DEFAULT NULL,
	`tmplog_end_landing_pages` varchar(255) DEFAULT NULL,
	`tmplog_content_change` tinyint(1) DEFAULT 0
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/tmplog_timestamp ON /*_*/cn_template_log (tmplog_timestamp);
CREATE INDEX /*i*/tmplog_user_id ON /*_*/cn_template_log (tmplog_user_id, tmplog_timestamp);
CREATE INDEX /*i*/tmplog_template_id ON /*_*/cn_template_log (tmplog_template_id, tmplog_timestamp);