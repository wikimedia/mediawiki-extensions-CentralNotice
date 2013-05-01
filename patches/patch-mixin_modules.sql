ALTER TABLE /*_*/cn_template_mixins
	ADD COLUMN `mixin_name` varchar(255) NOT NULL,
	ADD INDEX /*i*/tmxn_mixin_name (mixin_name);
