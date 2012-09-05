<?php

class SpecialNoticeTemplate extends UnlistedSpecialPage {
	var $editable, $centralNoticeError;

	function __construct() {
		// Register special page
		parent::__construct( 'NoticeTemplate' );
	}

	/**
	 * Handle different types of page requests
	 */
	function execute( $sub ) {
		global $wgOut, $wgUser, $wgRequest;

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$wgOut->addModules( 'ext.centralNotice.interface' );

		// Check permissions
		$this->editable = $wgUser->isAllowed( 'centralnotice-admin' );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$wgOut->addWikiMsg( 'centralnotice-summary' );

		// Show header
		CentralNotice::printHeader();

		// Begin Banners tab content
		$wgOut->addHTML( Html::openElement( 'div', array( 'id' => 'preferences' ) ) );

		$method = $wgRequest->getVal( 'wpMethod' );

		// Handle form submissions
		if ( $this->editable && $wgRequest->wasPosted() ) {

			// Check authentication token
			if ( $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {

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

				// Handle adding banner
				if ( $method == 'addTemplate' ) {
					$newTemplateName = $wgRequest->getText( 'templateName' );
					$newTemplateBody = $wgRequest->getText( 'templateBody' );
					if ( $newTemplateName != '' && $newTemplateBody != '' ) {
						$this->addTemplate(
							$newTemplateName,
							$newTemplateBody,
							$wgRequest->getBool( 'displayAnon' ),
							$wgRequest->getBool( 'displayAccount' ),
							$wgRequest->getBool( 'fundraising' ),
							$wgRequest->getBool( 'autolink' ),
							$wgRequest->getVal( 'landingPages' )
						);
						$sub = 'view';
					} else {
						$this->showError( 'centralnotice-null-string' );
					}
				}

				// Handle editing banner
				if ( $method == 'editTemplate' ) {
					$this->editTemplate(
						$wgRequest->getText( 'template' ),
						$wgRequest->getText( 'templateBody' ),
						$wgRequest->getBool( 'displayAnon' ),
						$wgRequest->getBool( 'displayAccount' ),
						$wgRequest->getBool( 'fundraising' ),
						$wgRequest->getBool( 'autolink' ),
						$wgRequest->getVal( 'landingPages' )
					);
					$sub = 'view';
				}

			} else {
				$this->showError( 'sessionfailure' );
			}

		}

		// Handle viewing of a banner in all languages
		if ( $sub == 'view' && $wgRequest->getVal( 'wpUserLanguage' ) == 'all' ) {
			$template = $wgRequest->getVal( 'template' );
			$this->showViewAvailable( $template );
			$wgOut->addHTML( Html::closeElement( 'div' ) );
			return;
		}

		// Handle viewing a specific banner
		if ( $sub == 'view' && $wgRequest->getText( 'template' ) != '' ) {
			$this->showView();
			$wgOut->addHTML( Html::closeElement( 'div' ) );
			return;
		}

		if ( $this->editable ) {
			// Handle showing "Add a banner" interface
			if ( $sub == 'add' ) {
				$this->showAdd();
				$wgOut->addHTML( Html::closeElement( 'div' ) );
				return;
			}

			// Handle cloning a specific banner
			if ( $sub == 'clone' ) {

				// Check authentication token
				if ( $wgUser->matchEditToken( $wgRequest->getVal( 'authtoken' ) ) ) {

					$oldTemplate = $wgRequest->getVal( 'oldTemplate' );
					$newTemplate = $wgRequest->getVal( 'newTemplate' );
					// We use the returned name in case any special characters had to be removed
					$template = $this->cloneTemplate( $oldTemplate, $newTemplate );
					$wgOut->redirect(
						$this->getTitle( 'view' )->getLocalUrl( "template=$template" ) );
					return;

				} else {
					$this->showError( 'sessionfailure' );
				}

			}

		}

		// Show list of banners by default
		$this->showList();

		// End Banners tab content
		$wgOut->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Show a list of available banners. Newer banners are shown first.
	 */
	function showList() {
		global $wgOut, $wgRequest;

		// Sanitize input on search key and split out terms
		$searchTerms = SpecialNoticeTemplate::sanitizeSearchTerms( $wgRequest->getText( 'tplsearchkey' ) );

		// Get the pager object
		$pager = new TemplatePager( $this, $searchTerms );

		// Begin building HTML
		$htmlOut = '';

		// Begin Manage Banners fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// Tab header
		$htmlOut .= Html::element( 'h2', null, $this->msg( 'centralnotice-manage-templates' )->text() );

		// Search box
		$htmlOut .= Html::openElement( 'fieldset', array( 'id' => 'cn-template-searchbox' ) );
		$htmlOut .= Html::element( 'legend', null, $this->msg( 'centralnotice-filter-template-banner' )->text() );

		$htmlOut .= Html::openElement( 'form', array( 'method' => 'get' ) );

		$htmlOut .= Html::element( 'label', array( 'for' => 'tplsearchkey' ), $this->msg( 'centralnotice-filter-template-prompt' )->text() );
		$htmlOut .= Html::input( 'tplsearchkey', $searchTerms );
		$htmlOut .= Html::element( 'input', array( 'type'=> 'submit', 'value' => $this->msg( 'centralnotice-filter-template-submit' )->text() ) );

		$htmlOut .= Html::closeElement( 'form' );
		$htmlOut .= Html::closeElement( 'fieldset' );

		if ( !$pager->getNumRows() ) {
			$htmlOut .= Html::element( 'p', null, $this->msg( 'centralnotice-no-templates' )->text() );
		} else {
			// Pagination form wrapper
			if ( $this->editable ) {
				$htmlOut .= Html::openElement( 'form', array( 'method' => 'post' ) );
			}

			// Show paginated list of banners
			$htmlOut .= Xml::tags( 'div', array( 'class' => 'cn-pager' ),
				$pager->getNavigationBar() );
			$htmlOut .= $pager->getBody();
			$htmlOut .= Xml::tags( 'div', array( 'class' => 'cn-pager' ),
				$pager->getNavigationBar() );

			if ( $this->editable ) {
				$htmlOut .= Html::closeElement( 'form' );
			}
		}

		if ( $this->editable ) {
			$htmlOut .= Html::element( 'p' );
			$newPage = $this->getTitle( 'add' );
			$htmlOut .= Linker::link(
				$newPage,
				$this->msg( 'centralnotice-add-template' )->escaped()
			);
		}

		// End Manage Banners fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$wgOut->addHTML( $htmlOut );
	}

	/**
	 * Show "Add a banner" interface
	 */
	function showAdd() {
		global $wgOut, $wgUser, $wgLang, $wgRequest,
		       $wgNoticeEnableFundraising;

		// Build HTML
		$htmlOut = '';
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		$htmlOut .= Html::openElement( 'form',
			array( 'method' => 'post', 'onsubmit' => 'return validateBannerForm(this)' ) );
		$htmlOut .= Html::element( 'h2', null, $this->msg( 'centralnotice-add-template' )->text() );
		$htmlOut .= Html::hidden( 'wpMethod', 'addTemplate' );

		// If there was an error, we'll need to restore the state of the form
		if ( $wgRequest->wasPosted() ) {
			$templateName = $wgRequest->getVal( 'templateName' );
			$displayAnon = $wgRequest->getCheck( 'displayAnon' );
			$displayAccount = $wgRequest->getCheck( 'displayAccount' );
			$fundraising = $wgRequest->getCheck( 'fundraising' );
			$autolink = $wgRequest->getCheck( 'autolink' );
			$landingPages = $wgRequest->getVal( 'landingPages' );
			$body = $wgRequest->getVal( 'templateBody' );
		} else { // Use default values
			$templateName = '';
			$displayAnon = true;
			$displayAccount = true;
			$fundraising = false;
			$autolink = false;
			$landingPages = '';
			$body = '';
		}

		$htmlOut .= Xml::tags( 'p', null,
			Xml::inputLabel(
				$this->msg( 'centralnotice-banner-name' )->text(),
				'templateName', 'templateName', 25, $templateName
			)
		);

		// Display settings
		$htmlOut .= Html::openElement( 'p', null );
		$htmlOut .= $this->msg( 'centralnotice-banner-display' )->escaped();
		$htmlOut .= Xml::check( 'displayAnon', $displayAnon, array( 'id' => 'displayAnon' ) );
		$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-anonymous' )->text(), 'displayAnon' );
		$htmlOut .= Xml::check( 'displayAccount', $displayAccount,
			array( 'id' => 'displayAccount' ) );
		$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-logged-in' )->text(), 'displayAccount' );
		$htmlOut .= Html::closeElement( 'p' );

		// Fundraising settings
		if ( $wgNoticeEnableFundraising ) {

			// Checkbox for indicating if it is a fundraising banner
			$htmlOut .= Html::openElement( 'p', null );
			$htmlOut .= Xml::check( 'fundraising', $fundraising, array( 'id' => 'fundraising' ) );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-fundraising' )->text(), 'fundraising' );
			$htmlOut .= Html::closeElement( 'p' );

			// Checkbox for whether or not to automatically create landing page link
			$htmlOut .= Html::openElement( 'p', null );
			$htmlOut .= Xml::check( 'autolink', $autolink, array( 'id' => 'autolink' ) );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-autolink' )->text(), 'autolink' );
			$htmlOut .= Html::closeElement( 'p' );

			// Interface for setting the landing pages
			$htmlOut .= Html::openElement( 'div',
				array( 'id' => 'autolinkInterface', 'style' => 'display: none;' ) );
			$htmlOut .= Xml::tags( 'p', array(),
				$this->msg( 'centralnotice-banner-autolink-help' )
					->rawParams( 'id="cn-landingpage-link"', 'JimmyAppeal01' )->escaped() );
			$htmlOut .= Xml::tags( 'p', array(),
				Xml::inputLabel(
					$this->msg( 'centralnotice-banner-landing-pages' )->text(),
					'landingPages', 'landingPages', 40, $landingPages,
					array( 'maxlength' => 255 )
				)
			);
			$htmlOut .= Html::closeElement( 'div' );
		}

		// Begin banner body section
		$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-banner' )->text() );
		$htmlOut .= $this->msg( 'centralnotice-edit-template-summary' )->text();
		$buttons = array();
		$buttons[ ] = '<a href="#" onclick="insertButton(\'close\');return false;">' .
			$this->msg( 'centralnotice-close-button' )->text() . '</a>';
		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'banner-editing-top-hint' ),
			$this->msg( 'centralnotice-insert', $wgLang->commaList( $buttons ) )->text()
		);

		$htmlOut .= Xml::textarea( 'templateBody', $body, 60, 20 );
		$htmlOut .= Html::closeElement( 'fieldset' );
		$htmlOut .= Html::hidden( 'authtoken', $wgUser->getEditToken() );

		// Submit button
		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'cn-buttons' ),
			Xml::submitButton( $this->msg( 'centralnotice-save-banner' )->text() )
		);

		$htmlOut .= Html::closeElement( 'form' );
		$htmlOut .= Html::closeElement( 'fieldset' );

		// Output HTML
		$wgOut->addHTML( $htmlOut );
	}

	/**
	 * View or edit an individual banner
	 */
	private function showView() {
		global $wgOut, $wgUser, $wgRequest, $wgLanguageCode, $wgLang,
		       $wgNoticeEnableFundraising;

		if ( $this->editable ) {
			$readonly = array();
			$disabled = array();
		} else {
			$readonly = array( 'readonly' => 'readonly' );
			$disabled = array( 'disabled' => 'disabled' );
		}

		// Get the language to display the banner preview and messages in
		$wpUserLang = $wgRequest->getVal( 'wpUserLanguage', $wgLanguageCode );

		// Get current banner
		$currentTemplate = $wgRequest->getText( 'template' );

		$bannerSettings = CentralNoticeDB::getBannerSettings( $currentTemplate );

		if ( !$bannerSettings ) {
			$this->showError( 'centralnotice-banner-doesnt-exist' );
			return;
		} else {
			// Begin building HTML
			$htmlOut = '';

			// Begin View Banner fieldset
			$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

			$htmlOut .= Html::element( 'h2', null,
				$this->msg( 'centralnotice-banner-heading', $currentTemplate )->text() );

			// Show preview of banner
			$render = new SpecialBannerLoader();
			$render->siteName = 'Wikipedia';
			$render->language = $wpUserLang;
			try {
				$preview = $render->getHtmlNotice( $wgRequest->getText( 'template' ) );
			} catch ( SpecialBannerLoaderException $e ) {
				$preview = $this->msg( 'centralnotice-nopreview' )->text();
			}
			if ( $render->language != '' ) {
				$htmlOut .= Xml::fieldset(
					$this->msg( 'centralnotice-preview' )->text() . " ($render->language)",
					$preview
				);
			} else {
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-preview' )->text(),
					$preview
				);
			}

			// Pull banner text and respect any inc: markup
			$bodyPage = Title::newFromText(
				"Centralnotice-template-{$currentTemplate}", NS_MEDIAWIKI );
			$curRev = Revision::newFromTitle( $bodyPage );
			$body = $curRev ? $curRev->getText() : '';

			// Extract message fields from the banner body
			$fields = array();
			$allowedChars = Title::legalChars();
			preg_match_all( "/\{\{\{([$allowedChars]+)\}\}\}/u", $body, $fields );
			
			// Remove magic words that don't need translation
			foreach ( $fields[ 0 ] as $index => $rawField ) {
				if ( $rawField === '{{{campaign}}}' || $rawField === '{{{banner}}}' ) {
					unset( $fields[ 0 ][ $index ] ); // unset raw field
					unset( $fields[ 1 ][ $index ] ); // unset field name
				}
			}

			// If there are any message fields in the banner, display translation tools.
			if ( count( $fields[ 0 ] ) > 0 ) {
				if ( $this->editable ) {
					$htmlOut .= Html::openElement( 'form', array( 'method' => 'post' ) );
				}
				$htmlOut .= Xml::fieldset(
					$this->msg( 'centralnotice-translate-heading', $currentTemplate )->text(),
					false,
					array( 'id' => 'mw-centralnotice-translations-for' )
				);
				$htmlOut .= Html::openElement( 'table',
					array(
						'cellpadding' => 9,
						'width'       => '100%'
					)
				);

				// Table headers
				$htmlOut .= Html::element( 'th', array( 'width' => '15%' ),
					$this->msg( 'centralnotice-message' )->text() );
				$htmlOut .= Html::element( 'th', array( 'width' => '5%' ),
					$this->msg( 'centralnotice-number-uses' )->text() );
				$htmlOut .= Html::element( 'th', array( 'width' => '40%' ),
					$this->msg( 'centralnotice-english' )->text() );
				$languages = Language::getTranslatedLanguageNames( $wgLang->getCode() );
				$htmlOut .= Html::element( 'th', array( 'width' => '40%' ),
					$languages[ $wpUserLang ] );

				// Remove duplicate message fields
				$filteredFields = array();
				foreach ( $fields[ 1 ] as $field ) {
					$filteredFields[ $field ] = array_key_exists( $field, $filteredFields )
						? $filteredFields[ $field ] + 1 : 1;
				}

				// Table rows
				foreach ( $filteredFields as $field => $count ) {
					// Message
					$message = ( $wpUserLang == 'en' )
						? "Centralnotice-{$currentTemplate}-{$field}"
						: "Centralnotice-{$currentTemplate}-{$field}/{$wpUserLang}";

					// English value
					$htmlOut .= Html::openElement( 'tr' );

					$title = Title::newFromText( "MediaWiki:{$message}" );
					$htmlOut .= Xml::tags( 'td', null,
						Linker::link( $title, htmlspecialchars( $field ) )
					);

					$htmlOut .= Html::element( 'td', null, $count );

					// English text
					$englishText = $this->msg( 'centralnotice-message-not-set' )->text();
					$englishTextExists = false;
					if (
						Title::newFromText(
							"Centralnotice-{$currentTemplate}-{$field}", NS_MEDIAWIKI
						)->exists()
					) {
						$englishText = $this->msg( "Centralnotice-{$currentTemplate}-{$field}" )
							->inLanguage( 'en' )->text();
						$englishTextExists = true;
					}
					$htmlOut .= Xml::tags( 'td', null,
						Html::element( 'span',
							array(
								'style' => 'font-style:italic;' .
									( !$englishTextExists ? 'color:silver' : '' )
							),
							$englishText
						)
					);

					// Foreign text input
					$foreignText = '';
					$foreignTextExists = false;
					if ( Title::newFromText( $message, NS_MEDIAWIKI )->exists() ) {
						// @todo FIXME: Use $this->msg(), but ensure inLanguage( $wpUserLang ) doesn't cause issues.
						$foreignText = wfMsgExt( "Centralnotice-{$currentTemplate}-{$field}",
							array( 'language' => $wpUserLang )
						);
						$foreignTextExists = true;
					}
					$htmlOut .= Xml::tags( 'td', null,
						Xml::input(
							"updateText[{$wpUserLang}][{$currentTemplate}-{$field}]",
							'',
							$foreignText,
							wfArrayMerge( $readonly,
								array( 'style' => 'width:100%;' .
									( !$foreignTextExists ? 'color:red' : '' ) ) )
						)
					);
					$htmlOut .= Html::closeElement( 'tr' );
				}
				$htmlOut .= Html::closeElement( 'table' );

				if ( $this->editable ) {
					$htmlOut .= Html::hidden( 'wpUserLanguage', $wpUserLang );
					$htmlOut .= Html::hidden( 'authtoken', $wgUser->getEditToken() );
					$htmlOut .= Xml::tags( 'div',
						array( 'class' => 'cn-buttons' ),
						Xml::submitButton(
							$this->msg( 'centralnotice-modify' )->text(),
							array( 'name' => 'update' )
						)
					);
				}

				$htmlOut .= Html::closeElement( 'fieldset' );

				if ( $this->editable ) {
					$htmlOut .= Html::closeElement( 'form' );
				}

				// Show language selection form
				$actionTitle = $this->getTitleFor( 'NoticeTemplate', 'view' );
				$actionUrl = $actionTitle->getLocalURL();
				$htmlOut .= Html::openElement( 'form', array( 'method' => 'get', 'action' => $actionUrl ) );
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-change-lang' )->text() );
				$htmlOut .= Html::hidden( 'template', $currentTemplate );
				$htmlOut .= Html::openElement( 'table', array( 'cellpadding' => 9 ) );
				// Retrieve the language list
				list( $lsLabel, $lsSelect ) = Xml::languageSelector( $wpUserLang, true, $wgLang->getCode() );

				$newPage = $this->getTitle( 'view' );

				$htmlOut .= Xml::tags( 'tr', null,
					Xml::tags( 'td', null, $lsLabel ) .
						Xml::tags( 'td', null, $lsSelect ) .
						Xml::tags( 'td', array( 'colspan' => 2 ),
							Xml::submitButton( $this->msg( 'centralnotice-modify' )->text() )
						)
				);
				$htmlOut .= Xml::tags( 'tr', null,
					Xml::tags( 'td', null, '' ) . Xml::tags(
						'td',
						null,
						Linker::link(
							$newPage,
							$this->msg( 'centralnotice-preview-all-template-translations' )->escaped(),
							array(),
							array(
								'template' => $currentTemplate,
								'wpUserLanguage' => 'all'
							)
						)
					)
				);
				$htmlOut .= Html::closeElement( 'table' );
				$htmlOut .= Html::closeElement( 'fieldset' );
				$htmlOut .= Html::closeElement( 'form' );
			}

			// Show edit form
			if ( $this->editable ) {
				$htmlOut .= Html::openElement( 'form',
					array(
						'method'   => 'post',
						'onsubmit' => 'return validateBannerForm(this)'
					)
				);
				$htmlOut .= Html::hidden( 'wpMethod', 'editTemplate' );
			}

			// If there was an error, we'll need to restore the state of the form
			if ( $wgRequest->wasPosted() && $wgRequest->getVal( 'mainform' ) ) {
				$displayAnon = $wgRequest->getCheck( 'displayAnon' );
				$displayAccount = $wgRequest->getCheck( 'displayAccount' );
				$fundraising = $wgRequest->getCheck( 'fundraising' );
				$autolink = $wgRequest->getCheck( 'autolink' );
				$landingPages = $wgRequest->getVal( 'landingPages' );
				$body = $wgRequest->getVal( 'templateBody', $body );
			} else { // Use previously stored values
				$displayAnon = ( $bannerSettings[ 'anon' ] == 1 );
				$displayAccount = ( $bannerSettings[ 'account' ] == 1 );
				$fundraising = ( $bannerSettings[ 'fundraising' ] == 1 );
				$autolink = ( $bannerSettings[ 'autolink' ] == 1 );
				$landingPages = $bannerSettings[ 'landingpages' ];
				// $body default is defined prior to message interface code
			}

			// Show banner settings
			$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-settings' )->text() );
			$htmlOut .= Html::openElement( 'p', null );
			$htmlOut .= $this->msg( 'centralnotice-banner-display' )->escaped();
			$htmlOut .= Xml::check( 'displayAnon', $displayAnon,
				wfArrayMerge( $disabled, array( 'id' => 'displayAnon' ) ) );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-anonymous' )->text(), 'displayAnon' );
			$htmlOut .= Xml::check( 'displayAccount', $displayAccount,
				wfArrayMerge( $disabled, array( 'id' => 'displayAccount' ) ) );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-logged-in' )->text(), 'displayAccount' );
			$htmlOut .= Html::closeElement( 'p' );

			// Fundraising settings
			if ( $wgNoticeEnableFundraising ) {

				// Checkbox for indicating if it is a fundraising banner
				$htmlOut .= Html::openElement( 'p', null );
				$htmlOut .= Xml::check( 'fundraising', $fundraising,
					wfArrayMerge( $disabled, array( 'id' => 'fundraising' ) ) );
				$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-fundraising' )->text(),
					'fundraising' );
				$htmlOut .= Html::closeElement( 'p' );

				// Checkbox for whether or not to automatically create landing page link
				$htmlOut .= Html::openElement( 'p', null );
				$htmlOut .= Xml::check( 'autolink', $autolink,
					wfArrayMerge( $disabled, array( 'id' => 'autolink' ) ) );
				$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-autolink' )->text(),
					'autolink' );
				$htmlOut .= Html::closeElement( 'p' );

				// Interface for setting the landing pages
				if ( $autolink ) {
					$htmlOut .= Html::openElement( 'div', array( 'id'=> 'autolinkInterface' ) );
				} else {
					$htmlOut .= Html::openElement( 'div',
						array( 'id'=> 'autolinkInterface', 'style'=> 'display:none;' ) );
				}
				$htmlOut .= Xml::tags(
					'p',
					array(),
					$this->msg( 'centralnotice-banner-autolink-help' )
						->rawParams( 'id="cn-landingpage-link"', 'JimmyAppeal01' )->escaped()
				);
				$htmlOut .= Xml::tags( 'p', array(),
					Xml::inputLabel(
						$this->msg( 'centralnotice-banner-landing-pages' )->text(),
						'landingPages', 'landingPages', 40, $landingPages,
						array( 'maxlength' => 255 )
					)
				);
				$htmlOut .= Html::closeElement( 'div' );

			}

			// Begin banner body section
			$htmlOut .= Html::closeElement( 'fieldset' );
			if ( $this->editable ) {
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-edit-template' )->text() );
				$htmlOut .= $this->msg( 'centralnotice-edit-template-summary' )->escaped();
				$buttons = array();
				$buttons[ ] = '<a href="#" onclick="insertButton(\'close\');return false;">' .
					$this->msg( 'centralnotice-close-button' )->text() . '</a>';
				$htmlOut .= Xml::tags( 'div',
					array( 'class' => 'banner-editing-top-hint' ),
					$this->msg( 'centralnotice-insert', $wgLang->commaList( $buttons ) )->escaped()
				);
			} else {
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-banner' )->text() );
			}
			$htmlOut .= Xml::textarea( 'templateBody', $body, 60, 20, $readonly );
			$htmlOut .= Html::closeElement( 'fieldset' );
			if ( $this->editable ) {
				// Indicate which form was submitted
				$htmlOut .= Html::hidden( 'mainform', 'true' );
				$htmlOut .= Html::hidden( 'authtoken', $wgUser->getEditToken() );
				$htmlOut .= Xml::tags( 'div',
					array( 'class' => 'cn-buttons' ),
					Xml::submitButton( $this->msg( 'centralnotice-save-banner' )->text() )
				);
				$htmlOut .= Html::closeElement( 'form' );
			}

			// Show clone form
			if ( $this->editable ) {
				$htmlOut .= Html::openElement( 'form',
					array(
						'method' => 'post',
						'action' => $this->getTitle( 'clone' )->getLocalUrl()
					)
				);

				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-clone-notice' )->text() );
				$htmlOut .= Html::openElement( 'table', array( 'cellpadding' => 9 ) );
				$htmlOut .= Html::openElement( 'tr' );
				$htmlOut .= Xml::inputLabel(
					$this->msg( 'centralnotice-clone-name' )->text(),
					'newTemplate', 'newTemplate', '25' );
				$htmlOut .= Xml::submitButton(
					$this->msg( 'centralnotice-clone' )->text(),
					array( 'id' => 'clone' ) );
				$htmlOut .= Html::hidden( 'oldTemplate', $currentTemplate );

				$htmlOut .= Html::closeElement( 'tr' );
				$htmlOut .= Html::closeElement( 'table' );
				$htmlOut .= Html::hidden( 'authtoken', $wgUser->getEditToken() );
				$htmlOut .= Html::closeElement( 'fieldset' );
				$htmlOut .= Html::closeElement( 'form' );
			}

			// End View Banner fieldset
			$htmlOut .= Html::closeElement( 'fieldset' );

			// Output HTML
			$wgOut->addHTML( $htmlOut );
		}
	}

	/**
	 * Preview all available translations of a banner
	 */
	public function showViewAvailable( $template ) {
		global $wgOut;

		// Testing to see if bumping up the memory limit lets meta preview
		ini_set( 'memory_limit', '120M' );

		// Pull all available text for a banner
		$langs = array_keys( $this->getTranslations( $template ) );
		$htmlOut = '';

		// Begin View Banner fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Html::element(
			'h2',
			null,
			$this->msg( 'centralnotice-banner-heading', $template )->text()
		);

		foreach ( $langs as $lang ) {
			// Link and Preview all available translations
			$viewPage = $this->getTitle( 'view' );
			$render = new SpecialBannerLoader();
			$render->siteName = 'Wikipedia';
			$render->language = $lang;
			try {
				$preview = $render->getHtmlNotice( $template );
			} catch ( SpecialBannerLoaderException $e ) {
				$preview = $this->msg( 'centralnotice-nopreview' )->text();
			}
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Linker::link(
					$viewPage,
					$lang,
					array(),
					array(
						'template' => $template,
						'wpUserLanguage' => $lang
					)
				) . Xml::fieldset(
					$this->msg( 'centralnotice-preview' )->text(),
					$preview,
					array( 'class' => 'cn-bannerpreview' )
				)
			);
		}

		// End View Banner fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$wgOut->addHtml( $htmlOut );
	}

	/**
	 * Add or update a message
	 */
	private function updateMessage( $text, $translation, $lang ) {
		$title = Title::newFromText(
			( $lang == 'en' ) ? "Centralnotice-{$text}" : "Centralnotice-{$text}/{$lang}",
			NS_MEDIAWIKI
		);
		$wikiPage = new WikiPage( $title );
		$wikiPage->doEdit( $translation, '', EDIT_FORCE_BOT );
	}

	private static function getTemplateId( $templateName ) {
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

	public static function getBannerName( $bannerId ) {
		$dbr = wfGetDB( DB_MASTER );
		if ( is_numeric( $bannerId ) ) {
			$row = $dbr->selectRow( 'cn_templates', 'tmp_name', array( 'tmp_id' => $bannerId ) );
			if ( $row ) {
				return $row->tmp_name;
			}
		}
		return null;
	}

	public function removeTemplate( $name ) {
		$id = SpecialNoticeTemplate::getTemplateId( $name );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_assignments', 'asn_id', array( 'tmp_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			$this->showError( 'centralnotice-template-still-bound' );
			return;
		} else {
			// Log the removal of the banner
			$this->logBannerChange( 'removed', $id );

			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$dbw->delete( 'cn_templates',
				array( 'tmp_id' => $id ),
				__METHOD__
			);
			$dbw->commit();

			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$article->doDeleteArticle( 'CentralNotice automated removal' );
		}
	}

	/**
	 * Create a new banner
	 *
	 * @param $name           string name of banner
	 * @param $body           string content of banner
	 * @param $displayAnon    integer flag for display to anonymous users
	 * @param $displayAccount integer flag for display to logged in users
	 * @param $fundraising    integer flag for fundraising banner (optional)
	 * @param $autolink       integer flag for automatically creating landing page links (optional)
	 * @param $landingPages   string list of landing pages (optional)
	 *
	 * @return bool true or false depending on whether banner was successfully added
	 */
	public function addTemplate( $name, $body, $displayAnon, $displayAccount, $fundraising = 0,
	                             $autolink = 0, $landingPages = ''
	) {
		if ( $body == '' || $name == '' ) {
			$this->showError( 'centralnotice-null-string' );
			return false;
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
			$this->showError( 'centralnotice-template-exists' );
			return false;
		} else {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->insert( 'cn_templates',
				array(
					'tmp_name'            => $name,
					'tmp_display_anon'    => $displayAnon,
					'tmp_display_account' => $displayAccount,
					'tmp_fundraising'     => $fundraising,
					'tmp_autolink'        => $autolink,
					'tmp_landing_pages'   => $landingPages
				),
				__METHOD__
			);
			$bannerId = $dbw->insertId();

			// Perhaps these should move into the db as blobs instead of being stored as articles
			$wikiPage = new WikiPage(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$wikiPage->doEdit( $body, '', EDIT_FORCE_BOT );

			// Log the creation of the banner
			$beginSettings = array();
			$endSettings = array(
				'anon'         => $displayAnon,
				'account'      => $displayAccount,
				'fundraising'  => $fundraising,
				'autolink'     => $autolink,
				'landingpages' => $landingPages
			);
			$this->logBannerChange( 'created', $bannerId, $beginSettings, $endSettings );

			return true;
		}
	}

	/**
	 * Update a banner
	 */
	private function editTemplate( $name, $body, $displayAnon, $displayAccount, $fundraising,
	                               $autolink, $landingPages
	) {
		if ( $body == '' || $name == '' ) {
			$this->showError( 'centralnotice-null-string' );
			return;
		}

		$initialBannerSettings = CentralNoticeDB::getBannerSettings( $name, true );

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_templates', 'tmp_name',
			array( 'tmp_name' => $name ),
			__METHOD__
		);

		if ( $dbr->numRows( $res ) == 1 ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'cn_templates',
				array(
					'tmp_display_anon'    => $displayAnon,
					'tmp_display_account' => $displayAccount,
					'tmp_fundraising'     => $fundraising,
					'tmp_autolink'        => $autolink,
					'tmp_landing_pages'   => $landingPages
				),
				array( 'tmp_name' => $name )
			);

			// Perhaps these should move into the db as blob
			$wikiPage = new WikiPage(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);

			$wikiPage->doEdit( $body, '', EDIT_FORCE_BOT );

			$bannerId = SpecialNoticeTemplate::getTemplateId( $name );
			$finalBannerSettings = CentralNoticeDB::getBannerSettings( $name, true );

			// If there are any difference between the old settings and the new settings, log them.
			$diffs = array_diff_assoc( $initialBannerSettings, $finalBannerSettings );
			if ( $diffs ) {
				$this->logBannerChange( 'modified', $bannerId, $initialBannerSettings, $finalBannerSettings );
			}

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
		$dest = preg_replace( '/[^A-Za-z0-9_]/', '', $dest );

		// Pull banner settings from database
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow( 'cn_templates',
			array(
				'tmp_display_anon',
				'tmp_display_account',
				'tmp_fundraising',
				'tmp_autolink',
				'tmp_landing_pages'
			),
			array( 'tmp_name' => $source ),
			__METHOD__
		);
		$displayAnon = $row->tmp_display_anon;
		$displayAccount = $row->tmp_display_account;
		$fundraising = $row->tmp_fundraising;
		$autolink = $row->tmp_autolink;
		$landingPages = $row->tmp_landing_pages;

		// Pull banner text and respect any inc: markup
		$bodyPage = Title::newFromText( "Centralnotice-template-{$source}", NS_MEDIAWIKI );
		$template_body = Revision::newFromTitle( $bodyPage )->getText();

		// Create new banner
		if ( $this->addTemplate( $dest, $template_body, $displayAnon, $displayAccount, $fundraising,
			$autolink, $landingPages )
		) {

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
		$body = $this->msg( "Centralnotice-template-{$template}" )->text();

		// Generate list of message fields from parsing the body
		$fields = array();
		$allowedChars = Title::legalChars();
		preg_match_all( "/\{\{\{([$allowedChars]+)\}\}\}/u", $body, $fields );

		// Remove duplicates
		$filteredFields = array();
		foreach ( $fields[ 1 ] as $field ) {
			$filteredFields[ $field ] = array_key_exists( $field, $filteredFields )
				? $filteredFields[ $field ] + 1
				: 1;
		}
		return $filteredFields;
	}

	/**
	 * Get all the translations of all the messages for a banner
	 *
	 * @param $template
	 * @return array A 2D array of every set message in every language for one banner
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
				$message = ( $lang == 'en' )
					? "Centralnotice-{$template}-{$field}"
					: "Centralnotice-{$template}-{$field}/{$lang}";
				if ( Title::newFromText( $message, NS_MEDIAWIKI )->exists() ) {
					$translations[ $lang ][ $field ] = $this->msg(
						"Centralnotice-{$template}-{$field}"
					)->inLanguage( $lang )->text();
				}
			}
		}
		return $translations;
	}

	function showError( $message ) {
		global $wgOut;
		$wgOut->wrapWikiMsg( "<div class='cn-error'>\n$1\n</div>", $message );
		$this->centralNoticeError = true;
	}

	/**
	 * Log setting changes related to a banner
	 *
	 * @param $action        string: 'created', 'modified', or 'removed'
	 * @param $bannerId      integer: ID of banner
	 * @param $beginSettings array of banner settings before changes (optional)
	 * @param $endSettings   array of banner settings after changes (optional)
	 * @return int
	 */
	function logBannerChange( $action, $bannerId, $beginSettings = array(), $endSettings = array() ) {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );

		$log = array(
			'tmplog_timestamp'     => $dbw->timestamp(),
			'tmplog_user_id'       => $wgUser->getId(),
			'tmplog_action'        => $action,
			'tmplog_template_id'   => $bannerId,
			'tmplog_template_name' => SpecialNoticeTemplate::getBannerName( $bannerId )
		);

		foreach ( $beginSettings as $key => $value ) {
			$log[ 'tmplog_begin_' . $key ] = $value;
		}
		foreach ( $endSettings as $key => $value ) {
			$log[ 'tmplog_end_' . $key ] = $value;
		}

		$dbw->insert( 'cn_template_log', $log );
		$log_id = $dbw->insertId();
		return $log_id;
	}

	/**
	 * Sanitizes template search terms by removing non alpha and ensuring space delimiting.
	 *
	 * @param $terms string Search terms to sanitize
	 *
	 * @return string Space delimited string
	 */
	static function sanitizeSearchTerms( $terms ) {
		$retval = ' '; // The space is important... it gets trimmed later

		foreach ( preg_split( '/\s/', $terms ) as $term ) {
			preg_match( '/[0-9a-zA-Z_\-]+/', $term, $matches );
			if ( $matches ) {
				$retval .= $matches[ 0 ];
				$retval .= ' ';
			}
		}

		return trim( $retval );
	}
}
