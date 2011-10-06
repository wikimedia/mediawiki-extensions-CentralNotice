-- Update to add a separate flag for LandingCheck

-- Store a flag indicating whether or not this banner uses LandingCheck
ALTER TABLE /*$wgDBprefix*/cn_templates ADD `tmp_landingcheck` bool NOT NULL DEFAULT 0 AFTER `tmp_fundraising`;

-- Store before and after flag values for logging
ALTER TABLE /*$wgDBprefix*/cn_template_log ADD `tmplog_begin_landingcheck` bool NULL DEFAULT NULL AFTER `tmplog_end_fundraising`;
ALTER TABLE /*$wgDBprefix*/cn_template_log ADD `tmplog_end_landingcheck` bool NULL DEFAULT NULL AFTER `tmplog_begin_landingcheck`;