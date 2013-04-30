-- Fix corrupted logs, where a "modified" entry is missing values for projects,
-- languages, countries, and banners
--
-- There is no DatabaseUpdater condition to run this script, it's meant to be done
-- manually, and is definitely an optional fixup.  The primary use case for this
-- data is Allocation History.

-- Get all bad log entries which are preceded by an entry
-- which can be used to repopulate missing data.
UPDATE cn_notice_log target_log
	JOIN
	(
		SELECT
			bad_log.notlog_id AS bad_log_id,
			data_log.*
		FROM /*_*/cn_notice_log bad_log
		JOIN /*_*/cn_notice_log data_log
		WHERE
			bad_log.notlog_action = 'modified'
			AND bad_log.notlog_begin_projects IS NULL
			AND
			(
				SELECT MAX( notlog_id ) FROM /*_*/cn_notice_log
				WHERE
					notlog_not_id = bad_log.notlog_not_id
					AND notlog_id < bad_log.notlog_id
					AND notlog_begin_projects IS NOT NULL
			) = data_log.notlog_id
	) AS prev_data
	SET
		target_log.notlog_begin_projects = prev_data.notlog_end_projects,
		target_log.notlog_end_projects = prev_data.notlog_end_projects,
		target_log.notlog_begin_languages = prev_data.notlog_end_languages,
		target_log.notlog_end_languages = prev_data.notlog_end_languages,
		target_log.notlog_begin_countries = prev_data.notlog_end_countries,
		target_log.notlog_end_countries = prev_data.notlog_end_countries,
		target_log.notlog_begin_banners = prev_data.notlog_end_banners,
		target_log.notlog_end_banners = prev_data.notlog_end_banners
	WHERE
		bad_log_id = target_log.notlog_id
;
