-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cn_notices (
  not_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  not_name VARCHAR(255) NOT NULL,
  not_start BLOB NOT NULL,
  not_end BLOB NOT NULL,
  not_enabled SMALLINT DEFAULT 0 NOT NULL,
  not_preferred SMALLINT DEFAULT 0 NOT NULL,
  not_throttle INTEGER DEFAULT 100 NOT NULL,
  not_locked SMALLINT DEFAULT 0 NOT NULL,
  not_geo SMALLINT DEFAULT 0 NOT NULL,
  not_buckets SMALLINT DEFAULT 1 NOT NULL,
  not_weight INTEGER DEFAULT 100 NOT NULL,
  not_mobile_carrier SMALLINT DEFAULT 0 NOT NULL,
  not_archived SMALLINT DEFAULT 0 NOT NULL,
  not_type VARCHAR(255) DEFAULT NULL
);


CREATE TABLE /*_*/cn_assignments (
  asn_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  not_id INTEGER NOT NULL, tmp_id INTEGER NOT NULL,
  tmp_weight INTEGER NOT NULL, asn_bucket SMALLINT DEFAULT 0
);

CREATE INDEX asn_not ON /*_*/cn_assignments (not_id);

CREATE INDEX asn_tmp ON /*_*/cn_assignments (tmp_id);

CREATE INDEX asn_bucket ON /*_*/cn_assignments (asn_bucket);


CREATE TABLE /*_*/cn_templates (
  tmp_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  tmp_name VARCHAR(255) DEFAULT NULL,
  tmp_display_anon SMALLINT DEFAULT 1 NOT NULL,
  tmp_display_account SMALLINT DEFAULT 1 NOT NULL,
  tmp_fundraising SMALLINT DEFAULT 0 NOT NULL,
  tmp_autolink SMALLINT DEFAULT 0 NOT NULL,
  tmp_landing_pages VARCHAR(255) DEFAULT NULL,
  tmp_archived SMALLINT DEFAULT 0 NOT NULL,
  tmp_category VARCHAR(255) DEFAULT NULL,
  tmp_preview_sandbox SMALLINT DEFAULT 0 NOT NULL,
  tmp_is_template SMALLINT DEFAULT 0 NOT NULL
);

CREATE INDEX tmp_name ON /*_*/cn_templates (tmp_name);

CREATE INDEX tmp_category ON /*_*/cn_templates (tmp_category);


CREATE TABLE /*_*/cn_notice_languages (
  nl_notice_id INTEGER UNSIGNED NOT NULL,
  nl_language VARCHAR(32) NOT NULL,
  PRIMARY KEY(nl_notice_id, nl_language)
);


CREATE TABLE /*_*/cn_notice_projects (
  np_notice_id INTEGER UNSIGNED NOT NULL,
  np_project VARCHAR(32) NOT NULL,
  PRIMARY KEY(np_notice_id, np_project)
);


CREATE TABLE /*_*/cn_notice_countries (
  nc_notice_id INTEGER UNSIGNED NOT NULL,
  nc_country VARCHAR(2) NOT NULL,
  PRIMARY KEY(nc_notice_id, nc_country)
);


CREATE TABLE /*_*/cn_notice_regions (
  nr_notice_id INTEGER UNSIGNED NOT NULL,
  nr_region VARCHAR(6) NOT NULL,
  PRIMARY KEY(nr_notice_id, nr_region)
);


CREATE TABLE /*_*/cn_template_mixins (
  tmxn_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  tmp_id INTEGER NOT NULL,
  page_id INTEGER NOT NULL,
  mixin_name VARCHAR(255) NOT NULL
);

CREATE INDEX tmxn_tmp_id ON /*_*/cn_template_mixins (tmp_id);

CREATE INDEX tmxn_page_id ON /*_*/cn_template_mixins (page_id);

CREATE INDEX tmxn_mixin_name ON /*_*/cn_template_mixins (mixin_name);


CREATE TABLE /*_*/cn_notice_mixins (
  nmxn_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  nmxn_not_id INTEGER NOT NULL,
  nmxn_mixin_name VARCHAR(255) NOT NULL,
  nmxn_enabled SMALLINT DEFAULT 0 NOT NULL
);

