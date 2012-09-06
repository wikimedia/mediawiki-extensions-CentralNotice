<?php

/**
 * Provides pagination functionality for viewing banner lists in the CentralNotice admin interface.
 */
class TemplatePager extends ReverseChronologicalPager {
	var $onRemoveChange, $viewPage, $special;
	var $editable;
	var $filter;

	function __construct( $special, $filter = '' ) {
		$this->special = $special;
		$this->editable = $special->editable;
		$this->filter = $filter;
		parent::__construct();

		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffset( 20, '' );
		$this->mLimitsShown = array( 20, 50, 100 );

		$msg = Xml::encodeJsVar( $this->msg( 'centralnotice-confirm-delete' )->text() );
		$this->onRemoveChange = "if( this.checked ) { this.checked = confirm( $msg ) }";
		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
	}

	/**
	 * Set the database query to retrieve all the banners in the database
	 *
	 * @return array of query settings
	 */
	function getQueryInfo() {
		$dbr = wfGetDB( DB_SLAVE );

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

		return array(
			'tables' => 'cn_templates',
			'fields' => array( 'tmp_name', 'tmp_id' ),
			'conds'  => array( 'tmp_name' . $dbr->buildLike( $likeArray ) ),
		);
	}

	/**
	 * Sort the banner list by tmp_id (generally equals reverse chronological)
	 *
	 * @return string
	 */
	function getIndexField() {
		$dbr = wfGetDB( DB_SLAVE );
		return $dbr->tableName( 'cn_templates' ) . '.tmp_id';
	}

	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 *
	 * @param $row object: database row
	 *
	 * @return string HTML
	 */
	function formatRow( $row ) {
		global $wgLanguageCode;

		// Begin banner row
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Remove box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'removeTemplates[]', false,
					array(
						'value'    => $row->tmp_name,
						'onchange' => $this->onRemoveChange
					)
				)
			);
		}

		// Link and Preview
		$render = new SpecialBannerLoader();
		$render->siteName = 'Wikipedia';
		$render->language = $this->mRequest->getVal( 'wpUserLanguage', $wgLanguageCode );
		try {
			$preview = $render->getHtmlNotice( $row->tmp_name );
		} catch ( SpecialBannerLoaderException $e ) {
			$preview = $this->msg( 'centralnotice-nopreview' )->text();
		}
		$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
			Linker::link(
				$this->viewPage,
				htmlspecialchars( $row->tmp_name ),
				array(),
				array( 'template' => $row->tmp_name	)
			) . Xml::fieldset(
				$this->msg( 'centralnotice-preview' )->text(),
				$preview,
				array( 'class' => 'cn-bannerpreview' )
			)
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
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				$this->msg( 'centralnotice-remove' )->text()
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
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
	function getEndBody() {
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		if ( $this->editable ) {
			$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );
			$htmlOut .= Xml::tags( 'div',
				array( 'class' => 'cn-buttons' ),
				Xml::submitButton( $this->msg( 'centralnotice-modify' )->text() )
			);
		}
		return $htmlOut;
	}
}
