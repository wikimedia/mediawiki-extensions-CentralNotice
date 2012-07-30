-- Support for certain campaigns to superceed other campaigns by having a higher
-- assigned priority. Use case is to be able to use one all language and
-- projects notice and have it superceded by a specific one for en wikipedia.

ALTER TABLE /*$wgDBprefix*/cn_notices CHANGE `not_preferred` `not_preferred` tinyint(1) NOT NULL DEFAULT '1';
