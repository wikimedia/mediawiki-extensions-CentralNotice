CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_notices (
  `not_id` int NOT NULL auto_increment,
  `not_name` varchar(255) NOT NULL,
  `not_start` char(14) NOT NULL,
  `not_end` char(14) NOT NULL,
  `not_enabled` bool NOT NULL default '0',
  `not_preferred` bool NOT NULL default '0',
  `not_locked` bool NOT NULL default '0',
  `not_language` varchar(32) NOT NULL,
  `not_project` varchar(255) NOT NULL,
  PRIMARY KEY  (`not_id`)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_assignments (
  `asn_id` int NOT NULL auto_increment,
  `not_id` int NOT NULL,
  `tmp_id` int NOT NULL,
  `tmp_weight` int NOT NULL,
  PRIMARY KEY  (`asn_id`)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_templates (
  `tmp_id` int NOT NULL auto_increment,
  `tmp_name` varchar(255) default NULL,
  PRIMARY KEY  (`tmp_id`)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_notice_languages (
  `id` int unsigned NOT NULL auto_increment,
  `not_id` int unsigned NOT NULL,
  `not_language` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `not_id_not_language` (`not_id`,`not_language`)
) /*$wgDBTableOptions*/;