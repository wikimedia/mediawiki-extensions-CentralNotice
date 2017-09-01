<?php

/**
 * Produce HTML and JSON output for a given banner and context
 */
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
	protected $campaignName = "";

	protected $mixinController = null;

	protected $debug;

	/**
	 * Creates a new renderer for a given banner and context
	 *
	 * @param IContextSource $context UI context, including language.
	 *
	 * @param Banner $banner Banner to be rendered.
	 *
	 * @param string $campaignName Which campaign we're serving.  This is
	 * substituted in for {{{campaign}}} magic word.
	 *
	 * @param bool $debug If false, minify the output.
	 */
	function __construct(
		IContextSource $context, Banner $banner, $campaignName = null, $debug = false
	) {
		$this->context = $context;

		$this->banner = $banner;
		$this->campaignName = $campaignName;
		$this->debug = $debug;

		$this->mixinController = new MixinController( $this->context, $this->banner->getMixins() );

		// FIXME: it should make sense to do this:
		// $this->mixinController->registerMagicWord( 'campaign', array( $this, 'getCampaign' ) );
		// $this->mixinController->registerMagicWord( 'banner', array( $this, 'getBanner' ) );
	}

	/**
	 * Produce a link to edit the banner
	 *
	 * @return string Edit URL.
	 */
	function linkTo() {
		return Linker::link(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$this->banner->getName()}" ),
			htmlspecialchars( $this->banner->getName() ),
			[ 'class' => 'cn-banner-title' ]
		);
	}

	/**
	 * Get the edit link for a banner (static version).
	 *
	 * TODO: consolidate with above function
	 *
	 * @param string $name Banner name.
	 *
	 * @return string Edit URL.
	 */
	public static function linkToBanner( $name ) {
		return Linker::link(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$name}" ),
			htmlspecialchars( $name ),
			[ 'class' => 'cn-banner-title' ]
		);
	}

	/**
	 * Render the banner as an html fieldset
	 *
	 * @return string HTML fragment
	 */
	function previewFieldSet() {
		global $wgNoticeBannerPreview;

		if ( !$wgNoticeBannerPreview ) {
			return '';
		}

		$bannerName = $this->banner->getName();
		$lang = $this->context->getLanguage()->getCode();

		$previewUrl = $wgNoticeBannerPreview . "{$bannerName}/{$bannerName}_{$lang}.png";
		$preview = Html::element(
			'img',
			[
				'src' => $previewUrl,
				'alt' => $bannerName,
			]
		);

		$label = $this->context->msg( 'centralnotice-preview', $lang )->text();

		return Xml::fieldset(
			$label,
			$preview,
			[
				'class' => 'cn-bannerpreview',
				'id' => Sanitizer::escapeId( "cn-banner-preview-{$this->banner->getName()}" ),
			]
		);
	}

	/**
	 * Get the body of the banner, with all transformations applied.
	 *
	 * FIXME: "->inLanguage( $context->getLanguage() )" is necessary due to a bug
	 *   in DerivativeContext
	 *
	 * @return string HTML fragment for the banner body.
	 */
	function toHtml() {
		global $wgNoticeUseLanguageConversion;
		$parentLang = $lang = $this->context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$parentLang = $lang->getParentLanguage();
		}

		$bannerKey = $this->banner->getDbKey();
		$bannerContentMessage = $this->context->msg( $bannerKey )->inLanguage( $parentLang );
		if ( !$bannerContentMessage->exists() ) {
			// Translation subsystem failure
			throw new RuntimeException(
				"Banner message key $bannerKey could not be found in {$parentLang->getCode()}"
			);
		}
		$bannerHtml = $bannerContentMessage->text();
		$bannerHtml .= $this->getResourceLoaderHtml();
		$bannerHtml = $this->substituteMagicWords( $bannerHtml );

		if ( $wgNoticeUseLanguageConversion ) {
			$bannerHtml = $parentLang->getConverter()->convertTo( $bannerHtml, $lang->getCode() );
		}
		return $bannerHtml;
	}

	/**
	 * Render any preload javascript for this banner
	 *
	 * @return string JS snippet.
	 */
	function getPreloadJs() {
		$code = $this->substituteMagicWords( $this->getPreloadJsRaw() );

		// Minify the code, if any.
		if ( !$this->debug && $code ) {
			$code = JavaScriptMinifier::minify( $code );
		}
		return $code;
	}

	/**
	 * Unrendered blob of preload javascript snippets
	 *
	 * This is only used internally, and will be parsed for magic words
	 * before use.
	 */
	function getPreloadJsRaw() {
		$snippets = $this->mixinController->getPreloadJsSnippets();

		return implode( "\n\n", $snippets );
	}

	/**
	 * Render any ResourceLoader modules
	 *
	 * If the banner includes RL mixins, render the JS (TODO: and CSS) and
	 * return here.
	 *
	 * @return string HTML snippet.
	 */
	function getResourceLoaderHtml() {
		$modules = $this->mixinController->getResourceLoaderModules();
		if ( $modules ) {
			// FIXME: Does the RL library already include a helper to do this?
			$html = "<!-- " . implode( ", ", array_keys( $modules ) ) . " -->";
			$html .= ResourceLoader::makeInlineScript(
				Xml::encodeJsCall( 'mw.loader.load', array_values( $modules ) )
			);
			return $html;
		}
		return "";
	}

	/**
	 * Replace magic word placeholders with their value
	 *
	 * We rely on $this->renderMagicWord to do the heavy lifting.
	 *
	 * @param string $contents Raw contents to be processed.
	 *
	 * @return string Rendered contents.
	 */
	function substituteMagicWords( $contents ) {
		return preg_replace_callback(
			'/{{{([^}:]+)(?:[:]([^}]*))?}}}/',
			[ $this, 'renderMagicWord' ],
			$contents
		);
	}

	/**
	 * Get a list of magic words provided or dependened upon by this banner
	 *
	 * @return array List of magic word names.
	 */
	function getMagicWords() {
		$words = [ 'banner', 'campaign' ];
		$words = array_merge( $words, $this->mixinController->getMagicWords() );
		return $words;
	}

	/**
	 * Get the value for a magic word
	 *
	 * @param array $re_matches Funky PCRE callback param having the form,
	 *     array(
	 *         0 => full match, ignored,
	 *         1 => magic word name,
	 *         2 => optional arguments to the magic word replacement function
	 *     );
	 *
	 * @return string HTML fragment with the resulting value.
	 */
	protected function renderMagicWord( $re_matches ) {
		$field = $re_matches[1];
		if ( $field === 'banner' ) {
			return $this->banner->getName();
		} elseif ( $field === 'campaign' ) {
			return $this->campaignName;
		}
		$params = [];
		if ( isset( $re_matches[2] ) ) {
			$params = explode( "|", $re_matches[2] );
		}

		$value = $this->mixinController->renderMagicWord( $field, $params );
		if ( $value !== null ) {
			return $value;
		}

		$bannerMessage = $this->banner->getMessageField( $field );
		return $bannerMessage->toHtml( $this->context );
	}
}
