-- Update to allow for any number of languages per notice.

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_notice_geo (
	ng_notice_id int unsigned NOT NULL,
	ng_country varchar(2) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/ng_notice_id_geo ON /*$wgDBprefix*/cn_notice_geo (ng_notice_id, ng_country);
ALTER TABLE /*$wgDBprefix*/cn_notices ADD not_geo BOOLEAN NOT NULL DEFAULT '0' AFTER not_locked; 
