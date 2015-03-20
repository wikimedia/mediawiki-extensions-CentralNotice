-- Add columns to the cn_notice_log for notice (campaign) mixin settings

ALTER TABLE /*_*/cn_notice_log
	ADD COLUMN `notlog_begin_mixins` BLOB DEFAULT NULL,
	ADD COLUMN `notlog_end_mixins` BLOB DEFAULT NULL;