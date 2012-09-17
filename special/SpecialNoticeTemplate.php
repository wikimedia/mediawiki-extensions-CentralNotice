<?php

class SpecialNoticeTemplate extends CentralNotice {
	var $editable, $centralNoticeError;

	function __construct() {
		// Register special page
		SpecialPage::__construct( 'NoticeTemplate' );
	}

	public function isListed() {
		return false;
	}

	/**
	 * Handle different types of page requests
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( 'ext.centralNotice.interface' );

		// Check permissions
		$this->editable = $user->isAllowed( 'centralnotice-admin' );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$out->addWikiMsg( 'centralnotice-summary' );

		// Show header
		$this->printHeader();

		// Begin Banners tab content
		$out->addHTML( Html::openElement( 'div', array( 'id' => 'preferences' ) ) );

		$method = $request->getVal( 'wpMethod' );

		// Handle form submissions
		if ( $this->editable && $request->wasPosted() ) {

			// Check authentication token
			if ( $user->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

				// Handle removing banners
				$toRemove = $request->getArray( 'removeTemplates' );
				if ( isset( $toRemove ) ) {
					// Remove banners in list
					foreach ( $toRemove as $template ) {
						$this->removeTemplate( $template );
					}
				}

				// Handle translation message update
				$update = $request->getArray( 'updateText' );
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
					$newTemplateName = $request->getText( 'templateName' );
					$newTemplateBody = $request->getText( 'templateBody' );
					$success = $this->addTemplate(
						$newTemplateName,
						$newTemplateBody,
						$request->getBool( 'displayAnon' ),
						$request->getBool( 'displayAccount' ),
						$request->getBool( 'fundraising' ),
						$request->getBool( 'autolink' ),
						$request->getVal( 'landingPages' )
					);
					if ( $success ) {
						$sub = 'view';
					}
				}

				// Handle editing banner
				if ( $method == 'editTemplate' ) {
					$this->editTemplate(
						$request->getText( 'template' ),
						$request->getText( 'templateBody' ),
						$request->getBool( 'displayAnon' ),
						$request->getBool( 'displayAccount' ),
						$request->getBool( 'fundraising' ),
						$request->getBool( 'autolink' ),
						$request->getVal( 'landingPages' ),
						$request->getArray( 'project_languages' )
					);
					$sub = 'view';
				}
			} else {
				$this->showError( 'sessionfailure' );
			}

		}

		// Handle viewing of a banner in all languages
		if ( $sub == 'view' && $request->getVal( 'wpUserLanguage' ) == 'all' ) {
			$template = $request->getVal( 'template' );
			$this->showViewAvailable( $template );
			$out->addHTML( Html::closeElement( 'div' ) );
			return;
		}

		// Handle viewing a specific banner
		if ( $sub == 'view' && $request->getText( 'template' ) != '' ) {
			$this->showView();
			$out->addHTML( Html::closeElement( 'div' ) );
			return;
		}

		if ( $this->editable ) {
			// Handle showing "Add a banner" interface
			if ( $sub == 'add' ) {
				$this->showAdd();
				$out->addHTML( Html::closeElement( 'div' ) );
				return;
			}

			// Handle cloning a specific banner
			if ( $sub == 'clone' ) {

				// Check authentication token
				if ( $user->matchEditToken( $request->getVal( 'authtoken' ) ) ) {

					$oldTemplate = $request->getVal( 'oldTemplate' );
					$newTemplate = $request->getVal( 'newTemplate' );
					// We use the returned name in case any special characters had to be removed
					$template = $this->cloneTemplate( $oldTemplate, $newTemplate );
					$out->redirect(
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
		$out->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Show a list of available banners. Newer banners are shown first.
	 */
	function showList() {
		// Sanitize input on search key and split out terms
		$searchTerms = $this->sanitizeSearchTerms( $this->getRequest()->getText( 'tplsearchkey' ) );

		// Get the pager object
		$pager = new TemplatePager( $this, $searchTerms );

		// Begin building HTML
		$htmlOut = '';

		// Begin Manage Banners fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		// Tab header
		$htmlOut .= Html::element( 'h2', array(), $this->msg( 'centralnotice-manage-templates' )->text() );

		// Search box
		$htmlOut .= Html::openElement( 'fieldset', array( 'id' => 'cn-template-searchbox' ) );
		$htmlOut .= Html::element( 'legend', array(), $this->msg( 'centralnotice-filter-template-banner' )->text() );

		$htmlOut .= Html::openElement( 'form', array( 'method' => 'get' ) );

		$htmlOut .= Html::element( 'label', array( 'for' => 'tplsearchkey' ), $this->msg( 'centralnotice-filter-template-prompt' )->text() );
		$htmlOut .= Html::input( 'tplsearchkey', $searchTerms );
		$htmlOut .= Html::element( 'input', array( 'type'=> 'submit', 'value' => $this->msg( 'centralnotice-filter-template-submit' )->text() ) );

		$htmlOut .= Html::closeElement( 'form' );
		$htmlOut .= Html::closeElement( 'fieldset' );

		if ( !$pager->getNumRows() ) {
			$htmlOut .= Html::element( 'p', array(), $this->msg( 'centralnotice-no-templates' )->text() );
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

		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Show "Add a banner" interface
	 */
	function showAdd() {
		global $wgNoticeEnableFundraising, $wgNoticeUseTranslateExtension;

		$request = $this->getRequest();

		// Build HTML
		$htmlOut = '';
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );
		$htmlOut .= Html::openElement( 'form',
			array( 'method' => 'post', 'onsubmit' => 'return validateBannerForm(this)' ) );
		$htmlOut .= Html::element( 'h2', array(), $this->msg( 'centralnotice-add-template' )->text() );
		$htmlOut .= Html::hidden( 'wpMethod', 'addTemplate' );

		// If there was an error, we'll need to restore the state of the form
		if ( $request->wasPosted() ) {
			$templateName = $request->getVal( 'templateName' );
			$displayAnon = $request->getCheck( 'displayAnon' );
			$displayAccount = $request->getCheck( 'displayAccount' );
			$fundraising = $request->getCheck( 'fundraising' );
			$autolink = $request->getCheck( 'autolink' );
			$landingPages = $request->getVal( 'landingPages' );
			$body = $request->getVal( 'templateBody' );
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
		$htmlOut .= Html::openElement( 'p', array() );
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
			$htmlOut .= Html::openElement( 'p', array() );
			$htmlOut .= Xml::check( 'fundraising', $fundraising, array( 'id' => 'fundraising' ) );
			$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-fundraising' )->text(), 'fundraising' );
			$htmlOut .= Html::closeElement( 'p' );

			// Checkbox for whether or not to automatically create landing page link
			$htmlOut .= Html::openElement( 'p', array() );
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

		// Allow setting prioritization of translations
		if ( $wgNoticeUseTranslateExtension ) {
			$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-prioritylangs' )->text() );
			$htmlOut .= Html::element( 'p', array(), $this->msg( 'centralnotice-prioritylangs-explain' )->text() );
			$htmlOut .= $this->languageMultiSelector();
			$htmlOut .= Html::closeElement( 'fieldset' );
		}

		// Begin banner body section
		$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-banner' )->text() );
		$htmlOut .= $this->msg( 'centralnotice-edit-template-summary' )->text();
		$buttons = array();
		$buttons[ ] = '<a href="#" onclick="insertButton(\'close\');return false;">' .
			$this->msg( 'centralnotice-close-button' )->text() . '</a>';
		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'banner-editing-top-hint' ),
			$this->msg( 'centralnotice-insert' )->rawParams( $this->getLanguage()->commaList( $buttons ) )->escaped()
		);

		$htmlOut .= Xml::textarea( 'templateBody', $body, 60, 20 );
		$htmlOut .= Html::closeElement( 'fieldset' );
		$htmlOut .= Html::hidden( 'authtoken', $this->getUser()->getEditToken() );

		// Submit button
		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'cn-buttons' ),
			Xml::submitButton( $this->msg( 'centralnotice-save-banner' )->text() )
		);

		$htmlOut .= Html::closeElement( 'form' );
		$htmlOut .= Html::closeElement( 'fieldset' );

		// Output HTML
		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Extract the raw fields and field names from the banner body source.
	 * @param string $body The body source of the banner
	 * @return array
	 */
	static function extractMessageFields( $body ) {
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
		return $fields;
	}

	/**
	 * View or edit an individual banner
	 */
	private function showView() {
		global $wgLanguageCode, $wgNoticeEnableFundraising, $wgNoticeUseTranslateExtension;

		$lang = $this->getLanguage();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $this->editable ) {
			$readonly = array();
			$disabled = array();
		} else {
			$readonly = array( 'readonly' => 'readonly' );
			$disabled = array( 'disabled' => 'disabled' );
		}

		// Get the language to display the banner preview and messages in
		$wpUserLang = $request->getVal( 'wpUserLanguage', $wgLanguageCode );

		// Get current banner
		$currentTemplate = $request->getText( 'template' );

		$cndb = new CentralNoticeDB();
		$bannerSettings = $cndb->getBannerSettings( $currentTemplate );

		if ( !$bannerSettings ) {
			$this->showError( 'centralnotice-banner-doesnt-exist' );
			return;
		} else {
			// Begin building HTML
			$htmlOut = '';

			// Begin View Banner fieldset
			$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

			$htmlOut .= Html::element( 'h2', array(),
				$this->msg( 'centralnotice-banner-heading', $currentTemplate )->text() );

			// Show preview of banner
			$render = new SpecialBannerLoader();
			$render->language = $wpUserLang;
			try {
				$preview = $render->getHtmlNotice( $request->getText( 'template' ) );
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

			// If there are any message fields in the banner, display translation tools.
			$fields = $this->extractMessageFields( $body );
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
				$languages = Language::fetchLanguageNames( $lang->getCode(), 'all' );
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

					if ( $wgNoticeUseTranslateExtension ) {
						// Create per message link to the translate extension
						$title = SpecialPage::getTitleFor( 'Translate' );
						$htmlOut .= Xml::tags( 'td', null,
							Linker::link( $title, htmlspecialchars( $field ), array(), array(
									'group' => BannerMessageGroup::getTranslateGroupName( $currentTemplate ),
									'task' => 'view'
								)
							)
						);
					} else {
						// Legacy method; which is to edit the page directly
						$title = Title::newFromText( "MediaWiki:{$message}" );
						$htmlOut .= Xml::tags( 'td', null,
							Linker::link( $title, htmlspecialchars( $field ) )
						);
					}

					$htmlOut .= Html::element( 'td', array(), $count );

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
						$foreignText = $this->msg( "Centralnotice-{$currentTemplate}-{$field}" )
							->inLanguage( $wpUserLang )->text();
						$foreignTextExists = true;
					}
					// If we're using the Translate extension to handle translations,
					// don't allow translations to be edited through CentralNotice.
					if ( ( $wgNoticeUseTranslateExtension && $wpUserLang !== 'en' ) || !$this->editable ) {
						$htmlOut .= Xml::tags( 'td', null, $foreignText );
					} else {
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
					}
					$htmlOut .= Html::closeElement( 'tr' );
				}
				$htmlOut .= Html::closeElement( 'table' );

				if ( $this->editable ) {
					$htmlOut .= Html::hidden( 'wpUserLanguage', $wpUserLang );
					$htmlOut .= Html::hidden( 'authtoken', $user->getEditToken() );
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
				list( $lsLabel, $lsSelect ) = Xml::languageSelector( $wpUserLang, true, $lang->getCode() );

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
			if ( $request->wasPosted() && $request->getVal( 'mainform' ) ) {
				$displayAnon = $request->getCheck( 'displayAnon' );
				$displayAccount = $request->getCheck( 'displayAccount' );
				$fundraising = $request->getCheck( 'fundraising' );
				$autolink = $request->getCheck( 'autolink' );
				$landingPages = $request->getVal( 'landingPages' );
				$priorityLangs = $request->getArray( 'project_languages', array() );
				$body = $request->getVal( 'templateBody', $body );
			} else { // Use previously stored values
				$displayAnon = ( $bannerSettings[ 'anon' ] == 1 );
				$displayAccount = ( $bannerSettings[ 'account' ] == 1 );
				$fundraising = ( $bannerSettings[ 'fundraising' ] == 1 );
				$autolink = ( $bannerSettings[ 'autolink' ] == 1 );
				$landingPages = $bannerSettings[ 'landingpages' ];
				if ( $wgNoticeUseTranslateExtension ) {
					$priorityLangs = $bannerSettings[ 'prioritylangs' ];
				}
				// $body default is defined prior to message interface code
			}

			// Show banner settings
			$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-settings' )->text() );
			$htmlOut .= Html::openElement( 'p', array() );
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
				$htmlOut .= Html::openElement( 'p', array() );
				$htmlOut .= Xml::check( 'fundraising', $fundraising,
					wfArrayMerge( $disabled, array( 'id' => 'fundraising' ) ) );
				$htmlOut .= Xml::label( $this->msg( 'centralnotice-banner-fundraising' )->text(),
					'fundraising' );
				$htmlOut .= Html::closeElement( 'p' );

				// Checkbox for whether or not to automatically create landing page link
				$htmlOut .= Html::openElement( 'p', array() );
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

			// Allow setting prioritization of translations
			if ( $wgNoticeUseTranslateExtension ) {
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-prioritylangs' )->text() );
				$htmlOut .= Html::element( 'p', array(), $this->msg( 'centralnotice-prioritylangs-explain' )->text() );
				$htmlOut .= $this->languageMultiSelector( $priorityLangs );
				$htmlOut .= Html::closeElement( 'fieldset' );
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
					$this->msg( 'centralnotice-insert' )->rawParams( $lang->commaList( $buttons ) )->escaped()
				);
			} else {
				$htmlOut .= Xml::fieldset( $this->msg( 'centralnotice-banner' )->text() );
			}
			$htmlOut .= Xml::textarea( 'templateBody', $body, 60, 20, $readonly );
			$htmlOut .= Html::closeElement( 'fieldset' );
			if ( $this->editable ) {
				// Indicate which form was submitted
				$htmlOut .= Html::hidden( 'mainform', 'true' );
				$htmlOut .= Html::hidden( 'authtoken', $user->getEditToken() );
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
				$htmlOut .= Html::hidden( 'authtoken', $user->getEditToken() );
				$htmlOut .= Html::closeElement( 'fieldset' );
				$htmlOut .= Html::closeElement( 'form' );
			}

			// End View Banner fieldset
			$htmlOut .= Html::closeElement( 'fieldset' );

			// Output HTML
			$this->getOutput()->addHTML( $htmlOut );
		}
	}

	/**
	 * Preview all available translations of a banner
	 */
	public function showViewAvailable( $template ) {
		// Testing to see if bumping up the memory limit lets meta preview
		ini_set( 'memory_limit', '120M' );

		// Pull all available text for a banner
		$langs = array_keys( $this->getTranslations( $template ) );
		$htmlOut = '';

		// Begin View Banner fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Html::element(
			'h2',
			array(),
			$this->msg( 'centralnotice-banner-heading', $template )->text()
		);

		foreach ( $langs as $lang ) {
			// Link and Preview all available translations
			$viewPage = $this->getTitle( 'view' );
			$render = new SpecialBannerLoader();
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

		$this->getOutput()->addHtml( $htmlOut );
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
		$content = ContentHandler::makeContent( $translation, $wikiPage->getTitle() );
		$wikiPage->doEditContent( $content, '/* CN admin */', EDIT_FORCE_BOT );
	}

	// @todo Can CentralNotice::getTemplateId() be updated and reused?
	protected function getTemplateId( $templateName ) {
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

	public function getBannerName( $bannerId ) {
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
		global $wgNoticeUseTranslateExtension;

		$id = $this->getTemplateId( $name );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_assignments', 'asn_id', array( 'tmp_id' => $id ), __METHOD__ );

		if ( $dbr->numRows( $res ) > 0 ) {
			$this->showError( 'centralnotice-template-still-bound' );
			return;
		} else {
			// Log the removal of the banner
			$this->logBannerChange( 'removed', $id );

			// Delete banner record from the CentralNotice cn_templates table
			$dbw = wfGetDB( DB_MASTER );
			$dbw->begin();
			$dbw->delete( 'cn_templates',
				array( 'tmp_id' => $id ),
				__METHOD__
			);
			$dbw->commit();

			// Delete the MediaWiki page that contains the banner source
			$article = new Article(
				Title::newFromText( "centralnotice-template-{$name}", NS_MEDIAWIKI )
			);
			$pageId = $article->getPage()->getId();
			$article->doDeleteArticle( 'CentralNotice automated removal' );

			if ( $wgNoticeUseTranslateExtension ) {
				// Remove any revision tags related to the banner
				$this->removeTag( 'banner:translate', $pageId );

				// And the preferred language metadata if it exists
				TranslateMetadata::set(
					BannerMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					false
				);
			}
		}
	}

	/**
	 * Create a new banner
	 *
	 * @param $name             string name of banner
	 * @param $body             string content of banner
	 * @param $displayAnon      integer flag for display to anonymous users
	 * @param $displayAccount   integer flag for display to logged in users
	 * @param $fundraising      integer flag for fundraising banner (optional)
	 * @param $autolink         integer flag for automatically creating landing page links (optional)
	 * @param $landingPages     string list of landing pages (optional)
	 * @param $priorityLangs    array Array of priority languages for the translate extension
	 *
	 * @return bool true or false depending on whether banner was successfully added
	 */
	public function addTemplate( $name, $body, $displayAnon, $displayAccount, $fundraising = 0,
	                             $autolink = 0, $landingPages = '', $priorityLangs = array()
	) {
		global $wgNoticeUseTranslateExtension;

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
			$content = ContentHandler::makeContent( $body, $wikiPage->getTitle() );
			$pageResult = $wikiPage->doEditContent( $content, '/* CN admin */', EDIT_FORCE_BOT );

			if ( $wgNoticeUseTranslateExtension ) {
				// Get the revision and page ID of the page that was created
				$pageResultValue = $pageResult->value;
				$revision = $pageResultValue['revision'];
				$revisionId = $revision->getId();
				$pageId = $revision->getPage();

				// If the banner includes translatable messages, tag it for translation
				$fields = $this->extractMessageFields( $body );
				if ( count( $fields[ 0 ] ) > 0 ) {
					// Tag the banner for translation
					$this->addTag( 'banner:translate', $revisionId, $pageId );
					MessageGroups::clearCache();
					MessageIndexRebuildJob::newJob()->run();
				}

				TranslateMetadata::set(
					BannerMessageGroup::getTranslateGroupName( $name ),
					'prioritylangs',
					implode( ',', $priorityLangs )
				);
			}

			// Log the creation of the banner
			$beginSettings = array();
			$endSettings = array(
				'anon'          => $displayAnon,
				'account'       => $displayAccount,
				'fundraising'   => $fundraising,
				'autolink'      => $autolink,
				'landingpages'  => $landingPages,
				'prioritylangs' => $priorityLangs,
			);
			$this->logBannerChange( 'created', $bannerId, $beginSettings, $endSettings );

			return true;
		}
	}

	/**
	 * Add a revision tag for the banner
	 * @param string $tag The name of the tag
	 * @param integer $revisionId ID of the revision
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @param string $value Value to store for the tag
	 * @throws MWException
	 */
	protected function addTag( $tag, $revisionId, $pageId, $value = null ) {
		$dbw = wfGetDB( DB_MASTER );

		if ( is_object( $revisionId ) ) {
			throw new MWException( 'Got object, excepted id' );
		}

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag ),
			'rt_revision' => $revisionId
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );

		if ( $value !== null ) {
			$conds['rt_value'] = serialize( implode( '|', $value ) );
		}

		$dbw->insert( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Make sure banner is not tagged with specified tag
	 * @param string $tag The name of the tag
	 * @param integer $pageId ID of the MediaWiki page for the banner
	 * @throws MWException
	 */
	protected function removeTag( $tag, $pageId ) {
		$dbw = wfGetDB( DB_MASTER );

		$conds = array(
			'rt_page' => $pageId,
			'rt_type' => RevTag::getType( $tag )
		);
		$dbw->delete( 'revtag', $conds, __METHOD__ );
	}

	/**
	 * Update a banner
	 */
	private function editTemplate( $name, $body, $displayAnon, $displayAccount, $fundraising,
	                               $autolink, $landingPages, $priorityLangs
	) {
		global $wgNoticeUseTranslateExtension;

		if ( $body == '' || $name == '' ) {
			$this->showError( 'centralnotice-null-string' );
			return;
		}

		$cndb = new CentralNoticeDB();
		$initialBannerSettings = $cndb->getBannerSettings( $name, true );

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
			$content = ContentHandler::makeContent( $body, $wikiPage->getTitle() );
			$pageResult = $wikiPage->doEditContent( $content, '', EDIT_FORCE_BOT );

			if ( $wgNoticeUseTranslateExtension ) {
				if ( $priorityLangs ) {
					TranslateMetadata::set(
						BannerMessageGroup::getTranslateGroupName( $name ),
						'prioritylangs',
						implode( ',', $priorityLangs )
					);
				}
			}

			$bannerId = $this->getTemplateId( $name );
			$cndb = new CentralNoticeDB();
			$finalBannerSettings = $cndb->getBannerSettings( $name, true );

			if ( $wgNoticeUseTranslateExtension && $pageResult->value['revision'] ) {
				// Get the revision and page ID of the page that was created (if it was actually
				// edited this session)
				$pageResultValue = $pageResult->value;
				$revision = $pageResultValue['revision'];
				$revisionId = $revision->getId();
				$pageId = $revision->getPage();

				// If the banner includes translatable messages, tag it for translation
				$fields = $this->extractMessageFields( $body );
				if ( count( $fields[ 0 ] ) > 0 ) {
					// Tag the banner for translation
					$this->addTag( 'banner:translate', $revisionId, $pageId );
				} else {
					// Make sure banner is not tagged for translation
					$this->removeTag( 'banner:translate', $pageId );
				}
				MessageGroups::clearCache();
				MessageIndexRebuildJob::newJob()->insert();
			}

			// If there are any difference between the old settings and the new settings, log them.
			$changed = false;
			foreach ( $finalBannerSettings as $key => $value ) {
				if ( $finalBannerSettings[$key] != $initialBannerSettings[$key] ) {
					$changed = true;
				}
			}

			if ( $changed ) {
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
		$allLangs = array_keys( Language::fetchLanguageNames( null, 'all' ) );

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
		$dbw = wfGetDB( DB_MASTER );

		$log = array(
			'tmplog_timestamp'     => $dbw->timestamp(),
			'tmplog_user_id'       => $this->getUser()->getId(),
			'tmplog_action'        => $action,
			'tmplog_template_id'   => $bannerId,
			'tmplog_template_name' => $this->getBannerName( $bannerId )
		);

		foreach ( $beginSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJson::encode( $value );
			}

			$log[ 'tmplog_begin_' . $key ] = $value;
		}
		foreach ( $endSettings as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = FormatJSON::encode( $value );
			}

			$log[ 'tmplog_end_' . $key ] = $value;
		}

		$dbw->insert( 'cn_template_log', $log );
		$log_id = $dbw->insertId();
		return $log_id;
	}
}
