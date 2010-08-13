<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class SpecialNoticeTemplate extends UnlistedSpecialPage {
	var $editable;
	
	function __construct() {
		parent::__construct( 'NoticeTemplate' );

		// Internationalization
		wfLoadExtensionMessages( 'CentralNotice' );
	}
	
	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgUser, $wgRequest, $wgScriptPath;

		// Begin output
		$this->setHeaders();
		
		// Add style file to the output headers
		$wgOut->addExtensionStyle( "$wgScriptPath/extensions/CentralNotice/centralnotice.css" );
		
		// Add script file to the output headers
		$wgOut->addScriptFile( "$wgScriptPath/extensions/CentralNotice/centralnotice.js" );

		// Check permissions
		$this->editable = $wgUser->isAllowed( 'centralnotice-admin' );

		// Show summary
		$wgOut->addWikiMsg( 'centralnotice-summary' );

		// Show header
		CentralNotice::printHeader();

		// Begin Banners tab content
		$wgOut->addHTML( Xml::openElement( 'div', array( 'id' => 'preferences' ) ) );
		
		if ( $this->editable && $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {
			// Handle forms
			if ( $wgRequest->wasPosted() ) {

				// Handle removing banners
				$toRemove = $wgRequest->getArray( 'removeTemplates' );
				if ( isset( $toRemove ) ) {
					// Remove banners in list
					foreach ( $toRemove as $template ) {
						$this->removeTemplate( $template );
					}
				}

				// Handle translation message update
				$update = $wgRequest->getArray( 'updateText' );
				if ( isset ( $update ) ) {
					foreach ( $update as $lang => $messages ) {
						foreach ( $messages as $text => $translation ) {
							// If we actually have text
							if ( $translation ) {
								$this->updateMessage( $text, $translation, $lang );
							}
						}
					}
				}
			}

			// Handle adding banner
			// FIXME: getText()? weak comparison
			if ( $wgRequest->getVal( 'wpMethod' ) == 'addTemplate' ) {
			
				// Handle "Display to anonymous users" checkbox
				$displayAnon = 0;
				if ( $wgRequest->getVal( 'displayAnon' ) ) {
					$displayAnon = $wgRequest->getVal( 'displayAnon' );
				}
				
				// Handle "Display to logged in users" checkbox
				$displayAccount = 0;
				if ( $wgRequest->getVal( 'displayAccount' ) ) {
					$displayAccount = $wgRequest->getVal( 'displayAccount' );
				}
				
				$this->addTemplate(
					$wgRequest->getVal( 'templateName' ),
					$wgRequest->getVal( 'templateBody' ),
					$displayAnon,
					$displayAccount
				);
				$sub = 'view';
			}
			
			// Handle editing banner
			if ( $wgRequest->getVal( 'wpMethod' ) == 'editTemplate' ) {
			
				// Handle "Display to anonymous users" checkbox
				$displayAnon = 0;
				if ( $wgRequest->getVal( 'displayAnon' ) ) {
					$displayAnon = $wgRequest->getVal( 'displayAnon' );
				}
				
				// Handle "Display to logged in users" checkbox
				$displayAccount = 0;
				if ( $wgRequest->getVal( 'displayAccount' ) ) {
					$displayAccount = $wgRequest->getVal( 'displayAccount' );
				}
				
				$this->editTemplate(
					$wgRequest->getVal( 'template' ),
					$wgRequest->getVal( 'templateBody' ),
					$displayAnon,
					$displayAccount
				);
				$sub = 'view';
			}
		}

		// Handle viewing of a banner in all languages
		if ( $sub == 'view' && $wgRequest->getVal( 'wpUserLanguage' ) == 'all' ) {
			$template =  $wgRequest->getVal( 'template' );
			$this->showViewAvailable( $template );
			$wgOut->addHTML( Xml::closeElement( 'div' ) );
			return;
		}

		// Handle viewing a specific banner
		if ( $sub == 'view' && $wgRequest->getText( 'template' ) != '' ) {
			$this->showView();
			$wgOut->addHTML( Xml::closeElement( 'div' ) );
			return;
		}

		if ( $this->editable ) {
			// Handle "Add a banner" link
			if ( $sub == 'add' ) {
				$this->showAdd();
				$wgOut->addHTML( Xml::closeElement( 'div' ) );
				return;
			}
			
			// Handle cloning a specific banner
			if ( $sub == 'clone' && $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {
				$oldTemplate = $wgRequest->getVal( 'oldTemplate' );
				$newTemplate =  $wgRequest->getVal( 'newTemplate' );
				// We use the returned name in case any special characters had to be removed
				$template = $this->cloneTemplate( $oldTemplate, $newTemplate );
				$wgOut->redirect( $this->getTitle( 'view' )->getLocalUrl( "template=$template" ) );
				return;
			}
		}

		// Show list of banners by default
		$this->showList();
		
		// End Banners tab content
		$wgOut->addHTML( Xml::closeElement( 'div' ) );
	}
	
	/**
	 * Show a list of available banners. Newer banners are shown first.
	 */
	function showList() {
		global $wgOut, $wgUser;

		$sk = $wgUser->getSkin();
		$pager = new TemplatePager( $this );
		
		// Begin building HTML
		$htmlOut = '';
		
		// Begin Manage Banners fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		if ( !$pager->getNumRows() ) {
			$htmlOut .= Xml::element( 'p', null, wfMsg( 'centralnotice-no-templates' ) );
		} else {
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form',
					array(
						'method' => 'post',
						'action' => ''
					 )
				);
			}
			$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-manage-templates' ) );
			
			// Show paginated list of banners
			$htmlOut .= Xml::tags( 'div', array( 'class' => 'cn-pager' ), $pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div', array( 'class' => 'cn-pager' ), $pager->getNavigationBar() );
		
			if ( $this->editable ) {
				$htmlOut .= Xml::closeElement( 'form' );
			}
		}

		if ( $this->editable ) {
			$htmlOut .= Xml::element( 'p' );
			$newPage = $this->getTitle( 'add' );
			$htmlOut .= $sk->makeLinkObj( $newPage, wfMsgHtml( 'centralnotice-add-template' ) );
		}
		
		// End Manage Banners fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}
	
	/**
	 * Show "Add a banner" interface
	 */
	function showAdd() {
		global $wgOut, $wgUser, $wgScriptPath, $wgLang;
		$scriptPath = "$wgScriptPath/extensions/CentralNotice";

		// Build HTML
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-add-template' ) );
		$htmlOut .= Xml::hidden( 'wpMethod', 'addTemplate' );
		$htmlOut .= Xml::tags( 'p', null,
			Xml::inputLabel( wfMsg( 'centralnotice-banner-name' ) . ":", 'templateName', 'templateName', 25 )
		);
		
		$htmlOut .= Xml::openElement( 'p', null );
		$htmlOut .= wfMsg( 'centralnotice-banner-display' );
		$htmlOut .= Xml::check( 'displayAnon', true, array( 'id' => 'displayAnon' ) );
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-banner-anonymous' ), 'displayAnon' );
		$htmlOut .= Xml::check( 'displayAccount', true, array( 'id' => 'displayAccount' ) );
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-banner-logged-in' ), 'displayAccount' );
		$htmlOut .= Xml::closeElement( 'p' );
		
		$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-banner' ) );
		$htmlOut .= wfMsg( 'centralnotice-edit-template-summary' );
		$buttons = array();
		$buttons[] = '<a href="#" onclick="insertButton(\'hide\');return false;">' . wfMsg( 'centralnotice-hide-button' ) . '</a>';
		$buttons[] = '<a href="#" onclick="insertButton(\'translate\');return false;">' . wfMsg( 'centralnotice-translate-button' ) . '</a>';
		$htmlOut .= Xml::tags( 'div',
			array( 'style' => 'margin-bottom: 0.2em;' ),
			'<img src="'.$scriptPath.'/down-arrow.png" style="vertical-align:baseline;"/>' . wfMsg( 'centralnotice-insert', $wgLang->commaList( $buttons ) )
		);
		$htmlOut .= Xml::textarea( 'templateBody', '', 60, 20 );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
		
		// Submit button
		$htmlOut .= Xml::tags( 'div', 
			array( 'class' => 'cn-buttons' ), 
			Xml::submitButton( wfMsg( 'centralnotice-save-banner' ) ) 
		);
		
		$htmlOut .= Xml::closeElement( 'form' );
		$htmlOut .= Xml::closeElement( 'fieldset' );

		// Output HTML
		$wgOut->addHTML( $htmlOut );
	}
	
	/**
	 * View or edit an individual banner
	 */
	private function showView() {
		global $wgOut, $wgUser, $wgRequest, $wgContLanguageCode, $wgScriptPath, $wgLang;
		
		$scriptPath = "$wgScriptPath/extensions/CentralNotice";
		$sk = $wgUser->getSkin();
		
		if ( $this->editable ) {
			$readonly = array();
			$disabled = array();
		} else {
			$readonly = array( 'readonly' => 'readonly' );
			$disabled = array( 'disabled' => 'disabled' );
		}

		// Get user's language
		$wpUserLang = $wgRequest->getVal( 'wpUserLanguage' ) ? $wgRequest->getVal( 'wpUserLanguage' ) : $wgContLanguageCode;

		// Get current banner
		$currentTemplate = $wgRequest->getText( 'template' );
		
		// Pull banner settings from database
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow( 'cn_templates',
			array(
				'tmp_display_anon',
				'tmp_display_account'
			),
			array( 'tmp_name' => $currentTemplate ),
			__METHOD__
		);
		
		// Begin building HTML
		$htmlOut = '';
		
		// Begin View Banner fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-banner-heading', $currentTemplate ) );

		// Show preview of banner
		$render = new SpecialNoticeText();
		$render->project = 'wikipedia';
		$render->language = $wgRequest->getVal( 'wpUserLanguage' );
		if ( $render->language != '' ) {
			$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-preview' ) . " ($render->language)",
				$render->getHtmlNotice( $wgRequest->getText( 'template' ) )
			);
		} else {
			$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-preview' ),
				$render->getHtmlNotice( $wgRequest->getText( 'template' ) )
			);
		}

		// Pull banner text and respect any inc: markup
		$bodyPage = Title::newFromText( "Centralnotice-template-{$currentTemplate}", NS_MEDIAWIKI );
		$curRev = Revision::newFromTitle( $bodyPage );
		$body = $curRev ? $curRev->getText() : '';
		
		// Extract message fields from the banner body
		$fields = array();
		preg_match_all( '/\{\{\{([A-Za-z0-9\_\-\x{00C0}-\x{017F}]+)\}\}\}/u', $body, $fields );
			
		// If there are any message fields in the banner, display translation tools.
		if ( count( $fields[0] ) > 0 ) {
			if ( $this->editable ) {
				$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			}
			$htmlOut .= Xml::fieldset(
				wfMsg( 'centralnotice-translate-heading', $currentTemplate ),
				false,
				array( 'id' => 'mw-centralnotice-translations-for' )
			);
			$htmlOut .= Xml::openElement( 'table',
				array (
					'cellpadding' => 9,
					'width' => '100%'
				)
			);
	
			// Table headers
			$htmlOut .= Xml::element( 'th', array( 'width' => '15%' ), wfMsg( 'centralnotice-message' ) );
			$htmlOut .= Xml::element( 'th', array( 'width' => '5%' ), wfMsg ( 'centralnotice-number-uses' )  );
			$htmlOut .= Xml::element( 'th', array( 'width' => '40%' ), wfMsg ( 'centralnotice-english' ) );
			$languages = Language::getLanguageNames();
			$htmlOut .= Xml::element( 'th', array( 'width' => '40%' ), $languages[$wpUserLang] );
			
			// Remove duplicate message fields
			$filteredFields = array();
			foreach ( $fields[1] as $field ) {
				$filteredFields[$field] = array_key_exists( $field, $filteredFields ) ? $filteredFields[$field] + 1 : 1;
			}
	
			// Table rows
			foreach ( $filteredFields as $field => $count ) {
				// Message
				$message = ( $wpUserLang == 'en' ) ? "Centralnotice-{$currentTemplate}-{$field}" : "Centralnotice-{$currentTemplate}-{$field}/{$wpUserLang}";
	
				// English value
				$htmlOut .= Xml::openElement( 'tr' );
	
				$title = Title::newFromText( "MediaWiki:{$message}" );
				$htmlOut .= Xml::tags( 'td', null,
					$sk->makeLinkObj( $title, htmlspecialchars( $field ) )
				);
	
				$htmlOut .= Xml::element( 'td', null, $count );
	
				// English text
				$englishText = wfMsg( 'centralnotice-message-not-set' );
				$englishTextExists = false;
				if ( Title::newFromText( "Centralnotice-{$currentTemplate}-{$field}", NS_MEDIAWIKI )->exists() ) {
					$englishText = wfMsgExt( "Centralnotice-{$currentTemplate}-{$field}",
						array( 'language' => 'en' )
					);
					$englishTextExists = true;
				}
				$htmlOut .= Xml::tags( 'td', null,
					Xml::element( 'span',
						array( 'style' => 'font-style:italic;' . ( !$englishTextExists ? 'color:silver' : '' ) ),
						$englishText
					)
				);
	
				// Foreign text input
				$foreignText = '';
				$foreignTextExists = false;
				if ( Title::newFromText( $message, NS_MEDIAWIKI )->exists() ) {
					$foreignText = wfMsgExt( "Centralnotice-{$currentTemplate}-{$field}",
						array( 'language' => $wpUserLang )
					);
					$foreignTextExists = true;
				}
				$htmlOut .= Xml::tags( 'td', null,
					Xml::input( "updateText[{$wpUserLang}][{$currentTemplate}-{$field}]", '', $foreignText,
						wfArrayMerge( $readonly,
							array( 'style' => 'width:100%;' . ( !$foreignTextExists ? 'color:red' : '' ) ) )
					)
				);
				$htmlOut .= Xml::closeElement( 'tr' );
			}
			$htmlOut .= Xml::closeElement( 'table' );
			
			if ( $this->editable ) {
				$htmlOut .= Xml::hidden( 'wpUserLanguage', $wpUserLang );
				$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
				$htmlOut .= Xml::tags( 'div', 
					array( 'class' => 'cn-buttons' ), 
					Xml::submitButton( wfMsg( 'centralnotice-modify' ), array( 'name' => 'update' ) ) 
				);
			}
			
			$htmlOut .= Xml::closeElement( 'fieldset' );
	
			if ( $this->editable ) {
				$htmlOut .= Xml::closeElement( 'form' );
			}
	
			// Show language selection form
		 	$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-change-lang' ) );
			$htmlOut .= Xml::openElement( 'table', array ( 'cellpadding' => 9 ) );
			list( $lsLabel, $lsSelect ) = Xml::languageSelector( $wpUserLang );
	
			$newPage = $this->getTitle( 'view' );
	
			$htmlOut .= Xml::tags( 'tr', null,
				Xml::tags( 'td', null, $lsLabel ) .
				Xml::tags( 'td', null, $lsSelect ) .
				Xml::tags( 'td', array( 'colspan' => 2 ),
					Xml::submitButton( wfMsg( 'centralnotice-modify' ) )
				)
			);
			$htmlOut .= Xml::tags( 'tr', null,
				Xml::tags( 'td', null, '' ) .
				Xml::tags( 'td', null, $sk->makeLinkObj( $newPage, wfMsgHtml( 'centralnotice-preview-all-template-translations' ), "template=$currentTemplate&wpUserLanguage=all" ) )
			);
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
			$htmlOut .= Xml::closeElement( 'fieldset' );
			$htmlOut .= Xml::closeElement( 'form' );
		}

		// Show edit form
		if ( $this->editable ) {
			$htmlOut .= Xml::openElement( 'form', array( 'method' => 'post' ) );
			$htmlOut .= Xml::hidden( 'wpMethod', 'editTemplate' );
		}
		
		// Show banner settings
		$htmlOut .= Xml::fieldset( 'Settings' );
		$htmlOut .= Xml::openElement( 'p', null );
		$htmlOut .= wfMsg( 'centralnotice-banner-display' );
		$htmlOut .= Xml::check( 'displayAnon', ( $row->tmp_display_anon == 1 ), wfArrayMerge( $disabled, array( 'id' => 'displayAnon' ) ) );
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-banner-anonymous' ), 'displayAnon' );
		$htmlOut .= Xml::check( 'displayAccount', ( $row->tmp_display_account == 1 ), wfArrayMerge( $disabled, array( 'id' => 'displayAccount' ) ) );
		$htmlOut .= Xml::label( wfMsg( 'centralnotice-banner-logged-in' ), 'displayAccount' );
		$htmlOut .= Xml::closeElement( 'p' );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		
		$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-edit-template' ) );
		$htmlOut .= wfMsg( 'centralnotice-edit-template-summary' );
		$buttons = array();
		$buttons[] = '<a href="#" onclick="insertButton(\'hide\');return false;">' . wfMsg( 'centralnotice-hide-button' ) . '</a>';
		$buttons[] = '<a href="#" onclick="insertButton(\'translate\');return false;">' . wfMsg( 'centralnotice-translate-button' ) . '</a>';
		$htmlOut .= Xml::tags( 'div',
			array( 'style' => 'margin-bottom: 0.2em;' ),
			'<img src="'.$scriptPath.'/down-arrow.png" style="vertical-align:baseline;"/>' . wfMsg( 'centralnotice-insert', $wgLang->commaList( $buttons ) )
		);
		$htmlOut .= Xml::textarea( 'templateBody', $body, 60, 20, $readonly );
		$htmlOut .= Xml::closeElement( 'fieldset' );
		if ( $this->editable ) {
			$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
			$htmlOut .= Xml::tags( 'div', 
				array( 'class' => 'cn-buttons' ), 
				Xml::submitButton( wfMsg( 'centralnotice-save-banner' ) ) 
			);
			$htmlOut .= Xml::closeElement( 'form' );
		}

		// Show clone form
		if ( $this->editable ) {
			$htmlOut .= Xml::openElement ( 'form',
				array(
					'method' => 'post',
					'action' => $this->getTitle( 'clone' )->getLocalUrl()
				)
			);

			$htmlOut .= Xml::fieldset( wfMsg( 'centralnotice-clone-notice' ) );
			$htmlOut .= Xml::openElement( 'table', array( 'cellpadding' => 9 ) );
			$htmlOut .= Xml::openElement( 'tr' );
			$htmlOut .= Xml::inputLabel( wfMsg( 'centralnotice-clone-name' ), 'newTemplate', 'newTemplate', '25' );
			$htmlOut .= Xml::submitButton( wfMsg( 'centralnotice-clone' ), array ( 'id' => 'clone' ) );
			$htmlOut .= Xml::hidden( 'oldTemplate', $currentTemplate );

			$htmlOut .= Xml::closeElement( 'tr' );
			$htmlOut .= Xml::closeElement( 'table' );
			$htmlOut .= Xml::hidden( 'authtoken', $wgUser->editToken() );
			$htmlOut .= Xml::closeElement( 'fieldset' );
			$htmlOut .= Xml::closeElement( 'form' );
		}
		
		// End View Banner fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );

		// Output HTML
		$wgOut->addHTML( $htmlOut );
	}
	
	/**
	 * Preview all available translations of a banner
	 */
	public function showViewAvailable( $template ) {
		global $wgOut, $wgUser;

		// Testing to see if bumping up the memory limit lets meta preview
		ini_set( 'memory_limit', '120M' );

		$sk = $wgUser->getSkin();

		// Pull all available text for a banner
		$langs = array_keys( $this->getTranslations( $template ) );
		$htmlOut = '';
		
		// Begin View Banner fieldset
		$htmlOut .= Xml::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		
		$htmlOut .= Xml::element( 'h2', null, wfMsg( 'centralnotice-banner-heading', $template ) );

		foreach ( $langs as $lang ) {
			// Link and Preview all available translations
			$viewPage = $this->getTitle( 'view' );
			$render = new SpecialNoticeText();
			$render->project = 'wikipedia';
			$render->language = $lang;
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$sk->makeLinkObj( $viewPage,
					$lang,
					'template=' . urlencode( $template ) . "&wpUserLanguage=$lang" ) .
				Xml::fieldset( wfMsg( 'centralnotice-preview' ),
					$render->getHtmlNotice( $template ),
					array( 'class' => 'cn-bannerpreview')
				)
			);
		}
		
		// End View Banner fieldset
		$htmlOut .= Xml::closeElement( 'fieldset' );
		
		return $wgOut->addHtml( $htmlOut );
	}
	
	/**
	 * Add or update a message
	 */
	private function updateMessage( $text, $translation, $lang ) {
		$title = Title::newFromText(
			( $lang == 'en' ) ? "Centralnotice-{$text}" : "Centralnotice-{$text}/{$lang}",
			NS_MEDIAWIKI
		);
		$article = new Article( $title );
		$article->doEdit( $translation, '', EDIT_FORCE_BOT );
	}
	
	private function getTemplateId ( $templateName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_templates', 'tmp_id',
			array( 'tmp_name' => $templateName ),
			__METHOD__
		);

		$row = $dbr->fetchObject( $res );
		if ( $row ) {
			return $row->tmp_id;
		}
		return null;
	}

	private function removeTemplate ( $name ) {
		global $wgOut;

		// FIXME: weak comparison
		if ( $name == '' ) {
			// FIXME: message not defined?
			$wgOut->addWikiMsg( 'centralnotice-template-doesnt-exist' );
			return;
		}

		$id = $this->getTemplateId( $name );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_assignments', 'asn_id', array( 'tmp_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			$wgOut->addWikiMsg( 'centralnotice-template-still-bound' );
			return;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$res = $dbw->delete( 'cn_templates',
				array( 'tmp_id' => $id ),
				__METHOD__
			);
			$dbw->commit();

			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$article->doDeleteArticle( 'CentralNotice Automated Removal' );
		}
	}

	/**
	 * Create a new banner
	 */
	private function addTemplate( $name, $body, $displayAnon, $displayAccount ) {
		global $wgOut;

		if ( $body == '' || $name == '' ) {
			$wgOut->addHTML( Xml::element( 'div', array( 'class' => 'cn-error' ), wfMsg( 'centralnotice-null-string' ) ) );
			return;
		}

		// Format name so there are only letters, numbers, and underscores
		$name = preg_replace( '/[^A-Za-z0-9_]/', '', $name );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'cn_templates',
			'tmp_name',
			array( 'tmp_name' => $name ),
			__METHOD__
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			$wgOut->addHTML( Xml::element( 'div', array( 'class' => 'cn-error' ), wfMsg( 'centralnotice-template-exists' ) ) );
			return false;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$res = $dbw->insert( 'cn_templates',
				array(
					'tmp_name' => $name,
					'tmp_display_anon' => $displayAnon,
					'tmp_display_account' => $displayAccount
				),
				__METHOD__
			);
			$dbw->commit();

			// Perhaps these should move into the db as blob
			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$article->doEdit( $body, '', EDIT_FORCE_BOT );
			return true;
		}
	}

	/**
	 * Update a banner
	 */
	private function editTemplate( $name, $body, $displayAnon, $displayAccount ) {
		global $wgOut;

		if ( $body == '' || $name == '' ) {
			$wgOut->addHTML( Xml::element( 'div', array( 'class' => 'cn-error' ), wfMsg( 'centralnotice-null-string' ) ) );
			return;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_templates', 'tmp_name',
			array( 'tmp_name' => $name ),
			__METHOD__
		);

		if ( $dbr->numRows( $res ) == 1 ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$res = $dbw->update( 'cn_templates',
				array(
					'tmp_display_anon' => $displayAnon,
					'tmp_display_account' => $displayAccount
				),
				array( 'tmp_name' => $name )
			);
			$dbw->commit();
		
			// Perhaps these should move into the db as blob
			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$article->doEdit( $body, '', EDIT_FORCE_BOT );
			return;
		}
	}

	/**
	 * Copy all the data from one banner to another
	 */
	public function cloneTemplate( $source, $dest ) {
	
		// Reset the timer as updates on meta take a long time
		set_time_limit( 300 );
		
		// Pull all possible langs
		$langs = $this->getTranslations( $source );

		// Normalize name
		$dest = ereg_replace( '[^A-Za-z0-9\_]', '', $dest );
		
		// Pull banner settings from database
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow( 'cn_templates',
			array(
				'tmp_display_anon',
				'tmp_display_account'
			),
			array( 'tmp_name' => $source ),
			__METHOD__
		);
		$displayAnon = $row->tmp_display_anon;
		$displayAccount = $row->tmp_display_account;

		// Pull banner text and respect any inc: markup
		$bodyPage = Title::newFromText( "Centralnotice-template-{$source}", NS_MEDIAWIKI );
		$template_body = Revision::newFromTitle( $bodyPage )->getText();

		// Create new banner
		if ( $this->addTemplate( $dest, $template_body, $displayAnon, $displayAccount ) ) {

			// Populate the fields
			foreach ( $langs as $lang => $fields ) {
				foreach ( $fields as $field => $text ) {
					$this->updateMessage( "$dest-$field", $text, $lang );
				}
			}
			return $dest;
		}
	}

	/**
	 * Find all message fields set for a banner
	 */
	private function findFields( $template ) {
		$messages = array();
		$body = wfMsg( "Centralnotice-template-{$template}" );

		// Generate list of message fields from parsing the body
		$fields = array();
		preg_match_all( '/\{\{\{([A-Za-z0-9\_\-\x{00C0}-\x{017F}]+)\}\}\}/u', $body, $fields );

		// Remove duplicates
		$filteredFields = array();
		foreach ( $fields[1] as $field ) {
			$filteredFields[$field] = array_key_exists( $field, $filteredFields ) ? $filteredFields[$field] + 1 :
			1;
		}
		return $filteredFields;
	}

	/**
	 * Get all the translations of all the messages for a banner
	 * @return a 2D array of every set message in every language for one banner
	 */
	public function getTranslations( $template ) {
		$translations = array();

		// Pull all language codes to enumerate
		$allLangs = array_keys( Language::getLanguageNames() );

		// Lookup all the message fields for a banner
		$fields = $this->findFields( $template );

		// Iterate through all possible languages to find matches
		foreach ( $allLangs as $lang ) {
			// Iterate through all possible message fields
			foreach ( $fields as $field => $count ) {
				// Put all message fields together for a lookup
				$message = ( $lang == 'en' ) ? "Centralnotice-{$template}-{$field}" : "Centralnotice-{$template}-{$field}/{$lang}";
				if ( Title::newFromText( $message,  NS_MEDIAWIKI )->exists() ) {
					$translations[$lang][$field] = wfMsgExt(
						"Centralnotice-{$template}-{$field}",
						array( 'language' => $lang )
					);
				}
			}
		}
		return $translations;
	}
}
