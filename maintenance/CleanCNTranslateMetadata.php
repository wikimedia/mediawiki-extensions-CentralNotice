<?php

use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Cleans up the Revision Tag table which is where CentralNotice stores
 * metadata required for the Translate extension.
 *
 * So far this class:
 * * Removes duplicate revision entries (there should be only one per banner)
 * * Associates entries with a banner by name
 * * Removes entries that have no banner object
 */
class CleanCNTranslateMetadata extends Maintenance {
	/** @var string|null */
	private $ttag;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CentralNotice' );
	}

	public function execute() {
		$this->ttag = Banner::TRANSLATE_BANNER_TAG;

		$this->cleanDuplicates();
		$this->populateIDs();
		$this->deleteOrphans();

		ChoiceDataProvider::invalidateCache();
	}

	/**
	 * Remove duplicated revtags
	 */
	private function cleanDuplicates() {
		$this->output( "Cleaning duplicates\n" );

		$db = CNDatabase::getPrimaryDb();

		$res = $db->newSelectQueryBuilder()
			->select( [
				'rt_page',
				'maxrev' => 'MAX(rt_revision)',
				'count' => 'COUNT(*)'
			] )
			->from( 'revtag' )
			->where( [ 'rt_type' => $this->ttag ] )
			->groupBy( 'rt_page' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			if ( (int)$row->count === 1 ) {
				continue;
			}

			$maxRev = (int)$row->maxrev;
			$db->newDeleteQueryBuilder()
				->deleteFrom( 'revtag' )
				->where( [
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					$db->expr( 'rt_revision', '!=', $maxRev ),
				] )
				->caller( __METHOD__ )
				->execute();
			$numRows = $db->affectedRows();
			$this->output(
				" -- Deleted {$numRows} rows for banner with page id {$row->rt_page}\n"
			);
		}
	}

	/**
	 * Attach a banner ID with a orphan metadata line
	 */
	private function populateIDs() {
		$this->output( "Associating metadata with banner ids\n" );

		$db = CNDatabase::getPrimaryDb();

		$res = $db->newSelectQueryBuilder()
			->select( [ 'rt_page', 'rt_revision', 'page_title', 'tmp_id' ] )
			->from( 'revtag' )
			->join( 'page', null, 'rt_page=page_id' )
			->join( 'cn_templates', null, [
				# Length of "centralnotice-template-"
				'tmp_name=substr(page_title, 24)'
			] )
			->where( [
				'rt_type' => $this->ttag,
				'rt_value' => null,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$this->output( " -- Associating banner id {$row->tmp_id} " .
				"with revtag with page id {$row->rt_page}\n" );
			$db->newUpdateQueryBuilder()
				->update( 'revtag' )
				->set( [ 'rt_value' => $row->tmp_id ] )
				->where( [
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					'rt_value' => null,
				] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Delete rows that have no banner ID associated with them
	 */
	private function deleteOrphans() {
		$db = CNDatabase::getPrimaryDb();
		$this->output( "Preparing to delete orphaned rows\n" );

		$res = $db->newSelectQueryBuilder()
			->select( [ 'rt_page', 'rt_revision' ] )
			->from( 'revtag' )
			->where( [ 'rt_type' => $this->ttag, 'rt_value' => null ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$this->output( " -- Deleting orphan row {$row->rt_page}:{$row->rt_revision}\n" );
			$db->newDeleteQueryBuilder()
				->deleteFrom( 'revtag' )
				->where( [
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					'rt_revision' => $row->rt_revision,
					// Just in case something updated it
					'rt_value' => null,
				] )
				->caller( __METHOD__ )
				->execute();
		}
	}
}

$maintClass = CleanCNTranslateMetadata::class;
require_once RUN_MAINTENANCE_IF_MAIN;
