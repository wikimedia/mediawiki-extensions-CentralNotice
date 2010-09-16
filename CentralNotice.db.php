<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class CentralNoticeDB {

	/* Functions */

	function __construct() {
		// Internationalization
		wfLoadExtensionMessages( 'CentralNotice' );
	}

	/*
	 * Return campaigns in the system within given constraints
	 * By default returns enabled campaigns, if $enabled set to false, returns both enabled and disabled campaigns
	 */
	static function getNotices( $project = false, $language = false, $date = false, $enabled = true, $preferred = false, $location = false ) {
	
		$notices = array();
		
		// Database setup
		$dbr = wfGetDB( DB_SLAVE );
		
		if ( !$date ) {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		} else {
			$encTimestamp = $dbr->addQuotes( $date );
		}
		
		$tables[] = "cn_notices";
		if ( $language ) {
			$tables[] = "cn_notice_languages";
		}

		// Use whatever conditional arguments got passed in
		if ( $language ) {
			$conds[] = "nl_notice_id = cn_notices.not_id";
			$conds[] = "nl_language =" . $dbr->addQuotes( $language );
		}
		if ( $enabled ) {
			$conds[] = "not_enabled = 1";
		}
		if ( $preferred ) {
			$conds[] = "not_preferred = 1";
		}
		$conds[] = "not_project IN ( '', " . $dbr->addQuotes($project ) . " )";
		$conds[] = "not_geo = 0";
		$conds[] = "not_start <= " . $encTimestamp;
		$conds[] = "not_end >= " . $encTimestamp;

		// Pull db data
		$res = $dbr->select(
			$tables,
			array(
				'not_id'
			),
			$conds,
			__METHOD__
		);

		// Loop through result set and return ids
		foreach ( $res as $row ) {
			$notices[] = $row->not_id;
		}
		
		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables = array();
			$tables[] = "cn_notices";
			$tables[] = "cn_notice_countries";
			if ( $language ) {
				$tables[] = "cn_notice_languages";
			}
	
			// Use whatever conditional arguments got passed in
			$conds = array();
			if ( $project ) {
				$conds[] = "not_project =" . $dbr->addQuotes( $project );
			}
			if ( $language ) {
				$conds[] = "nl_notice_id = cn_notices.not_id";
				$conds[] = "nl_language =" . $dbr->addQuotes( $language );
			}
			
			if ( $enabled ) {
				$conds[] = "not_enabled = 1";
			}
			if ( $preferred ) {
				$conds[] = "not_preferred = 1";
			}
			$conds[] = "not_project IN ( '', " . $dbr->addQuotes($project ) . " )";
			$conds[] = "not_geo = 1";
			$conds[] = "nc_notice_id = cn_notices.not_id";
			$conds[] = "nc_country =" . $dbr->addQuotes( $location );
			$conds[] = "not_start <= " . $encTimestamp;
			$conds[] = "not_end >= " . $encTimestamp;
	
			// Pull db data
			$res = $dbr->select(
				$tables,
				array(
					'not_id'
				),
				$conds,
				__METHOD__
			);
			
			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$notices[] = $row->not_id;
			}
		}

		return $notices;
	}

	/*
	 * Given one or more campaign ids, return all banners bound to them
	 */
	static function selectTemplatesAssigned( $campaigns ) {
		$dbr = wfGetDB( DB_SLAVE );

		// Pull templates based on join with assignments
		$res = $dbr->select(
			array(
				'cn_notices',
				'cn_assignments',
				'cn_templates'
			),
			array(
				'tmp_name',
				'SUM(tmp_weight) AS total_weight',
				'tmp_display_anon',
				'tmp_display_account'
			),
			array(
				'cn_notices.not_id' => $campaigns,
				'cn_notices.not_id = cn_assignments.not_id',
				'cn_assignments.tmp_id = cn_templates.tmp_id'
			),
			__METHOD__,
			array(
				'GROUP BY' => 'tmp_name'
			)
		);
		$templates = array();
		foreach ( $res as $row ) {
			$template = array();
			$template['name'] = $row->tmp_name;
			$template['weight'] = intval( $row->total_weight );
			$template['display_anon'] = intval( $row->tmp_display_anon );
			$template['display_account'] =  intval( $row->tmp_display_account );
			$templates[] = $template;
		}
		return $templates;
	}
	
}