CREATE INDEX nmxn_not_id_idx ON /*_*/cn_notice_mixins (nmxn_not_id);

CREATE INDEX nmxn_mixin_name_idx ON /*_*/cn_notice_mixins (nmxn_mixin_name);

CREATE UNIQUE INDEX nmxn_not_id_mixin_name ON /*_*/cn_notice_mixins (nmxn_not_id, nmxn_mixin_name);


CREATE TABLE /*_*/cn_notice_mixin_params (
  nmxnp_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  nmxnp_notice_mixin_id INTEGER NOT NULL,
  nmxnp_param_name VARCHAR(255) NOT NULL,
  nmxnp_param_value CLOB NOT NULL
);

CREATE INDEX nmxnp_notice_mixin_id_idx ON /*_*/cn_notice_mixin_params (nmxnp_notice_mixin_id);

CREATE INDEX nmxnp_param_name_value_idx ON /*_*/cn_notice_mixin_params (
  nmxnp_param_name, nmxnp_param_value
);

CREATE UNIQUE INDEX nmxn_notice_mixin_id_param_name ON /*_*/cn_notice_mixin_params (
  nmxnp_notice_mixin_id, nmxnp_param_name
);


CREATE TABLE /*_*/cn_known_devices (
  dev_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  dev_name VARCHAR(255) NOT NULL,
  dev_display_label BLOB NOT NULL
);

CREATE INDEX dev_name ON /*_*/cn_known_devices (dev_name);


CREATE TABLE /*_*/cn_template_devices (
  tdev_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  tmp_id INTEGER NOT NULL, dev_id INTEGER NOT NULL
);

CREATE INDEX tdev_template_id ON /*_*/cn_template_devices (tmp_id);


CREATE TABLE /*_*/cn_known_mobile_carriers (
  mc_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  mc_name VARCHAR(255) NOT NULL,
  mc_display_label BLOB NOT NULL
);

CREATE INDEX mc_name ON /*_*/cn_known_mobile_carriers (mc_name);


CREATE TABLE /*_*/cn_notice_mobile_carriers (
  nmc_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  not_id INTEGER NOT NULL, mc_id INTEGER NOT NULL
);

CREATE INDEX nmc_not_id ON /*_*/cn_notice_mobile_carriers (not_id);

CREATE INDEX nmc_carrier_id ON /*_*/cn_notice_mobile_carriers (mc_id);


CREATE TABLE /*_*/cn_notice_log (
  notlog_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  notlog_timestamp BLOB NOT NULL,
  notlog_user_id INTEGER UNSIGNED NOT NULL,
  notlog_action TEXT DEFAULT 'modified' NOT NULL,
  notlog_not_id INTEGER UNSIGNED NOT NULL,
  notlog_not_name VARCHAR(255) DEFAULT NULL,
  notlog_begin_projects VARCHAR(255) DEFAULT NULL,
  notlog_end_projects VARCHAR(255) DEFAULT NULL,
  notlog_begin_languages CLOB DEFAULT NULL,
  notlog_end_languages CLOB DEFAULT NULL,
  notlog_begin_countries CLOB DEFAULT NULL,
  notlog_end_countries CLOB DEFAULT NULL,
  notlog_begin_regions CLOB DEFAULT NULL,
  notlog_end_regions CLOB DEFAULT NULL,
  notlog_begin_start BLOB DEFAULT NULL,
  notlog_end_start BLOB DEFAULT NULL,
  notlog_begin_end BLOB DEFAULT NULL,
  notlog_end_end BLOB DEFAULT NULL,
  notlog_begin_enabled SMALLINT DEFAULT NULL,
  notlog_end_enabled SMALLINT DEFAULT NULL,
  notlog_begin_preferred SMALLINT DEFAULT NULL,
  notlog_end_preferred SMALLINT DEFAULT NULL,
  notlog_begin_throttle INTEGER DEFAULT NULL,
  notlog_end_throttle INTEGER DEFAULT NULL,
  notlog_begin_locked SMALLINT DEFAULT NULL,
  notlog_end_locked SMALLINT DEFAULT NULL,
  notlog_begin_geo SMALLINT DEFAULT NULL,
  notlog_end_geo SMALLINT DEFAULT NULL,
  notlog_begin_banners CLOB DEFAULT NULL,
  notlog_end_banners CLOB DEFAULT NULL,
  notlog_begin_buckets SMALLINT DEFAULT NULL,
  notlog_end_buckets SMALLINT DEFAULT NULL,
  notlog_begin_mobile_carrier INTEGER DEFAULT NULL,
  notlog_end_mobile_carrier INTEGER DEFAULT NULL,
  notlog_begin_weight INTEGER DEFAULT NULL,
  notlog_end_weight INTEGER DEFAULT NULL,
  notlog_begin_archived SMALLINT DEFAULT NULL,
  notlog_end_archived SMALLINT DEFAULT NULL,
  notlog_begin_mixins BLOB DEFAULT NULL,
  notlog_end_mixins BLOB DEFAULT NULL,
  notlog_comment VARCHAR(255) DEFAULT NULL,
  notlog_begin_type VARCHAR(255) DEFAULT NULL,
  notlog_end_type VARCHAR(255) DEFAULT NULL
);

