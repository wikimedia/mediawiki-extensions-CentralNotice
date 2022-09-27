ALTER TABLE /*_*/cn_notice_log
	ADD COLUMN `notlog_begin_type` varchar(255) DEFAULT NULL,
	ADD COLUMN `notlog_end_type` varchar(255) DEFAULT NULL;
