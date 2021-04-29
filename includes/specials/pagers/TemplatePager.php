<?php

/**
 * Provides pagination functionality for viewing banner lists in the CentralNotice admin interface.
 *
 * @deprecated 2.3 -- We're moving to an HTML form model and this is no longer used directly.
 * We still need to move the Campaign manager to HTMLForm though and so this still exists for
 * that part of CN.
 */
class TemplatePager extends ReverseChronologicalPager {
	/** @var string */
	public $onRemoveChange;
	/** @var Title */
	public $viewPage;
	/** @var SpecialPage */
	public $special;
	/** @var bool */
	public $editable;
	/** @var string */
	public $filter;

	public function __construct( $special, $filter = '' ) {
		$this->special = $special;
		$this->editable = $special->editable;
		$this->filter = $filter;
		parent::__construct();

		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffsetForUser(
			$this->getUser(),
			20,
			''
		);
		$this->mLimitsShown = [ 20, 50, 100 ];

		$msg = Xml::encodeJsVar( $this->msg( 'centralnotice-confirm-delete' )->text() );
		$this->onRemoveChange = "if( this.checked ) { this.checked = confirm( $msg ) }";
		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
	}

	/**
	 * Set the database query to retrieve all the banners in the database
	 *
	 * @return array of query settings
	 */
	public function getQueryInfo() {
		$dbr = CNDatabase::getDb();

		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s+/', $this->filter );

		// ...and then insert all the wildcards betwean search terms
		if ( empty( $likeArray ) ) {
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

		return [
			'tables' => [ 'templates' => 'cn_templates' ],
			'fields' => [ 'templates.tmp_name', 'templates.tmp_id' ],
			'conds' => [ 'templates.tmp_name' . $dbr->buildLike( $likeArray ) ],
		];
	}

	/**
	 * Sort the banner list by tmp_id (generally equals reverse chronological)
	 *
	 * @return string
	 */
	public function getIndexField() {
		return 'templates.tmp_id';
	}

	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 *
	 * @param stdClass $row database row
	 *
	 * @return string HTML
	 */
	public function formatRow( $row ) {
		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Remove box
			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
				Xml::check( 'removeTemplates[]', false,
					[
						'value'    => $row->tmp_name,
						'onchange' => $this->onRemoveChange
					]
				)
			);
		}

		// Render banner row.
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
			BannerRenderer::linkToBanner( $row->tmp_name )
		);

		// End banner row
		$htmlOut .= Xml::closeElement( 'tr' );

		return $htmlOut;
	}

	/**
	 * Specify table headers
	 *
	 * @return string HTML
	 */
	public function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', [ 'cellpadding' => 9 ] );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'width' => '5%' ],
				$this->msg( 'centralnotice-remove' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left' ],
			$this->msg( 'centralnotice-templates' )->text()
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table and add Submit button
	 *
	 * @return string HTML
	 */
	public function getEndBody() {
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		if ( $this->editable ) {
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );
			$htmlOut .= Xml::tags( 'div',
				[ 'class' => 'cn-buttons' ],
				Xml::submitButton( $this->msg( 'centralnotice-modify' )->text() )
			);
		}
		return $htmlOut;
	}
}
