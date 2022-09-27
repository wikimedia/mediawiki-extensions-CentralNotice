-- Adds geotargeted regions for notices.

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_regions (
	nr_notice_id int(10) unsigned NOT NULL,
	nr_region varchar(6) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/nr_notice_id_region ON /*_*/cn_notice_regions (nr_notice_id, nr_region);

-- Add a regions field to cn_notice_log

ALTER TABLE /*_*/cn_notice_log ADD `notlog_begin_regions` text;
ALTER TABLE /*_*/cn_notice_log ADD `notlog_end_regions` text;
