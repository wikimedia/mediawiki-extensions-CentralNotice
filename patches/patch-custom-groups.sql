-- Going ahead and allowing custom banner categories; but it turns
-- out that these need to be more generic than originally thought.
ALTER TABLE /*_*/cn_templates
  MODIFY COLUMN `tmp_category` varchar(255) DEFAULT NULL;
CREATE INDEX /*i*/tmp_category ON /*_*/cn_templates (tmp_category);

ALTER TABLE /*_*/cn_template_log
  MODIFY COLUMN `tmplog_begin_category` varchar(255) DEFAULT NULL,
  MODIFY COLUMN `tmplog_end_category` varchar(255) DEFAULT NULL;
UPDATE /*_*/cn_template_log
  SET `tmplog_begin_category` = '{{{campaign}}}' WHERE `tmplog_begin_fundraising` = 0;
UPDATE /*_*/cn_template_log
  SET `tmplog_begin_category` = 'fundraising' WHERE `tmplog_begin_fundraising` = 1;
UPDATE /*_*/cn_template_log
  SET `tmplog_end_category` = '{{{campaign}}}' WHERE `tmplog_end_fundraising` = 0;
UPDATE /*_*/cn_template_log
  SET `tmplog_end_category` = 'fundraising' WHERE `tmplog_end_fundraising` = 1;

UPDATE /*_*/cn_templates
  SET `tmp_category` = 'fundraising' WHERE `tmp_fundraising` = 1;
UPDATE /*_*/cn_templates
  SET `tmp_category` = '{{{campaign}}}' WHERE `tmp_fundraising` = 0;