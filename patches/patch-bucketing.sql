-- Support for bucketing within a campaign. The not_bucket value will indicate
-- whether user bucketing is turned on or off.

ALTER TABLE /*$wgDBprefix*/cn_notices ADD COLUMN not_buckets tinyint(1) NOT NULL default 1;
ALTER TABLE /*$wgDBprefix*/cn_assignments ADD COLUMN asn_bucket tinyint(1) default 0;
ALTER TABLE /*$wgDBprefix*/cn_notice_log ADD COLUMN notlog_begin_buckets tinyint(1) default NULL;
ALTER TABLE /*$wgDBprefix*/cn_notice_log ADD COLUMN notlog_end_buckets tinyint(1) default NULL;
