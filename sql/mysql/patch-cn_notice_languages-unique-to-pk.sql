-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-cn_notice_languages-unique-to-pk.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP  INDEX nl_notice_id_language ON  /*_*/cn_notice_languages;
ALTER TABLE  /*_*/cn_notice_languages
ADD  PRIMARY KEY (nl_notice_id, nl_language);