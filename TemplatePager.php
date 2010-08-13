<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class TemplatePager extends ReverseChronologicalPager {
	var $onRemoveChange, $viewPage, $special;
	var $editable;

	function __construct( $special ) {
		$this->special = $special;
		$this->editable = $special->editable;
		parent::__construct();
		
		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffset( 20, '' );
		$this->mLimitsShown = array( 20, 50, 100 );
		
		$msg = Xml::encodeJsVar( wfMsg( 'centralnotice-confirm-delete' ) );
		$this->onRemoveChange = "if( this.checked ) { this.checked = confirm( $msg ) }";
		$this->viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
	}

	/**
	 * Pull all banners from the database
	 */
	function getQueryInfo() {
		return array(
			'tables' => 'cn_templates',
			'fields' => array( 'tmp_name', 'tmp_id' ),
		);
	}
	
	/**
	 * Sort the banner list by tmp_id
	 */
	function getIndexField() {
		return 'tmp_id';
	}

	/**
	 * Generate the content of each table row (1 row = 1 banner)
	 */
	function formatRow( $row ) {
	
		$templatesAssigned = array();
		// If we are calling the pager from the campaign editing interface...
		if ( $this->special->mName == 'CentralNotice' ) {
			// Find banners already assigned to the campaign
			$notice = $this->mRequest->getVal( 'notice' );
			$templatesAssigned = $this->selectTemplatesAssigned( $notice );
		}

		// If banner is not already assigned...
		if ( !in_array ( $row->tmp_name, $templatesAssigned ) ) {
	
			// Begin banner row
			$htmlOut = Xml::openElement( 'tr' );
			
			if ( $this->editable ) {
				// If we are calling the pager from the manage banners interface...
				if ( $this->special->mName == 'NoticeTemplate' ) {
					// Remove box
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						Xml::check( 'removeTemplates[]', false,
							array(
								'value' => $row->tmp_name,
								'onchange' => $this->onRemoveChange
							)
						)
					);
				// Else, if we are calling the pager from the campaign editing interface...
				} elseif ( $this->special->mName == 'CentralNotice' ) {
					// Add box
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						Xml::check( 'addTemplates[]', '', array ( 'value' => $row->tmp_name ) )
					);
					// Weight select
					$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
						Xml::listDropDown( "weight[$row->tmp_name]",
							CentralNotice::dropDownList( wfMsg( 'centralnotice-weight' ), range ( 0, 100, 5 ) ) ,
							'',
							'25',
							'',
							'' )
					);
				}
			}
			
			// Link and Preview
			$viewPage = SpecialPage::getTitleFor( 'NoticeTemplate', 'view' );
			$render = new SpecialNoticeText();
			$render->project = 'wikipedia';
			$render->language = $this->mRequest->getVal( 'wpUserLanguage' );
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$this->getSkin()->makeLinkObj( $this->viewPage,
					htmlspecialchars( $row->tmp_name ),
					'template=' . urlencode( $row->tmp_name ) ) .
				Xml::fieldset( wfMsg( 'centralnotice-preview' ),
					$render->getHtmlNotice( $row->tmp_name ),
					array( 'class' => 'cn-bannerpreview')
				)
			);
			
			// End banner row
			$htmlOut .= Xml::closeElement( 'tr' );
		}
		
		return $htmlOut;
	}

	/**
	 * Specify table headers
	 */
	function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
		$htmlOut .= Xml::openElement( 'tr' );
		if ( $this->editable ) {
			if ( $this->special->mName == 'CentralNotice' ) {
				$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
					 wfMsg ( "centralnotice-add" )
				);
				$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
					 wfMsg ( "centralnotice-weight" )
				);
			} elseif ( $this->special->mName == 'NoticeTemplate' ) {
				$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
					wfMsg ( 'centralnotice-remove' )
				);
			}
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			wfMsg ( 'centralnotice-templates' )
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table and add Submit button if we're on the Manage banners page
	 */
	function getEndBody() {
		global $wgUser;
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		if ( $this->special->mName == 'NoticeTemplate' ) {
			if ( $this->editable ) {
				$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
				$htmlOut .= Xml::tags( 'div', 
					array( 'class' => 'cn-buttons' ), 
					Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) 
				);
			}
		}
		return $htmlOut;
	}
	
	/**
	 * Build a list of all the banners assigned to a campaign
	 * @return An array of banner names
	 */
	function selectTemplatesAssigned ( $notice ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array(
				'cn_notices',
				'cn_assignments',
				'cn_templates'
			),
			array(
				'cn_templates.tmp_name',
			),
			array(
				'cn_notices.not_name' => $notice,
				'cn_notices.not_id = cn_assignments.not_id',
				'cn_assignments.tmp_id = cn_templates.tmp_id'
			),
			__METHOD__,
			array( 'ORDER BY' => 'cn_notices.not_id' )
		);
		$templateNames = array();
		foreach ( $res as $row ) {
			array_push( $templateNames, $row->tmp_name ) ;
		}
		return $templateNames;
	}
}
