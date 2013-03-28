<?php

class BannerRenderer {
	/**
	 * @var IContextSource $context
	 */
	protected $context;

	/**
	 * @var Banner $banner
	 */
	protected $banner;

	/**
	 * Campaign in which context the rendering is taking place.  Empty during preview.
	 *
	 * @var string $campaignName
	 */
	protected $campaignName = '';

	function __construct( IContextSource $context, Banner $banner, $campaignName = null ) {
		$this->banner = $banner;
		$this->campaignName = $campaignName;

		$this->setContext( $context );
	}

	function setContext( IContextSource $context ) {
		$this->context = $context;
	}

	function linkTo() {
		return Linker::link(
			SpecialPage::getTitleFor( 'NoticeTemplate', 'view' ),
			htmlspecialchars( $this->banner->getName() ),
			array( 'class' => 'cn-banner-title' ),
			array( 'template' => $this->banner->getName() )
		);
	}

	/**
	 * Render the banner as an html fieldset
	 *
	 * TODO js refresh, iframe
	 */
	function previewFieldSet() {
		$preview = "";
		try {
			$preview = $this->toHtml();
		} catch ( SpecialBannerLoaderException $e ) {
			$preview = $this->context->msg( 'centralnotice-nopreview' )->text();
		}
		$lang = $this->context->getLanguage()->getCode();

		$label = $this->context->msg( 'centralnotice-preview', $lang )->text();

		/* TODO: enhanced preview modes
		//FIXME: pull project and language from an associated campaign...
		$live_target = "wikipedia:{$lang}:Special:Random";
		$preview .= "<br>" . Linker::link(
			Title::newFromText( $live_target ),
			$context->msg( 'centralnotice-live-page' )->text(),
			array(),
			array( 'banner' => $this->name )
		);
		*/

		return Xml::fieldset(
			$label,
			$preview,
			array( 'class' => 'cn-bannerpreview' )
		);
	}

	/**
	 * Get the body of the banner, with all transformations applied.
	 *
	 * FIXME: "->inLanguage( $context->getLanguage() )" is necessary due to a bug in DerivativeContext
	 */
	function toHtml() {
		$bannerHtml = $this->context->msg( $this->banner->getDbKey() )->inLanguage( $this->context->getLanguage() )->text();

		$bannerHtml = preg_replace_callback(
			'/{{{([^}]+)}}}/',
			array( $this, 'renderMagicWord' ),
			$bannerHtml
		);
		return $bannerHtml;
	}

	protected function renderMagicWord( $re_matches ) {
		$field = $re_matches[1];
		if ( $field === 'banner' ) {
			return $this->banner->name;
		} elseif ( $field === 'campaign' ) {
			return $this->campaignName;
		}
		$bannerMessage = $this->banner->getMessageField( $field );
		return $bannerMessage->toHtml( $this->context );
	}
}
