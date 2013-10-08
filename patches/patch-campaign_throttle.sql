ALTER TABLE /*_*/cn_notices
	ADD COLUMN `not_throttle` INT NOT NULL DEFAULT '100';

ALTER TABLE /*_*/cn_notice_log
	ADD COLUMN `notlog_begin_throttle` INT DEFAULT NULL,
	ADD COLUMN `notlog_end_throttle` INT DEFAULT NULL;
