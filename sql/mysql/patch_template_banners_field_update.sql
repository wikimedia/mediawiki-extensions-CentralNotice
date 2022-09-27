-- Fix tmp_is_template field type

ALTER TABLE /*$wgDBprefix*/cn_templates MODIFY `tmp_is_template` tinyint(1) NOT NULL DEFAULT 0;