CREATE INDEX notlog_timestamp ON /*_*/cn_notice_log (notlog_timestamp);

CREATE INDEX notlog_user_id ON /*_*/cn_notice_log (
  notlog_user_id, notlog_timestamp
);

CREATE INDEX notlog_not_id ON /*_*/cn_notice_log (notlog_not_id, notlog_timestamp);


CREATE TABLE /*_*/cn_template_log (
  tmplog_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  tmplog_timestamp BLOB NOT NULL,
  tmplog_user_id INTEGER UNSIGNED NOT NULL,
  tmplog_action TEXT DEFAULT 'modified' NOT NULL,
  tmplog_template_id INTEGER UNSIGNED NOT NULL,
  tmplog_template_name VARCHAR(255) DEFAULT NULL,
  tmplog_begin_anon SMALLINT DEFAULT NULL,
  tmplog_end_anon SMALLINT DEFAULT NULL,
  tmplog_begin_account SMALLINT DEFAULT NULL,
  tmplog_end_account SMALLINT DEFAULT NULL,
  tmplog_begin_fundraising SMALLINT DEFAULT NULL,
  tmplog_end_fundraising SMALLINT DEFAULT NULL,
  tmplog_begin_autolink SMALLINT DEFAULT NULL,
  tmplog_end_autolink SMALLINT DEFAULT NULL,
  tmplog_begin_landingpages VARCHAR(255) DEFAULT NULL,
  tmplog_end_landingpages VARCHAR(255) DEFAULT NULL,
  tmplog_content_change SMALLINT DEFAULT 0,
  tmplog_begin_prioritylangs CLOB DEFAULT NULL,
  tmplog_end_prioritylangs CLOB DEFAULT NULL,
  tmplog_begin_archived SMALLINT DEFAULT NULL,
  tmplog_end_archived SMALLINT DEFAULT NULL,
  tmplog_begin_category VARCHAR(255) DEFAULT NULL,
  tmplog_end_category VARCHAR(255) DEFAULT NULL,
  tmplog_begin_preview_sandbox SMALLINT DEFAULT NULL,
  tmplog_end_preview_sandbox SMALLINT DEFAULT NULL,
  tmplog_begin_controller_mixin BLOB DEFAULT NULL,
  tmplog_end_controller_mixin BLOB DEFAULT NULL,
  tmplog_begin_devices BLOB DEFAULT NULL,
  tmplog_end_devices BLOB DEFAULT NULL,
  tmplog_comment VARCHAR(255) DEFAULT NULL
);

CREATE INDEX tmplog_timestamp ON /*_*/cn_template_log (tmplog_timestamp);

CREATE INDEX tmplog_user_id ON /*_*/cn_template_log (
  tmplog_user_id, tmplog_timestamp
);

CREATE INDEX tmplog_template_id ON /*_*/cn_template_log (
  tmplog_template_id, tmplog_timestamp
);
