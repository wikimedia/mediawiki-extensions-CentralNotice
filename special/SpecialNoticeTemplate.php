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
						try {
							Banner::removeTemplate( $template, $this->getUser() );
						} catch ( MWException $ex ) {
							$this->showError( array(
								'centralnotice-template-still-bound',
								$template
							) );
						}
					}
				}

				// Handle translation message update
				$update = $request->getArray( 'updateText' );
				if ( isset ( $update ) ) {
					foreach ( $update as $template => $langs ) {
						$banner = new Banner( $template );
						foreach ( $langs as $lang => $messages ) {
							foreach ( $messages as $field => $translation ) {
								// If we actually have text
								if ( $translation ) {
									$bannerMesage = $banner->getMessageField( $field );
									$bannerMesage->update( $translation, $lang, $user );
								}
							}
						}
					}
				}

				// Handle adding banner
				if ( $method == 'addTemplate' ) {
					$newTemplateName = $request->getText( 'templateName' );
					$newTemplateBody = $request->getText( 'templateBody' );
					$errors = Banner::addTemplate(
						$newTemplateName,
						$newTemplateBody,
						$this->getUser(),
						$request->getBool( 'displayAnon' ),
						$request->getBool( 'displayAccount' ),
						$request->getBool( 'fundraising' ),
						$request->getBool( 'autolink' ),
						$request->getVal( 'landingPages' ),
						$request->getVal( 'mixins' ),
						$request->getArray( 'project_languages', array() )
					);
					if ( $errors ) {
						$this->showError( $errors );
					} else {
						$sub = 'view';
					}
				}

				// Handle editing banner
				if ( $method == 'editTemplate' ) {
					$banner = new Banner( $request->getText( 'template' ) );
					$banner->editTemplate(
						$this->getUser(),
						$request->getText( 'templateBody' ),
						$request->getBool( 'displayAnon' ),
						$request->getBool( 'displayAccount' ),
						$request->getBool( 'fundraising' ),
						$request->getBool( 'autolink' ),
						$request->getVal( 'landingPages' ),
						$request->getVal( 'mixins' ),
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
					$template = Banner::cloneTemplate( $oldTemplate, $newTemplate, $this->getUser() );
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
			$mixins = $request->getVal( 'mixins' );
			$body = $request->getVal( 'templateBody' );
		} else { // Use default values
			$templateName = '';
			$displayAnon = true;
			$displayAccount = true;
			$fundraising = false;
			$autolink = false;
			$landingPages = '';
			$mixins = '';
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

		// Mixins
		$htmlOut .= Xml::tags( 'p', array(),
			Xml::inputLabel(
				$this->msg( 'centralnotice-banner-mixins' )->text(),
				'mixins', 'mixins', 40, $mixins,
				array( 'maxlength' => 255 )
			)
		);

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

			// If there was an error, we'll need to restore the state of the form
		} else {
			$readonly = array( 'readonly' => 'readonly' );
			$disabled = array( 'disabled' => 'disabled' );
		}

		// Get current banner
		$currentTemplate = $request->getText( 'template' );

		$bannerSettings = Banner::getBannerSettings( $currentTemplate );

		if ( $request->wasPosted() && $request->getVal( 'mainform' ) ) {
			$displayAnon = $request->getCheck( 'displayAnon' );
			$displayAccount = $request->getCheck( 'displayAccount' );
			$fundraising = $request->getCheck( 'fundraising' );
			$autolink = $request->getCheck( 'autolink' );
			$landingPages = $request->getVal( 'landingPages' );
			$mixins = $request->getVal( 'mixins' );
			$priorityLangs = $request->getArray( 'project_languages', array() );
			$body = $request->getVal( 'templateBody' );
		} else {
			// Use previously stored values if nothing was posted
			$displayAnon = ( $bannerSettings[ 'anon' ] == 1 );
			$displayAccount = ( $bannerSettings[ 'account' ] == 1 );
			$fundraising = ( $bannerSettings[ 'fundraising' ] == 1 );
			$autolink = ( $bannerSettings[ 'autolink' ] == 1 );
			$landingPages = $bannerSettings[ 'landingpages' ];
			$mixins = $bannerSettings[ 'controller_mixin' ];
			if ( $wgNoticeUseTranslateExtension ) {
				$priorityLangs = $bannerSettings[ 'prioritylangs' ];
			}
		}

		// Get the language to display the banner preview and messages in
		$wpUserLang = $request->getVal( 'wpUserLanguage', $wgLanguageCode );
		$userLangContext = new DerivativeContext( $this->getContext() );
		$userLangContext->setLanguage( $wpUserLang );

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
			$banner = new Banner( $currentTemplate );
			$bannerRenderer = new BannerRenderer( $userLangContext, $banner );
			$htmlOut .= $bannerRenderer->previewFieldSet();

			if ( !isset( $body ) ) {
				$body = $banner->getContent();
			}

			// If there are any message fields in the banner, display translation tools.
			$fields = $banner->extractMessageFields( $body );
			if ( count( $fields ) > 0 ) {
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
					Language::fetchLanguageName( $this->getLanguage()->getCode() ) );
				$htmlOut .= Html::element( 'th', array( 'width' => '40%' ),
					Language::fetchLanguageName( $wpUserLang, $this->getLanguage()->getCode() ) );

				// Table rows
				foreach ( $fields as $field => $count ) {
					$bannerMessage = $banner->getMessageField( $field );

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
						$title = $bannerMessage->getTitle( $wpUserLang );
						$htmlOut .= Xml::tags( 'td', null,
							Linker::link( $title, htmlspecialchars( $field ) )
						);
					}

					$htmlOut .= Html::element( 'td', array(), $count );

					// Native text
					$nativeText = $this->msg( 'centralnotice-message-not-set' )->text();
					$nativeTextExists = $bannerMessage->existsInLang( $this->getLanguage()->getCode() );
					if ( $nativeTextExists ) {
						$nativeText = $bannerMessage->toHtml( $this->getContext() );
					}
					$htmlOut .= Xml::tags( 'td', null,
						Html::element( 'span',
							array(
								'style' => 'font-style:italic;' .
									( !$nativeTextExists ? 'color:silver' : '' )
							),
							$nativeText
						)
					);

					// Foreign text input
					$foreignText = '';
					$foreignTextExists = $bannerMessage->existsInLang( $wpUserLang );
					if ( $foreignTextExists ) {
						$foreignText = $bannerMessage->toHtml( $userLangContext );
					}
					// If we're using the Translate extension to handle translations,
					// don't allow translations to be edited through CentralNotice.
					if ( ( $wgNoticeUseTranslateExtension && $wpUserLang !== $this->getLanguage()->getCode() ) || !$this->editable ) {
						$htmlOut .= Xml::tags( 'td', null, $foreignText );
					} else {
						$htmlOut .= Xml::tags( 'td', null,
							Xml::input(
								"updateText[{$currentTemplate}][{$wpUserLang}][{$field}]",
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

			// Mixins
			$htmlOut .= Xml::tags( 'p', array(),
				Xml::inputLabel(
					$this->msg( 'centralnotice-banner-mixins' )->text(),
					'mixins', 'mixins', 40, $mixins,
					array( 'maxlength' => 255 )
				)
			);

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

				$magicWords = $bannerRenderer->getMagicWords();
				foreach ( $magicWords as &$word ) {
					$word = '{{{' . $word . '}}}';
				}
				$htmlOut .= Xml::tags( 'p', array(), $this->msg( 'centralnotice-edit-template-magicwords', $this->getLanguage()->listToText( $magicWords ) )->text() );

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

		$banner = new Banner( $template );

		// Pull all available text for a banner
		$langs = $banner->getAvailableLanguages();
		$htmlOut = '';

		// Begin View Banner fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Html::element(
			'h2',
			array(),
			$this->msg( 'centralnotice-banner-heading', $template )->text()
		);

		$langContext = new DerivativeContext( $this->getContext() );
		$bannerRenderer = new BannerRenderer( $langContext, $banner );

		foreach ( $langs as $lang ) {
			$langContext->setLanguage( $lang );
			// Link and Preview all available translations
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				$bannerRenderer->previewFieldSet()
			);
		}

		// End View Banner fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$this->getOutput()->addHtml( $htmlOut );
	}
}
