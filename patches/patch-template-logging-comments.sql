-- Update to support comments in template logs

-- Add a comment field to cn_template_log
ALTER TABLE /*_*/cn_template_log ADD `tmplog_comment` varchar(255) DEFAULT NULL;