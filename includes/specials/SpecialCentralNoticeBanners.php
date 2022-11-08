<?php

/**
 * Special page for management of CentralNotice banners
 */
class SpecialCentralNoticeBanners extends CentralNotice {
	/** @var string Name of the banner we're currently editing */
	protected $bannerName = '';

	/** @var Banner|null Banner object we're currently editing */
	protected $banner = null;

	/** @var string Filter to apply to the banner search when generating the list */
	protected $bannerFilterString = '';

	/** @var string Language code to render preview materials in */
	protected $bannerLanguagePreview;

	/** @var bool If true, form execution must stop and the page will be redirected */
	protected $bannerFormRedirectRequired = false;

	/** @var array|null Names of the banners that are marked as templates */
	protected $templateBannerNames = null;

	public function __construct() {
		SpecialPage::__construct( 'CentralNoticeBanners' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Whether this special page is listed in Special:SpecialPages
	 * @return false
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
	 * TODO: Use the "?action=" convention rather than parsing the URL subpath.
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

		// Allow users to add a custom nav bar (T138284)
		$navBar = $this->msg( 'centralnotice-navbar' )->inContentLanguage();
		if ( !$navBar->isDisabled() ) {
			$this->getOutput()->addHTML( $navBar->parseAsBlock() );
		}
		$this->getOutput()->addWikiMsg( 'centralnotice-summary' );

		// Now figure out what to display
		// TODO Use only params instead of subpage to indicate action
		$parts = explode( '/', $subPage );
		$action = $parts[0] ?: 'list';
		$this->bannerName = $parts[1] ?? '';

		switch ( strtolower( $action ) ) {
			case 'list':
				// Display the list of banners
				$this->showBannerList();
				break;

			case 'edit':
				if ( $this->bannerName ) {
					$this->ensureBanner( $this->bannerName );
					$this->showBannerEditor();
				} else {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-banner-name-error' );
				}
				break;

			default:
				// Something went wrong; display error page
				throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
		}
	}

	/**
	 * Ensure that $this->banner is assigned.
	 *
	 * @param string $bannerName
	 * @throws ErrorPageError
	 */
	private function ensureBanner( $bannerName ) {
		if ( !Banner::isValidBannerName( $bannerName ) ) {
			throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
		}

		if ( $this->banner ) {
			return;
		}

		$this->banner = Banner::fromName( $this->bannerName );

		if ( !$this->banner->exists() ) {
			throw new ErrorPageError( 'centralnotice-banner-not-found-title',
				'centralnotice-banner-not-found-contents' );
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
		$htmlForm->setSubmitCallback( [ $this, 'processBannerList' ] )
			->prepareForm();
		$formResult = $htmlForm->trySubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Re-generate the form in case they changed the filter string, archived something,
		// deleted something, etc...
		$formDescriptor = $this->generateBannerListForm( $this->bannerFilterString );
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext() );

		$htmlForm->setId( 'cn-banner-manager' )
			->suppressDefaultSubmit()
			->setDisplayFormat( 'div' )
			->prepareForm()
			->displayForm( $formResult );
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
				'class' => HTMLTextField::class,
				'placeholder-message' => 'centralnotice-filter-template-prompt',
				'filter-callback' => [ $this, 'sanitizeSearchTerms' ],
				'default' => $filter,
			],
			'filterApply' => [
				'section' => 'header/banner-search',
				'class' => HTMLButtonField::class,
				'default' => $this->msg( 'centralnotice-filter-template-submit' )->text(),
			]
		];

