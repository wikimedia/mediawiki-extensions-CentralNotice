<?php

/**
 * Special page for management of CentralNotice banners
 */
class SpecialCentralNoticeBanners extends CentralNotice {
	/** @var string Name of the banner we're currently editing */
	protected $bannerName = '';

	/** @var Banner Banner object we're currently editing */
	protected $banner = null;

	/** @var string Filter to apply to the banner search when generating the list */
	protected $bannerFilterString = '';

	/** @var string Language code to render preview materials in */
	protected $bannerLanguagePreview;

	/** @var bool If true, form execution must stop and the page will be redirected */
	protected $bannerFormRedirectRequired = false;

	function __construct() {
		SpecialPage::__construct( 'CentralNoticeBanners' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Whether this special page is listed in Special:SpecialPages
	 * @return Bool
	 */
	public function isListed() {
		return false;
	}

	/**
	 * Handle all the different types of page requests determined by the first subpage
	 * level after the special page title. If needed, the second subpage level is the
	 * banner name.
	 *
	 * Valid actions are:
	 *    (none)    - Display a list of banners
	 *    edit      - Edits an existing banner
	 *
	 * TODO Preview action (for previewing translated messages) is broken. See T105558
	 * TODO Change method of indicating action to something more standard.
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		// Do all the common setup
		$this->setHeaders();
		$this->editable = $this->getUser()->isAllowed( 'centralnotice-admin' );

		// Make sure we have a session
		$this->getRequest()->getSession()->persist();

		// Load things that may have been serialized into the session
		$this->bannerLanguagePreview = $this->getCNSessionVar(
			'bannerLanguagePreview',
			$this->getLanguage()->getCode()
		);

		// User settable text for some custom message, like usage instructions
		$this->getOutput()->setPageTitle( $this->msg( 'noticetemplate' ) );
		$this->getOutput()->addWikiMsg( 'centralnotice-summary' );

		// Now figure out what to display
		// TODO Use only params instead of subpage to indicate action
		$parts = explode( '/', $subPage );
		$action = ( isset( $parts[0] ) && $parts[0] ) ? $parts[0] : 'list';
		$this->bannerName = array_key_exists( 1, $parts ) ? $parts[1] : null;

		switch ( strtolower( $action ) ) {
			case 'list':
				// Display the list of banners
				$this->showBannerList();
				break;

			case 'edit':
				// Display the banner editor form
				if ( $this->bannerName ) {
					$this->showBannerEditor();
				} else {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				}
				break;

			// TODO Feature is broken. Remove or fix? See T105558
			case 'preview':
				// Preview all available translations
				// Display the banner editor form
				if ( $this->bannerName ) {
					$this->showAllLanguages();
				} else {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				}
				break;

			default:
				// Something went wrong; display error page
				throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				break;
		}
	}

	/**
	 * Process the 'banner list' form and display a new one.
	 */
	protected function showBannerList() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'centralnotice-manage-templates' ) );
		$out->addModules( 'ext.centralNotice.adminUi.bannerManager' );

