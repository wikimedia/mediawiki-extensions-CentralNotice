<?php

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
	protected $ttag;

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
	protected function cleanDuplicates() {
		$this->output( "Cleaning duplicates\n" );

		$db = CNDatabase::getDb( DB_PRIMARY );

		$res = $db->select(
			'revtag',
			[
				'rt_page',
				'maxrev' => 'max(rt_revision)',
				'count' => 'count(*)'
			],
			[ 'rt_type' => $this->ttag ],
			__METHOD__,
			[ 'GROUP BY' => 'rt_page' ]
		);

		foreach ( $res as $row ) {
			if ( (int)$row->count === 1 ) {
				continue;
			}

			$maxRev = (int)$row->maxrev;
			$db->delete(
				'revtag',
				[
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					"rt_revision != $maxRev"
				],
				__METHOD__
			);
			$numRows = $db->affectedRows();
			$this->output(
				" -- Deleted {$numRows} rows for banner with page id {$row->rt_page}\n"
			);
		}
	}

	/**
	 * Attach a banner ID with a orphan metadata line
	 */
	protected function populateIDs() {
		$this->output( "Associating metadata with banner ids\n" );

		$db = CNDatabase::getDb( DB_PRIMARY );

		$res = $db->select(
			[ 'revtag' => 'revtag', 'page' => 'page', 'cn_templates' => 'cn_templates' ],
			[ 'rt_page', 'rt_revision', 'page_title', 'tmp_id' ],
			[
				'rt_type' => $this->ttag,
				'rt_page=page_id',
				'rt_value is null',
				# Length of "centralnotice-template-"
				'tmp_name=substr(page_title, 24)'
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$this->output( " -- Associating banner id {$row->tmp_id} " .
				"with revtag with page id {$row->rt_page}\n" );
			$db->update(
				'revtag',
				[ 'rt_value' => $row->tmp_id ],
				[
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					'rt_value is null'
				],
				__METHOD__
			);
		}
	}

	/**
	 * Delete rows that have no banner ID associated with them
	 */
	protected function deleteOrphans() {
		$db = CNDatabase::getDb( DB_PRIMARY );
		$this->output( "Preparing to delete orphaned rows\n" );

		$res = $db->select(
			'revtag',
			[ 'rt_page', 'rt_revision' ],
			[ 'rt_type' => $this->ttag, 'rt_value is null' ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$this->output( " -- Deleting orphan row {$row->rt_page}:{$row->rt_revision}\n" );
			$db->delete(
				'revtag',
				[
					'rt_type' => $this->ttag,
					'rt_page' => $row->rt_page,
					'rt_revision' => $row->rt_revision,
					'rt_value is null' // Just in case something updated it
				],
				__METHOD__
			);
		}
	}
}

$maintClass = CleanCNTranslateMetadata::class;
require_once RUN_MAINTENANCE_IF_MAIN;
