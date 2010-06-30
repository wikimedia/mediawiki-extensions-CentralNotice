-- Update to allow for any number of languages per notice.

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/cn_notice_languages (
  `id` int unsigned NOT NULL auto_increment,
  `not_id` int unsigned NOT NULL,
  `not_language` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `not_id_not_language` (`not_id`,`not_language`)
) /*$wgDBTableOptions*/;