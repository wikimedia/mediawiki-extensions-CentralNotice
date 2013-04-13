-- Fix corrupted logs, where the "created" entry null values for all banner settings.
-- See https://mingle.corp.wikimedia.org/projects/fundraiser_2012/cards/894
--
-- There is no DatabaseUpdater condition to run this script, it's meant to be done
-- manually, and is definitely an optional fixup.  The primary use case for this
-- data is Allocation History.

-- Get all bad log entries which are followed by a "modified" entry
-- which can be used to repopulate missing data.
UPDATE cn_template_log target_log
	JOIN
	(
		SELECT
			bad_log.tmplog_id AS bad_log_id,
			data_log.*
		FROM /*_*/cn_template_log bad_log
		JOIN /*_*/cn_template_log data_log
		WHERE
			bad_log.tmplog_action = 'created'
			AND bad_log.tmplog_end_anon IS NULL
			AND
			(
				SELECT MIN( tmplog_id ) FROM /*_*/cn_template_log
				WHERE
					tmplog_template_id = bad_log.tmplog_template_id
					AND tmplog_id > bad_log.tmplog_id
					AND tmplog_action = 'modified'
			) = data_log.tmplog_id
	) AS next_data
	SET 
		target_log.tmplog_end_anon = next_data.tmplog_begin_anon,
		target_log.tmplog_end_account = next_data.tmplog_begin_account,
		target_log.tmplog_end_fundraising = next_data.tmplog_begin_fundraising,
		target_log.tmplog_end_autolink = next_data.tmplog_begin_autolink,
		target_log.tmplog_end_landingpages = next_data.tmplog_begin_landingpages,
		target_log.tmplog_end_prioritylangs = next_data.tmplog_begin_prioritylangs,
		target_log.tmplog_end_archived = next_data.tmplog_begin_archived,
		target_log.tmplog_end_category = next_data.tmplog_begin_category
	WHERE
		bad_log_id = target_log.tmplog_id
;

-- Populate the remaining bad log entries with values from the current banner settings
UPDATE cn_template_log target_log
	JOIN
	(
		SELECT
			bad_log.tmplog_id AS bad_log_id,
			settings.*
		FROM /*_*/cn_template_log bad_log
		JOIN cn_templates settings
		WHERE
			bad_log.tmplog_action = 'created'
			AND bad_log.tmplog_end_anon IS NULL
	) AS next_data
	SET 
		target_log.tmplog_end_anon = next_data.tmp_display_anon,
		target_log.tmplog_end_account = next_data.tmp_display_account,
		target_log.tmplog_end_fundraising = next_data.tmp_fundraising,
		target_log.tmplog_end_autolink = next_data.tmp_autolink,
		target_log.tmplog_end_landingpages = next_data.tmp_landing_pages,
		target_log.tmplog_end_archived = next_data.tmp_archived,
		target_log.tmplog_end_category = next_data.tmp_category
	WHERE
		bad_log_id = target_log.tmplog_id
;
