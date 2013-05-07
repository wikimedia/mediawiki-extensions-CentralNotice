-- We support device targetting on banners but need to log it!
ALTER TABLE /*_*/cn_template_log ADD (
  `tmplog_begin_devices` varbinary(512) DEFAULT NULL,
  `tmplog_end_devices` varbinary(512) DEFAULT NULL
);
UPDATE /*_*/cn_template_log
  SET
    tmplog_begin_devices = '["desktop"]',
    tmplog_end_devices = '["desktop"]';