		// --- Create the management options --- //
		$formDescriptor += [
			'selectAllBanners' => [
				'section' => 'header/banner-bulk-manage',
				'class' => HTMLCheckField::class,
				'disabled' => !$this->editable,
			],
			/* TODO: Actually enable this feature
			'archiveSelectedBanners' => array(
				'section' => 'header/banner-bulk-manage',
				'class' => HTMLButtonField::class,
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedBanners' => [
				'section' => 'header/banner-bulk-manage',
				'class' => HTMLButtonField::class,
				'default' => $this->msg( 'centralnotice-remove' )->text(),
				'disabled' => !$this->editable,
			],
			'addNewBanner' => [
				'section' => 'header/one-off',
				'class' => HTMLButtonField::class,
				'default' => $this->msg( 'centralnotice-add-template' )->text(),
				'disabled' => !$this->editable,
			],
			'newBannerName' => [
				'section' => 'addBanner',
				'class' => HTMLTextField::class,
				'disabled' => !$this->editable,
				'label' => $this->msg( 'centralnotice-banner-name' )->text(),
			],
			'createFromTemplateCheckbox' => [
				'section' => 'addBanner',
				'class' => HTMLCheckField::class,
				'label' => $this->msg( 'centralnotice-create-from-template-checkbox-label' )->text(),
				'disabled' => !$this->editable || empty( $this->getTemplateBannerDropdownItems() ),
			],
			'newBannerTemplate' => [
				'section' => 'addBanner',
				'class' => HTMLSelectLimitField::class,
				'cssclass' => 'banner-template-dropdown-hidden',
				'disabled' => !$this->editable || empty( $this->getTemplateBannerDropdownItems() ),
				'options' => $this->getTemplateBannerDropdownItems()
			],
			'newBannerEditSummary' => [
				'section' => 'addBanner',
				'class' => HTMLTextField::class,
				'label-message' => 'centralnotice-change-summary-label',
				'placeholder-message' => 'centralnotice-change-summary-action-prompt',
				'disabled' => !$this->editable,
				'filter-callback' => [ $this, 'truncateSummaryField' ]
			],
			'removeBannerEditSummary' => [
				'section' => 'removeBanner',
				'class' => HTMLTextField::class,
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
					'class' => HTMLCheckField::class,
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
						return $this->msg( 'centralnotice-banner-name-error' )->parse();
					} else {
						$this->bannerName = $formData[ 'newBannerName' ];
					}

					if ( Banner::fromName( $this->bannerName )->exists() ) {
						return $this->msg( 'centralnotice-template-exists' )->parse();
					} else {
						if ( !empty( $formData['newBannerTemplate'] ) ) {
							try {
								$bannerTemplate = Banner::fromName( $formData['newBannerTemplate'] );
								// This will do data load for the banner, confirming it actually exists in the DB
								// without calling Banner::exists()
								if ( !$bannerTemplate || !$bannerTemplate->isTemplate() ) {
									throw new BannerDataException(
										"Attempted to create a banner based on invalid template"
									);
								}
							} catch ( BannerDataException $exception ) {
								wfDebugLog( 'CentralNotice', $exception->getMessage() );

								// We do not want to show the actual exception to the user here,
								// since the message does not actually refer to the template being created,
								// but to the template it is being created from
								return $this->msg( 'centralnotice-banner-template-error' )->plain();
							}

							$retval = Banner::addFromBannerTemplate(
								$this->bannerName,
								$this->getUser(),
								$bannerTemplate,
								$formData['newBannerEditSummary']
							);
						} else {
							$retval = Banner::addBanner(
								$this->bannerName,
								"<!-- Empty banner -->",
								$this->getUser(),
								false,
								false,
								// Default values of a zillion parameters...
								[], [], null,
								$formData['newBannerEditSummary']
							);
						}

						if ( $retval ) {
							// Something failed; display error to user
							return $this->msg( $retval )->parse();
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

				case 'remove':
					$summary = $formData['removeBannerEditSummary'];
					$failed = [];
					foreach ( $formData as $element => $value ) {
						$parts = explode( '-', $element, 2 );
						if ( ( $parts[0] === 'applyTo' ) && ( $value === true ) ) {
							try {

								Banner::removeBanner(
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
			return $this->msg( 'centralnotice-generic-error' )->parse();
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
			$this->bannerFilterString = $this->sanitizeSearchTerms( $filterParam );
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
	 * FIXME Some of this code is repeated in BannerRenderer, but probably
	 * should be elsewhere.
	 *
	 * @return array
	 */
	private function getBannerPreviewEditLinks() {
		$linkRenderer = $this->getLinkRenderer();
		$links = [
			$linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Randompage' ),
				$this->msg( 'centralnotice-live-preview' )->text(),
				[ 'class' => 'cn-banner-list-element-label-text' ],
				[
					'banner' => $this->bannerName,
					'uselang' => $this->bannerLanguagePreview,
					'force' => '1',
				]
			)
		];

		$bannerTitle = $this->banner->getTitle();
		// $bannerTitle can be null sometimes
		if ( $bannerTitle && $this->getUser()->isAllowed( 'editinterface' ) ) {
			$links[] = $linkRenderer->makeLink(
				$bannerTitle,
				$this->msg( 'centralnotice-banner-edit-onwiki' )->text(),
				[ 'class' => 'cn-banner-list-element-label-text' ],
				[ 'action' => 'edit' ]
			);
		}
		if ( $bannerTitle ) {
			$links[] = $linkRenderer->makeLink(
				$bannerTitle,
				$this->msg( 'centralnotice-banner-history' )->text(),
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
		global $wgUseCdn;

		$out = $this->getOutput();
		$out->addModules( 'ext.centralNotice.adminUi.bannerEditor' );
		$this->addHelpLink(
			'//meta.wikimedia.org/wiki/Special:MyLanguage/Help:CentralNotice',
			true
		);

		$out->setPageTitle( $this->bannerName );
		$out->setSubtitle(
			$this->getLanguage()->pipeList( $this->getBannerPreviewEditLinks() )
		);

		// Generate the form
		$formDescriptor = $this->generateBannerEditForm();

		// Now begin form processing
		$htmlForm = new CentralNoticeHtmlForm(
			$formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditBanner' ] )
			->prepareForm();

		$formResult = $htmlForm->tryAuthorizedSubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Recreate the form because something could have changed
		$formDescriptor = $this->generateBannerEditForm();

		$htmlForm = new CentralNoticeHtmlForm(
			$formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( [ $this, 'processEditBanner' ] )
			->setId( 'cn-banner-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()
			->setId( 'cn-banner-editor' )
			->setDisplayFormat( 'div' )
			->prepareForm()
			->displayForm( $formResult );

		// Send banner name into page for access from JS
		$out->addHTML( Xml::element( 'span',
			[
				'id' => 'centralnotice-data-container',
				'data-banner-name' => $this->bannerName
			]
		) );

		if ( !$this->banner->isTemplate() ) {
			// Controls to purge banner loader URLs from CDN caches for a given language.
			if ( $wgUseCdn ) {
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
	}

	protected function generateBannerEditForm() {
		global $wgCentralNoticeBannerMixins, $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		array_walk(
			$languages,
			static function ( &$val, $index ) {
				$val = "$index - $val";
			}
		);
		$languages = array_flip( $languages );

		$bannerSettings = $this->banner->getBannerSettings( $this->bannerName, true );

		$formDescriptor = [];

		/* --- Use banner as template --- */
		$campaignNames = $this->banner->getCampaignNames();
		$formDescriptor['banner-is-template'] = [
			'section' => 'banner-template',
			'type' => 'check',
			'disabled' => !$this->editable || !empty( $campaignNames ),
			'label-message' => 'centralnotice-banner-is-template',
			'default' => $this->banner->isTemplate()
		];

		if ( $campaignNames ) {
			$formDescriptor[ 'banner-assigned-to-campaign' ] = [
				'section' => 'banner-template',
				'class' => HTMLInfoField::class,
				'label-message' => 'centralnotice-messages-banner-in-campaign',
				'default' => implode( ', ', $campaignNames )
			];
		}

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
			'default' => $this->banner->getCategory(),
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
			CNDeviceTarget::getDevicesAssociatedWithBanner( $this->banner->getId() )
		);
		$availableDevices = [];
		foreach ( CNDeviceTarget::getAvailableDevices() as $k => $value ) {
			$header = htmlspecialchars( $value[ 'header' ] );
			$label = $this->getOutput()->parseInlineAsInterface( $value[ 'label' ] );
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

		// TODO Remove. See T225831.
		$mixinNames = array_keys( $wgCentralNoticeBannerMixins );
		$availableMixins = array_combine( $mixinNames, $mixinNames );
		$selectedMixins = array_keys( $this->banner->getMixins() );
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
		$messages = $this->banner->getMessageFieldsFromCache();

		$linkRenderer = $this->getLinkRenderer();
		if ( $messages ) {
			// Only show this part of the form if messages exist

			$formDescriptor[ 'translate-language' ] = [
				'section' => 'banner-messages',
				'class' => LanguageSelectHeaderElement::class,
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
						$linkRenderer->makeLink(
							$title,
							$messageName,
							[],
							[
								'group' => BannerMessageGroup::getTranslateGroupName(
									$this->banner->getName()
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
					'class' => HTMLCentralNoticeBannerMessage::class,
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
					'class' => HTMLLargeMultiSelectField::class,
					'disabled' => !$this->editable,
					'label-message' => 'centralnotice-prioritylangs',
					'options' => $languages,
					'default' => $bannerSettings[ 'prioritylangs' ],
					'help-message' => 'centralnotice-prioritylangs-explain',
					'cssclass' => 'separate-form-element cn-multiselect',
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
						'class' => HTMLInfoField::class,
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
			'class' => HTMLInfoField::class,
			'default' => Html::rawElement(
				'div',
				[ 'class' => 'separate-form-element' ],
				$this->msg( 'centralnotice-edit-template-summary' )->parse() ),
			'rawrow' => true,
		];

		$renderer = new BannerRenderer( $this->getContext(), $this->banner );
		$magicWords = $renderer->getMagicWords();
		foreach ( $magicWords as &$word ) {
			$word = wfEscapeWikiText( '{{{' . $word . '}}}' );
		}
		$formDescriptor[ 'banner-mixin-words' ] = [
			'section' => 'edit-template',
			'type' => 'info',
			'default' => $this->msg(
					'centralnotice-edit-template-magicwords',
					$this->getLanguage()->listToText( $magicWords )
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
			'class' => HTMLInfoField::class,
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
			'default' => $this->banner->getBodyContent(),
			'cssclass' => 'separate-form-element'
		];

		$links = [];
		foreach ( $this->banner->getIncludedTemplates() as $titleObj ) {
			$links[] = $linkRenderer->makeLink( $titleObj );
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
			'class' => HTMLTextField::class,
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder' => $this->msg( 'centralnotice-change-summary-prompt' ),
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'save-button' ] = [
			'section' => 'form-actions',
			'class' => HTMLSubmitField::class,
			'default' => $this->msg( 'centralnotice-save-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		];

		$formDescriptor[ 'clone-button' ] = [
			'section' => 'form-actions',
			'class' => HTMLButtonField::class,
			'default' => $this->msg( 'centralnotice-clone' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		];

		/* TODO: Add this back in when we can actually support it
		$formDescriptor[ 'archive-button' ] = array(
			'section' => 'form-actions',
			'class' => HTMLButtonField::class,
			'default' => $this->msg( 'centralnotice-archive-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);
		*/

		$formDescriptor[ 'delete-button' ] = [
			'section' => 'form-actions',
			'class' => HTMLButtonField::class,
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
			'class' => HTMLTextField::class,
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder' => $this->msg( 'centralnotice-change-summary-action-prompt' ),
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'deleteEditSummary' ] = [
			'section' => 'delete-banner',
			'class' => HTMLTextField::class,
			'label-message' => 'centralnotice-change-summary-label',
			'placeholder-message' => 'centralnotice-change-summary-action-prompt',
			'disabled' => !$this->editable,
			'filter-callback' => [ $this, 'truncateSummaryField' ]
		];

		$formDescriptor[ 'action' ] = [
			'section' => 'form-actions',
			'type' => 'hidden',
			// The default is to save for historical reasons.  TODO: review.
			'default' => 'save',
		];

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
			'id' => 'cn-cdn-cache-purge'
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
				if ( !Banner::isValidBannerName( $this->bannerName ) ) {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-banner-name-error' );
				}
				try {
					Banner::removeBanner(
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

			case 'clone':
				if ( !$this->editable ) {
					return null;
				}
				$newBannerName = $formData[ 'cloneName' ];
				if ( !Banner::isValidBannerName( $newBannerName ) ) {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-banner-name-error' );
				}

				$this->ensureBanner( $this->bannerName );
				$this->banner->cloneBanner(
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

			default:
				// Nothing was requested, so do nothing
				break;
		}
	}

	protected function processSaveBannerAction( $formData ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$this->ensureBanner( $this->bannerName );
		$summary = $formData['summary'];

		/* --- Update the translations --- */
		// But only if we aren't using translate or if the preview language is the content language
		if ( !$wgNoticeUseTranslateExtension || $this->bannerLanguagePreview === $wgLanguageCode ) {
			foreach ( $formData as $key => $value ) {
				if ( strpos( $key, 'message-' ) === 0 ) {
					$messageName = substr( $key, strlen( 'message-' ) );
					$bannerMessage = $this->banner->getMessageField( $messageName );

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

		$this->banner->setAllocation(
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'registered', $formData[ 'display-to' ] )
		);
		$this->banner->setCategory( $formData[ 'banner-class' ] );
		$this->banner->setDevices( $formData[ 'device-classes' ] );
		$this->banner->setPriorityLanguages( $prioLang );
		$this->banner->setBodyContent( $formData[ 'banner-body' ] );

		$this->banner->setMixins( $formData['mixins'] );
		$this->banner->setIsTemplate( (bool)$formData['banner-is-template'] );

		$this->banner->save( $this->getUser(), $summary );

		// Deferred update to purge CDN caches for banner content (for user's lang)
		DeferredUpdates::addUpdate(
			new CdnCacheUpdateBannerLoader( $this->getLanguage()->getCode(), $this->banner ),
			DeferredUpdates::POSTSEND
		);

		return null;
	}

	/**
	 * Returns all template banner names for dropdown
	 *
	 * @return array
	 */
	private function getTemplateBannerDropdownItems() {
		if ( $this->templateBannerNames === null ) {
			$dbr = CNDatabase::getDb();
			$this->templateBannerNames = [];

			$res = $dbr->select(
				[
					'templates' => 'cn_templates'
				],
				[
					'tmp_name'
				],
				[
					'templates.tmp_is_template' => true,
				],
				__METHOD__
			);

			foreach ( $res as $row ) {
				// name of the banner as a key for HTMLSelectField
				$this->templateBannerNames[$row->tmp_name] = $row->tmp_name;
			}
		}

		return $this->templateBannerNames;
	}
}
