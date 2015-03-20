-- Add a table and indexes for the parameters of mixins associated with campaigns
-- (a.k.a. notices)

CREATE TABLE IF NOT EXISTS /*_*/cn_notice_mixin_params (
	`nmxnp_id` int PRIMARY KEY AUTO_INCREMENT,
	`nmxnp_notice_mixin_id` int(11) NOT NULL,
	`nmxnp_param_name` varchar(255) NOT NULL,
	`nmxnp_param_value` TEXT NOT NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/nmxnp_notice_mixin_id_idx ON /*_*/cn_notice_mixin_params (nmxnp_notice_mixin_id);
CREATE INDEX /*i*/nmxnp_param_name_value_idx ON /*_*/cn_notice_mixin_params (nmxnp_param_name, nmxnp_param_value(50));
CREATE UNIQUE INDEX /*i*/nmxn_notice_mixin_id_param_name ON /*_*/cn_notice_mixin_params (nmxnp_notice_mixin_id, nmxnp_param_name);
