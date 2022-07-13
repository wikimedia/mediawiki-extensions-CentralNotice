-- Allow declaring banners as templates

ALTER TABLE /*$wgDBprefix*/cn_templates ADD `tmp_is_template` bool NOT NULL DEFAULT 0;
