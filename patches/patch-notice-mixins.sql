-- Add a table and indexes for mixins associated with campaigns (a.k.a. notices)

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_mixins (
	`nmxn_id` int PRIMARY KEY AUTO_INCREMENT,
	`nmxn_not_id` int(11) NOT NULL,
	`nmxn_mixin_name` varchar(255) NOT NULL,
	`nmxn_enabled` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/nmxn_not_id_idx ON /*_*/cn_notice_mixins (nmxn_not_id);
CREATE INDEX /*i*/nmxn_mixin_name_idx ON /*_*/cn_notice_mixins (nmxn_mixin_name);
CREATE UNIQUE INDEX /*i*/nmxn_not_id_mixin_name ON /*_*/cn_notice_mixins (nmxn_not_id, nmxn_mixin_name);
