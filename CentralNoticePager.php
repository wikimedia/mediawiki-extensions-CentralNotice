<?php

class CentralNoticePager extends TemplatePager {
	public $viewPage, $special;
	public $editable;
	public $filter;

	function __construct( $special, $filter = '' ) {
		parent::__construct( $special, $filter );
	}

	/**
	 * Pull banners from the database
	 */
	function getQueryInfo() {
		$dbr = CNDatabase::getDb();

		// First we must construct the filter before we pull banners
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards betwean search terms
		if ( empty( $likeArray ) ) {
			$likeArray = $dbr->anyString();
		} else {
			$anyStringToken = $dbr->anyString();
			$tempArray = array( $anyStringToken );
			foreach ( $likeArray as $likePart ) {
				$tempArray[ ] = $likePart;
				$tempArray[ ] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		// Get the current campaign and filter on that as well if required
		$notice = $this->mRequest->getVal( 'notice' );
		$noticeId = Campaign::getNoticeId( $notice );

		if ( $noticeId ) {
			// Return all the banners not already assigned to the current campaign
			return array(
				'tables' => array(
					'assignments' => 'cn_assignments',
					'templates' => 'cn_templates',
				),

				'fields' => array(
					'templates.tmp_name',
					'templates.tmp_id',
				),

				'conds' => array(
					'assignments.tmp_id IS NULL',
					'tmp_name' . $dbr->buildLike( $likeArray )
				),

				'join_conds' => array(
					'assignments' => array(
						'LEFT JOIN',
						"assignments.tmp_id = templates.tmp_id " .
							"AND assignments.not_id = $noticeId"
					)
				)
			);
		} else {
			// Return all the banners in the database
			return array(
				'tables' => array( 'templates' => 'cn_templates'),
				'fields' => array( 'templates.tmp_name', 'templates.tmp_id' ),
				'conds'  => array( 'templates.tmp_name' . $dbr->buildLike( $likeArray ) ),
			);
		}
	}

	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 */
	function formatRow( $row ) {
		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Add box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'addTemplates[]', '', array( 'value' => $row->tmp_name ) )
			);

			// Bucket
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->bucketDropDown( $row->tmp_name )
			);

			// Weight select
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top', 'class' => 'cn-weight' ),
				Xml::listDropDown( "weight[$row->tmp_id]",
					CentralNotice::dropDownList(
						$this->msg( 'centralnotice-weight' )->text(), range( 0, 100, 5 )
					),
					'',
					'25',
					'',
					'' )
			);
		}

		// Link and Preview
		$banner = Banner::fromName( $row->tmp_name );
		$bannerRenderer = new BannerRenderer( $this->getContext(), $banner );

		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			$bannerRenderer->linkTo() . "<br>" . $bannerRenderer->previewFieldSet()
		);

		// End banner row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * Specify table headers
	 *
	 * @return string
	 */
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( "centralnotice-add" )->text()
			);
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( 'centralnotice-bucket' )->text()
			);
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%', 'class' => 'cn-weight' ),
				$this->msg( 'centralnotice-weight' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			$this->msg( 'centralnotice-templates' )->text()
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 *
	 * @return string
	 */
	function getEndBody() {
		return Xml::closeElement( 'table' );
	}

	function bucketDropDown( $bannerName ) {
		global $wgNoticeNumberOfBuckets;

		$html = Html::openElement( 'select', array(
			'name' => "bucket-{$bannerName}",
			'class' => array( 'bucketSelector' ),
		) );
		foreach ( range( 0, $wgNoticeNumberOfBuckets - 1 ) as $value ) {
			$html .= Xml::option( chr( $value + ord( 'A' ) ), $value, false, array() );
		}
		$html .= Html::closeElement( 'select' );
		return $html;
	}
}
