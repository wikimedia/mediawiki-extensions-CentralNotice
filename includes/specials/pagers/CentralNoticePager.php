<?php

use MediaWiki\Html\Html;

class CentralNoticePager extends TemplatePager {

	public function __construct( $special, $filter = '' ) {
		parent::__construct( $special, $filter );
	}

	/**
	 * Pull banners from the database
	 * @return array[]
	 */
	public function getQueryInfo() {
		$dbr = CNDatabase::getDb();

		// First we must construct the filter before we pull banners
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards between search terms
		if ( !$likeArray ) {
			$likeArray = $dbr->anyString();
		} else {
			$anyStringToken = $dbr->anyString();
			$tempArray = [ $anyStringToken ];
			foreach ( $likeArray as $likePart ) {
				$tempArray[] = $likePart;
				$tempArray[] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		// Get the current campaign and filter on that as well if required
		$notice = $this->mRequest->getVal( 'notice' );
		$noticeId = Campaign::getNoticeId( $notice );

		if ( $noticeId ) {
			// Return all the banners not already assigned to the current campaign
			return [
				'tables' => [
					'assignments' => 'cn_assignments',
					'templates' => 'cn_templates',
				],

				'fields' => [
					'templates.tmp_name',
					'templates.tmp_id',
				],

				'conds' => [
					'assignments.tmp_id IS NULL',
					'tmp_name' . $dbr->buildLike( $likeArray ),
					'templates.tmp_is_template = 0'
				],

				'join_conds' => [
					'assignments' => [
						'LEFT JOIN',
						[
							"assignments.tmp_id = templates.tmp_id ",
							"assignments.not_id" => $noticeId
						]
					]
				]
			];
		} else {
			// Return all the banners in the database
			return [
				'tables' => [ 'templates' => 'cn_templates' ],
				'fields' => [ 'templates.tmp_name', 'templates.tmp_id' ],
				'conds' => [
					'templates.tmp_name' . $dbr->buildLike( $likeArray ),
					'templates.tmp_is_template = 0'
				],
			];
		}
	}

	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 * @param stdClass $row
	 * @return string HTML
	 */
	public function formatRow( $row ) {
		// Begin banner row
		$htmlOut = Html::openElement( 'tr' );

		if ( $this->editable ) {
			// Add box
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
				Xml::check( 'addTemplates[]', false, [ 'value' => $row->tmp_name ] )
			);

			// Bucket
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
				$this->bucketDropdown( $row->tmp_name )
			);

			// Weight select
			$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top', 'class' => 'cn-weight' ],
				Xml::listDropdown( "weight[$row->tmp_id]",
					CentralNotice::dropdownList(
						$this->msg( 'centralnotice-weight' )->text(), range( 0, 100, 5 )
					),
					'',
					'25'
				)
			);
		}

		// Render banner row.
		$htmlOut .= Html::rawElement( 'td', [ 'valign' => 'top' ],
			BannerRenderer::linkToBanner( $row->tmp_name )
		);

		// End banner row
		$htmlOut .= Html::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * Specify table headers
	 *
	 * @return string
	 */
	public function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Html::openElement( 'table', [ 'cellpadding' => 9 ] );
		$htmlOut .= Html::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
				$this->msg( "centralnotice-add" )->text()
			);
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
				$this->msg( 'centralnotice-bucket' )->text()
			);
			$htmlOut .= Xml::element( 'th',
				[ 'align' => 'left', 'width' => '5%', 'class' => 'cn-weight' ],
				$this->msg( 'centralnotice-weight' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left' ],
			$this->msg( 'centralnotice-templates' )->text()
		);
		$htmlOut .= Html::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 *
	 * @return string
	 */
	public function getEndBody() {
		return Html::closeElement( 'table' );
	}

	private function bucketDropdown( $bannerName ) {
		global $wgNoticeNumberOfBuckets;

		$html = '';
		foreach ( range( 0, $wgNoticeNumberOfBuckets - 1 ) as $value ) {
			$html .= Xml::option( chr( $value + ord( 'A' ) ), $value, false, [] );
		}

		return Html::rawElement( 'select', [
			'name' => "bucket-{$bannerName}",
			// class should coordinate with CentralNotice::bucketDropdown()
			'class' => 'bucketSelector',
		], $html );
	}
}
