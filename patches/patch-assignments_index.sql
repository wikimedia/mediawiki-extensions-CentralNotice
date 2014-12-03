-- Add indexes to the cn_assignments table.

ALTER TABLE /*_*/cn_assignments
	ADD INDEX /*i*/asn_not (not_id),
	ADD INDEX /*i*/asn_tmp (tmp_id),
	ADD INDEX /*i*/asn_bucket (asn_bucket);
