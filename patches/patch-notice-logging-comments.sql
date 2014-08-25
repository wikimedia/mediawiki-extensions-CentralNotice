-- Update to support comments in campaigns logs

-- Add a comment field to cn_notice_log
ALTER TABLE /*_*/cn_notice_log ADD `notlog_comment` varchar(255) DEFAULT NULL;