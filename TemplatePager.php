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

	function getQueryInfo() {
		return array(
			'tables' => 'cn_templates',
			'fields' => array( 'tmp_name', 'tmp_id' ),
		);
	}

	function getIndexField() {
		return 'tmp_id';
	}

	function formatRow( $row ) {
		$htmlOut = Xml::openElement( 'tr' );

		if ( $this->editable ) {
			// Remove box
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Xml::check( 'removeTemplates[]', false,
					array(
						'value' => $row->tmp_name,
						'onchange' => $this->onRemoveChange
					)
				)
			);
		}

		// Link and Preview
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

		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	function getStartBody() {
		$htmlOut = '';
				
		$htmlOut .= Xml::openElement( 'table',
			array(
				'cellpadding' => 9,
				'width' => '100%'
			)
		);
		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'th', array( 'align' => 'left', 'width' => '5%' ),
				wfMsg ( 'centralnotice-remove' )
			);
		}
		$htmlOut .= Xml::element( 'th', array( 'align' => 'left' ),
			wfMsg ( 'centralnotice-template-name' )
		);
		return $htmlOut;
	}

	function getEndBody() {
		global $wgUser;
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		if ( $this->editable ) {
			$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
			$htmlOut .= Xml::tags( 'div', 
				array( 'class' => 'cn-buttons' ), 
				Xml::submitButton( wfMsg( 'centralnotice-modify' ) ) 
			);
		}
		return $htmlOut;
	}
}
