-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: sql/abstractSchemaChanges/patch-cn_notice_countries-unique-to-pk.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP  INDEX nc_notice_id_country ON  /*_*/cn_notice_countries;
ALTER TABLE  /*_*/cn_notice_countries
ADD  PRIMARY KEY (nc_notice_id, nc_country);