		// Process the form that we sent out
		$formDescriptor = $this->generateBannerListForm( $this->bannerFilterString );
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( [ $this, 'processBannerList' ] );
		$htmlForm->loadData();
		$formResult = $htmlForm->trySubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Re-generate the form in case they changed the filter string, archived something,
		// deleted something, etc...
		$formDescriptor = $this->generateBannerListForm( $this->bannerFilterString );
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext() );

		$htmlForm->setId( 'cn-banner-manager' )->
			suppressDefaultSubmit()->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	/**
	 * Generates the HTMLForm entities for the 'banner list' form.
	 *
	 * @param string $filter Filter to use for the banner list
	 *
	 * @return array of HTMLForm entities
	 */
	protected function generateBannerListForm( $filter = '' ) {
		// --- Create the banner search form --- //

		// Note: filter is normally set via JS, not form submission. But we
		// leave the info in the submitted form, in any case.
		$formDescriptor = [
			'bannerNameFilter' => [
				'section' => 'header/banner-search',
				'class' => 'HTMLTextField',
				'placeholder-message' => 'centralnotice-filter-template-prompt',
				'filter-callback' => [ $this, 'sanitizeSearchTerms' ],
				'default' => $filter,
			],
			'filterApply' => [
				'section' => 'header/banner-search',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'centralnotice-filter-template-submit' )->text(),
			]
		];

		// --- Create the management options --- //
		$formDescriptor += [
			'selectAllBanners' => [
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLCheckField',
				'disabled' => !$this->editable,
			],
			/* TODO: Actually enable this feature
			'archiveSelectedBanners' => array(
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedBanners' => [
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'centralnotice-remove' )->text(),
				'disabled' => !$this->editable,
			],
			'addNewBanner' => [
				'section' => 'header/one-off',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'centralnotice-add-template' )->text(),
				'disabled' => !$this->editable,
			],
			'newBannerName' => [
				'section' => 'addBanner',
				'class' => 'HTMLTextField',
				'disabled' => !$this->editable,
				'label' => wfMessage( 'centralnotice-banner-name' )->text(),
			],
			'newBannerEditSummary' => [
				'section' => 'addBanner',
				'class' => 'HTMLTextField',
				'label-message' => 'centralnotice-change-summary-label',
				'placeholder-message' => 'centralnotice-change-summary-action-prompt',
				'disabled' => !$this->editable,
				'filter-callback' => [ $this, 'truncateSummaryField' ]
			],
			'removeBannerEditSummary' => [
				'section' => 'removeBanner',
				'class' => 'HTMLTextField',
				'label-message' => 'centralnotice-change-summary-label',
				'placeholder-message' => 'centralnotice-change-summary-action-prompt',
				'disabled' => !$this->editable,
				'filter-callback' => [ $this, 'truncateSummaryField' ]
			],
			'action' => [
				'type' => 'hidden',
			]
		];

		// --- Add all the banners via the fancy pager object ---
		$pager = new CNBannerPager(
			$this,
			'banner-list',
			[
				'applyTo' => [
					'section' => 'banner-list',
					'class' => 'HTMLCheckField',
					'cssclass' => 'cn-bannerlist-check-applyto',
				]
			],
			[],
			$filter,
			$this->editable
		);
		$formDescriptor[ 'topPagerNav' ] = $pager->getNavigationBar();
		$formDescriptor += $pager->getBody();
		$formDescriptor[ 'bottomPagerNav' ] = $pager->getNavigationBar();

		return $formDescriptor;
	}

	/**
	 * Callback function from the showBannerList() form that actually processes the
	 * response data.
	 *
	 * @param array $formData
	 *
	 * @return null|string|array
	 */
	public function processBannerList( $formData ) {
		$this->setFilterFromUrl();

		if ( $formData[ 'action' ] && $this->editable ) {
			switch ( strtolower( $formData[ 'action' ] ) ) {
				case 'create':
					// Attempt to create a new banner and redirect; we validate here because it's
					// a hidden field and that doesn't work so well with the form
					if ( !Banner::isValidBannerName( $formData[ 'newBannerName' ] ) ) {
						return wfMessage( 'centralnotice-banner-name-error' )->parse();
					} else {
						$this->bannerName = $formData[ 'newBannerName' ];
					}

					if ( Banner::fromName( $this->bannerName )->exists() ) {
						return wfMessage( 'centralnotice-template-exists' )->parse();
					} else {
						$retval = Banner::addTemplate(
							$this->bannerName,
							"<!-- Empty banner -->",
							$this->getUser(),
							false,
							false,
							// Default values of a zillion parameters...
							false, [], [], null,
							$formData['newBannerEditSummary']
						);

						if ( $retval ) {
							// Something failed; display error to user
							return wfMessage( $retval )->parse();
						} else {
							$this->getOutput()->redirect(
								SpecialPage::getTitleFor(
									'CentralNoticeBanners', "edit/{$this->bannerName}"
								)->getFullURL()
							);
							$this->bannerFormRedirectRequired = true;
						}
					}
					break;

				case 'archive':
					return 'Archiving not yet implemented!';
					break;

				case 'remove':
					$summary = $formData['removeBannerEditSummary'];
					$failed = [];
					foreach ( $formData as $element => $value ) {
						$parts = explode( '-', $element, 2 );
						if ( ( $parts[0] === 'applyTo' ) && ( $value === true ) ) {
							try {

								Banner::removeTemplate(
									$parts[1], $this->getUser(), $summary );

							} catch ( Exception $ex ) {
								$failed[] = $parts[1];
							}
						}
					}
					if ( $failed ) {
						return 'some banners were not deleted';

					} else {

						// Go back to the special banners page, including the
						// same filter that was already set in the URL.
						$this->getOutput()->redirect(
							SpecialPage::getTitleFor( 'CentralNoticeBanners' )
							->getFullURL( $this->getFilterUrlParamAsArray() ) );

						$this->bannerFormRedirectRequired = true;
					}
					break;
			}
		} elseif ( $formData[ 'action' ] ) {
			// Oh noes! The l33t hakorz are here...
			return wfMessage( 'centralnotice-generic-error' )->parse();
		}

		return null;
	}

	/**
	 * Use a URL parameter to set the filter string for the banner list.
	 */
	protected function setFilterFromUrl() {
		// This is the normal param on visible URLs.
		$filterParam = $this->getRequest()->getVal( 'filter', null );

		// If the form was posted the filter parameter'll have a different name.
		if ( $filterParam === null ) {
			$filterParam =
				$this->getRequest()->getVal( 'wpbannerNameFilter', null );
		}

		// Clean, clean...
		if ( $filterParam !== null ) {
			$this->bannerFilterString
				= static::sanitizeSearchTerms( $filterParam );
		}
	}

	/**
	 * Return an array for use in constructing a URL query part with or without
	 * a filter parameter, as required.
	 *
	 * @return array
	 */
	public function getFilterUrlParamAsArray() {
		return $this->bannerFilterString ?
			[ 'filter' => $this->bannerFilterString ] : [];
	}

	/**
	 * Returns array of navigation links to banner preview URL and
	 * edit link to the banner's wikipage if the user is allowed.
	 *
	 * @return array
	 */
	private function getBannerPreviewEditLinks() {
		$links = [
			Linker::linkKnown(
				SpecialPage::getTitleFor( 'Randompage' ),
				$this->msg( 'centralnotice-live-preview' )->escaped(),
				[ 'class' => 'cn-banner-list-element-label-text' ],
				[
					'banner' => $this->bannerName,
					'uselang' => $this->bannerLanguagePreview,
					'force' => '1',
				]
			)
		];

		$bannerObj = Banner::fromName( $this->bannerName );
		$bannerTitle = $bannerObj->getTitle();
		// $bannerTitle can be null sometimes
		if ( $bannerTitle && $this->getUser()->isAllowed( 'editinterface' ) ) {
			$links[] = Linker::link(
				$bannerTitle,
				$this->msg( 'centralnotice-banner-edit-onwiki' )->escaped(),
				[ 'class' => 'cn-banner-list-element-label-text' ],
				[ 'action' => 'edit' ]
				);
			$links[] = Linker::link(
				$bannerTitle,
				$this->msg( 'centralnotice-banner-history' )->escaped(),
				[ 'class' => 'cn-banner-list-element-label-text' ],
				[ 'action' => 'history' ]
				);
		}
		return $links;
	}

	/**
	 * Display the banner editor and process edits
	 */
	protected function showBannerEditor() {
		global $wgUseSquid;

		$out = $this->getOutput();
		$out->addModules( 'ext.centralNotice.adminUi.bannerEditor' );
		$this->addHelpLink(
			'//meta.wikimedia.org/wiki/Special:MyLanguage/Help:CentralNotice',
			true
		);

		if ( !Banner::isValidBannerName( $this->bannerName ) ) {
			throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
		}
		$out->setPageTitle( $this->bannerName );
		$out->setSubtitle(
			$this->getLanguage()->pipeList( $this->getBannerPreviewEditLinks() )
		);

		// Generate the form
		$formDescriptor = $this->generateBannerEditForm();

		// Now begin form processing
		$htmlForm = new CentralNoticeHtmlForm(
			$formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditBanner' ] );
		$htmlForm->loadData();

		$formResult = $htmlForm->tryAuthorizedSubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Recreate the form because something could have changed
		$formDescriptor = $this->generateBannerEditForm();

		$htmlForm = new CentralNoticeHtmlForm(
			$formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditBanner' ] )->setId( 'cn-banner-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()->
			setId( 'cn-banner-editor' )->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );

		// Controls to purge banner loader URLs from CDN caches for a given language.
		if ( $wgUseSquid ) {
			$out->addHTML( $this->generateCdnPurgeSection() );
		}

		$out->addHTML( Xml::element( 'h2',
			[ 'class' => 'cn-special-section' ],
			$this->msg( 'centralnotice-campaigns-using-banner' )->text() ) );

		$pager = new CNCampaignPager( $this, false, $this->banner->getId() );
		$out->addModules( 'ext.centralNotice.adminUi.campaignPager' );
		$out->addHTML( $pager->getBody() );
		$out->addHTML( $pager->getNavigationBar() );
	}

	protected function generateBannerEditForm() {
		global $wgCentralNoticeBannerMixins, $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		array_walk(
			$languages,
			function ( &$val, $index ) {
				$val = "$index - $val";
			}
		);
		$languages = array_flip( $languages );

		$banner = Banner::fromName( $this->bannerName );
		if ( !$banner->exists() ) {
			throw new ErrorPageError( 'centralnotice-banner-not-found-title',
				'centralnotice-banner-not-found-contents' );
		}
		$bannerSettings = $banner->getBannerSettings( $this->bannerName, true );

		$formDescriptor = [];

		/* --- Banner Preview Section --- */
		// FIXME Unused? See T161907
		$formDescriptor[ 'preview' ] = [
			'section' => 'preview',
			'class' => 'HTMLCentralNoticeBanner',
			'banner' => $this->bannerName,
			'language' => $this->bannerLanguagePreview,
		];

		/* --- Banner Settings --- */
		$formDescriptor['banner-class'] = [
			'section' => 'settings',
			'type' => 'selectorother',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-class',
			'help-message' => 'centralnotice-banner-class-desc',
			'options' => Banner::getAllUsedCategories(),
			'size' => 30,
			'maxlength' => 255,
			'default' => $banner->getCategory(),
		];

		$selected = [];
		if ( $bannerSettings[ 'anon' ] === 1 ) {
			$selected[] = 'anonymous';
		}
		if ( $bannerSettings[ 'account' ] === 1 ) {
			$selected[] = 'registered';
		}
		$formDescriptor[ 'display-to' ] = [
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-display',
			'options' => [
				$this->msg( 'centralnotice-banner-logged-in' )->escaped() => 'registered',
				$this->msg( 'centralnotice-banner-anonymous' )->escaped() => 'anonymous'
			],
			'default' => $selected,
			'cssclass' => 'separate-form-element',
		];

		$assignedDevices = array_values(
			CNDeviceTarget::getDevicesAssociatedWithBanner( $banner->getId() )
		);
		$availableDevices = [];
		foreach ( CNDeviceTarget::getAvailableDevices() as $k => $value ) {
			$header = htmlspecialchars( $value[ 'header' ] );
			$label = $this->getOutput()->parseInline( $value[ 'label' ] );
			$availableDevices[ "($header) $label" ] = $header;
		}
		$formDescriptor[ 'device-classes' ] = [
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-display-on',
			'options' => $availableDevices,
			'default' => $assignedDevices,
			'cssclass' => 'separate-form-element',
		];

		$mixinNames = array_keys( $wgCentralNoticeBannerMixins );
		$availableMixins = array_combine( $mixinNames, $mixinNames );
		$selectedMixins = array_keys( $banner->getMixins() );
		$formDescriptor['mixins'] = [
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-mixins',
			'help-message' => 'centralnotice-banner-mixins-help',
			'cssclass' => 'separate-form-element',
			'options' => $availableMixins,
			'default' => $selectedMixins,
		];

		/* --- Translatable Messages Section --- */
		$messages = $banner->getMessageFieldsFromCache();

		if ( $messages ) {
			// Only show this part of the form if messages exist

			$formDescriptor[ 'translate-language' ] = [
				'section' => 'banner-messages',
				'class' => 'LanguageSelectHeaderElement',
				'label-message' => 'centralnotice-language',
				'options' => $languages,
				'default' => $this->bannerLanguagePreview,
				'cssclass' => 'separate-form-element',
			];

			$messageReadOnly = false;
			if ( $wgNoticeUseTranslateExtension &&
				( $this->bannerLanguagePreview !== $wgLanguageCode )
			) {
				$messageReadOnly = true;
			}
			foreach ( $messages as $messageName => $count ) {
				if ( $wgNoticeUseTranslateExtension ) {
					// Create per message link to the translate extension
					$title = SpecialPage::getTitleFor( 'Translate' );
					$label = Xml::tags( 'td', null,
						Linker::link(
							$title,
							htmlspecialchars( $messageName ),
							[],
							[
								'group' => BannerMessageGroup::getTranslateGroupName(
									$banner->getName()
								),
								'task' => 'view'
							]
						)
					);
				} else {
					$label = htmlspecialchars( $messageName );
				}

				$formDescriptor[ "message-$messageName" ] = [
					'section' => 'banner-messages',
					'class' => 'HTMLCentralNoticeBannerMessage',
					'label-raw' => $label,
					'banner' => $this->bannerName,
					'message' => $messageName,
					'language' => $this->bannerLanguagePreview,
					'cssclass' => 'separate-form-element',
				];

				if ( !$this->editable || $messageReadOnly ) {
					$formDescriptor[ "message-$messageName" ][ 'readonly' ] = true;
				}
			}

			if ( $wgNoticeUseTranslateExtension ) {
				$formDescriptor[ 'priority-langs' ] = [
					'section' => 'banner-messages',
					'class' => 'HTMLLargeMultiSelectField',
					'disabled' => !$this->editable,
					'label-message' => 'centralnotice-prioritylangs',
					'options' => $languages,
					'default' => $bannerSettings[ 'prioritylangs' ],
					'help-message' => 'centralnotice-prioritylangs-explain',
					'cssclass' => 'separate-form-element cn-multiselect',
				];
			}

			$liveMessageNames = $banner->getAvailableLanguages();
			if ( $liveMessageNames ) {
				$formDescriptor[ 'approved-languages' ] = [
					'section' => 'banner-messages',
					'class' => 'HTMLInfoField',
					'disabled' => !$this->editable,
					'label-raw' => Linker::link(
							$this->getPageTitle( "preview/{$this->bannerName}" ),
							$this->msg( 'centralnotice-preview-all-template-translations' )->escaped()
						),
					'default' => implode( ', ', $liveMessageNames ),
					'cssclass' => 'separate-form-element',
				];
			}

			if ( $wgNoticeUseTranslateExtension && BannerMessageGroup::isUsingGroupReview() ) {
				$readyStateLangs = BannerMessageGroup::getLanguagesInState(
					$this->bannerName,
					'ready'
				);

				if ( $readyStateLangs ) {
					$formDescriptor[ 'pending-languages' ] = [
						'section' => 'banner-messages',
						'class' => 'HTMLInfoField',
						'disabled' => !$this->editable,
						'label-message' => 'centralnotice-messages-pending-approval',
						'default' => implode( ', ', $readyStateLangs ),
						'cssclass' => 'separate-form-element',
					];
				}
			}
		}

		/* -- The banner editor -- */
		$formDescriptor[ 'banner-magic-words' ] = [
			'section' => 'edit-template',
			'class' => 'HTMLInfoField',
			'default' => Html::rawElement(
				'div',
				[ 'class' => 'separate-form-element' ],
				$this->msg( 'centralnotice-edit-template-summary' )->parse() ),
			'rawrow' => true,
		];

		$renderer = new BannerRenderer( $this->getContext(), $banner );
		$magicWords = $renderer->getMagicWords();
		foreach ( $magicWords as &$word ) {
			$word = '{{{' . $word . '}}}';
		}
		$formDescriptor[ 'banner-mixin-words' ] = [
			'section' => 'edit-template',
			'type' => 'info',
			'default' => $this->msg(
					'centralnotice-edit-template-magicwords',
					wfEscapeWikiText( $this->getLanguage()->listToText( $magicWords ) )
				)->parse(),
			'rawrow' => true,
		];

		$buttons = [];
		// TODO: Fix this gawdawful method of inserting the close button
		$buttons[] =
			'<a href="#" onclick="mw.centralNotice.adminUi.bannerEditor.insertButton(\'close\');' .
				'return false;">' . $this->msg( 'centralnotice-close-button' )->escaped() . '</a>';
		$formDescriptor[ 'banner-insert-button' ] = [
			'section' => 'edit-template',
			'class' => 'HTMLInfoField',
			'rawrow' => true,
			'default' => Html::rawElement(
				'div',
				[ 'class' => 'banner-editing-top-hint separate-form-element' ],
				$this->msg( 'centralnotice-insert' )->
					rawParams( $this->getLanguage()->commaList( $buttons ) )->
					escaped() ),
		];

		$formDescriptor[ 'banner-body' ] = [
			'section' => 'edit-template',
			'type' => 'textarea',
			'readonly' => !$this->editable,
			'hidelabel' => true,
			'placeholder' => '<!-- blank banner -->',
			'default' => $banner->getBodyContent(),
			'cssclass' => 'separate-form-element'
		];

		$links = [];
		foreach ( $banner->getIncludedTemplates() as $titleObj ) {
			$links[] = Linker::link( $titleObj );
		}
		if ( $links ) {
			$formDescriptor[ 'links' ] = [
				'section' => 'edit-template',
				'type' => 'info',
				'label-message' => 'centralnotice-templates-included',
				'default' => implode( '<br />', $links ),
				'raw' => true
			];
		}

		/* --- Form bottom options --- */
		$formDescriptor[ 'summary' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLTextField',
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder' => wfMessage( 'centralnotice-change-summary-prompt' ),
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'save-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLSubmitField',
			'default' => $this->msg( 'centralnotice-save-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		];

		$formDescriptor[ 'clone-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-clone' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		];

		/* TODO: Add this back in when we can actually support it
		$formDescriptor[ 'archive-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-archive-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);
		*/

		$formDescriptor[ 'delete-button' ] = [
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-delete-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		];

		/* --- Hidden fields and such --- */
		$formDescriptor[ 'cloneName' ] = [
			'section' => 'clone-banner',
			'type' => 'text',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-clone-name',
		];

		$formDescriptor[ 'cloneEditSummary' ] = [
			'section' => 'clone-banner',
			'class' => 'HTMLTextField',
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder' => wfMessage( 'centralnotice-change-summary-action-prompt' ),
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'deleteEditSummary' ] = [
			'section' => 'delete-banner',
			'class' => 'HTMLTextField',
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder-message' => 'centralnotice-change-summary-action-prompt',
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'action' ] = [
			'section' => 'form-actions',
			'type' => 'hidden',
			// The default is save so that we can still save the banner/form if the banner
			// preview has seriously borked JS. Maybe one day we'll be able to get Caja up
			// and working and not have this issue.
			'default' => 'save',
		];

		// Save the banner object in an instance variable
		$this->banner = $banner;

		return $formDescriptor;
	}

	/**
	 * Generate a string with the HTML for controls to request a front-end (CDN) cache
	 * purge of banner content for a language.
	 *
	 * @return string
	 */
	protected function generateCdnPurgeSection() {
		$purgeControls = Xml::element( 'h2',
			[ 'class' => 'cn-special-section' ],
			$this->msg( 'centralnotice-banner-cdn-controls' )->text() );

		$purgeControls .= Html::openElement( 'fieldset', [ 'class' => 'prefsection' ] );

		$purgeControls .= Html::openElement( 'label' );
		$purgeControls .=
			$this->msg( 'centralnotice-banner-cdn-label' )->escaped() . ' ';

		$disabledAttr = $this->editable ? [] : [ 'disabled' => true ];

		$purgeControls .= Html::openElement( 'select',
			$disabledAttr + [ 'id' => 'cn-cdn-cache-language' ] );

		// Retrieve the list of languages in user's language
		// FIXME Similar code in SpecialBannerAllocation::execute(), maybe switch
		// to language selector?
		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		ksort( $languages );

		foreach ( $languages as $code => $name ) {
			$purgeControls .= Xml::option(
				$this->msg( 'centralnotice-language-listing', $code, $name )->text(),
				$code );
		}

		$purgeControls .= Html::closeElement( 'select' );
		$purgeControls .= Html::closeElement( 'label' );

		$purgeControls .= ' ' . Html::openElement( 'button', $disabledAttr + [
			'id' => 'cn-cdn-cache-purge',
			'data-banner-name' => $this->bannerName
		] );

		$purgeControls .=
			$this->msg( 'centralnotice-banner-cdn-button' )->escaped();

		$purgeControls .= Html::closeElement( 'button' );

		$purgeControls .= Html::element(
			'div',
			[ 'class' => 'htmlform-help' ],
			$this->msg( 'centralnotice-banner-cdn-help' )->text()
		);

		$purgeControls .= Html::closeElement( 'fieldset' );

		return $purgeControls;
	}

	public function processEditBanner( $formData ) {
		// First things first! Figure out what the heck we're actually doing!
		switch ( $formData[ 'action' ] ) {
			case 'update-lang':
				$newLanguage = $formData[ 'translate-language' ];
				$this->setCNSessionVar( 'bannerLanguagePreview', $newLanguage );
				$this->bannerLanguagePreview = $newLanguage;
				break;

			case 'delete':
				if ( !$this->editable ) {
					return null;
				}
				try {
					Banner::removeTemplate(
						$this->bannerName, $this->getUser(),
						$formData[ 'deleteEditSummary' ] );

					$this->getOutput()->redirect( $this->getPageTitle( '' )->getCanonicalURL() );
					$this->bannerFormRedirectRequired = true;
				} catch ( Exception $ex ) {
					return htmlspecialchars( $ex->getMessage() ) . " <br /> " .
						$this->msg( 'centralnotice-template-still-bound', $this->bannerName )->parse();
				}
				break;

			case 'archive':
				if ( !$this->editable ) {
					return null;
				}
				return 'Archiving currently does not work';
				break;

			case 'clone':
				if ( !$this->editable ) {
					return null;
				}
				$newBannerName = $formData[ 'cloneName' ];

				Banner::fromName( $this->bannerName )->cloneBanner(
					$newBannerName, $this->getUser(),
					$formData[ 'cloneEditSummary' ]
				);

				$this->getOutput()->redirect(
					$this->getPageTitle( "Edit/$newBannerName" )->getCanonicalURL()
				);
				$this->bannerFormRedirectRequired = true;
				break;

			case 'save':
				if ( !$this->editable ) {
					return null;
				}

				$ret = $this->processSaveBannerAction( $formData );

				// Clear the edit summary field in the request so the form
				// doesn't re-display the same value. Note: this is a hack :(
				$this->getRequest()->setVal( 'wpsummary', '' );

				return $ret;
				break;

			default:
				// Nothing was requested, so do nothing
				break;
		}
	}

	protected function processSaveBannerAction( $formData ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$banner = Banner::fromName( $this->bannerName );
		$summary = $formData['summary'];

		/* --- Update the translations --- */
		// But only if we aren't using translate or if the preview language is the content language
		if ( !$wgNoticeUseTranslateExtension || $this->bannerLanguagePreview === $wgLanguageCode ) {
			foreach ( $formData as $key => $value ) {
				if ( strpos( $key, 'message-' ) === 0 ) {
					$messageName = substr( $key, strlen( 'message-' ) );
					$bannerMessage = $banner->getMessageField( $messageName );

					$bannerMessage->update(
						$value, $this->bannerLanguagePreview, $this->getUser(),
						$summary );
				}
			}
		}

		/* --- Banner settings --- */
		if ( array_key_exists( 'priority-langs', $formData ) ) {
			$prioLang = $formData[ 'priority-langs' ];
			if ( !is_array( $prioLang ) ) {
				$prioLang = [ $prioLang ];
			}
		} else {
			$prioLang = [];
		}

		$banner->setAllocation(
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'registered', $formData[ 'display-to' ] )
		);
		$banner->setCategory( $formData[ 'banner-class' ] );
		$banner->setDevices( $formData[ 'device-classes' ] );
		$banner->setPriorityLanguages( $prioLang );
		$banner->setBodyContent( $formData[ 'banner-body' ] );

		$banner->setMixins( $formData['mixins'] );
		$banner->save( $this->getUser(), $summary );

		// Deferred update to purge CDN caches for banner content (for user's lang)
		DeferredUpdates::addUpdate(
			new CdnCacheUpdateBannerLoader( $this->getLanguage()->getCode(), $banner ),
			DeferredUpdates::POSTSEND
		);

		return null;
	}

	/**
	 * Preview all available translations of a banner
	 */
	protected function showAllLanguages() {
		$out = $this->getOutput();

		if ( !Banner::isValidBannerName( $this->bannerName ) ) {
			$out->addHTML(
				Xml::element( 'div', [ 'class' => 'error' ],
					wfMessage( 'centralnotice-generic-error' )->text() )
			);
			return;
		}
		$out->setPageTitle( $this->bannerName );

		// Large amounts of memory apparently required to do this
		ini_set( 'memory_limit', '120M' );

		$banner = Banner::fromName( $this->bannerName );

		// Pull all available text for a banner
		$langs = $banner->getAvailableLanguages();
		$htmlOut = '';

		$langContext = new DerivativeContext( $this->getContext() );

		foreach ( $langs as $lang ) {
			$langContext->setLanguage( $lang );
			$bannerRenderer = new BannerRenderer( $langContext, $banner, 'test' );

			// Link and Preview all available translations
			$htmlOut .= Xml::tags(
				'td',
				[ 'valign' => 'top' ],
				$bannerRenderer->previewFieldSet()
			);
		}

		$this->getOutput()->addHTML( $htmlOut );
	}
}
