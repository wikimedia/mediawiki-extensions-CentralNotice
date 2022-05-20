<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\ResourceLoader;

/**
 * Produce HTML and JSON output for a given banner and context
 */
class BannerRenderer {
	/**
	 * @var IContextSource
	 */
	protected $context;

	/**
	 * @var Banner
	 */
	protected $banner;

	/**
	 * Campaign in which context the rendering is taking place.  Empty during preview.
	 * @var string
	 */
	protected $campaignName = "";

	/**
	 * Unsaved raw banner content to use for rendering an unsaved preview.
	 * @var string|null
	 */
	protected $previewContent;

	/**
	 * Associative array of banner message names and values, for rendering an unsaved
	 * preview.
	 * @var array|null
	 */
	protected $previewMessages;

	/** @var MixinController|null */
	protected $mixinController = null;

	/** @var bool */
	protected $debug;

	/**
	 * Creates a new renderer for a given banner and context
	 *
	 * @param IContextSource $context UI context, including language.
	 * @param Banner $banner Banner to be rendered.
	 * @param string|null $campaignName Which campaign we're serving.  This is
	 *   substituted in for {{{campaign}}} magic word.
	 * @param string|null $previewContent Unsaved raw banner content to use for rendering
	 *   an unsaved preview.
	 * @param array|null $previewMessages Associative array of banner message names and
	 *   and values, for rendering unsaved preview.
	 * @param bool $debug If false, minify the output.
	 */
	public function __construct(
		IContextSource $context,
		Banner $banner,
		$campaignName = null,
		$previewContent = null,
		$previewMessages = null,
		$debug = false
	) {
		$this->context = $context;

		$this->banner = $banner;
		$this->campaignName = $campaignName;
		$this->previewContent = $previewContent;
		$this->previewMessages = $previewMessages;
		$this->debug = $debug;

		$this->mixinController = new MixinController( $this->context, $this->banner->getMixins() );

		// FIXME: it should make sense to do this:
		// $this->mixinController->registerMagicWord( 'campaign', array( $this, 'getCampaign' ) );
		// $this->mixinController->registerMagicWord( 'banner', array( $this, 'getBanner' ) );
	}

	/**
	 * Get the edit link for a banner (static version).
	 *
	 * @param string $name Banner name.
	 * @return string Edit URL.
	 *
	 * TODO Move the following method somewhere more appropriate.
	 */
	public static function linkToBanner( $name ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return $linkRenderer->makeLink(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$name}" ),
			$name,
			[ 'class' => 'cn-banner-title' ]
		);
	}

	/**
	 * Return a rendered link to the Special:Random banner preview.
	 *
	 * @param string $name Banner name
	 * @return string HTML anchor tag
	 *
	 * TODO Move this method somewhere more appropriate.
	 */
	public static function getPreviewLink( $name ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		// FIXME: Need a reusable way to get a known target language.  We want
		// to set uselang= so that the banner is rendered using an available
		// translation.  banner->getPriorityLanguages isn't reliable.

		return $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Randompage' ),
			wfMessage( 'centralnotice-live-preview' )->text(),
			[ 'class' => 'cn-banner-list-element-label-text' ],
			[
				'banner' => $name,
				// TODO: 'uselang' => $language,
				'force' => '1',
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
	public function toHtml() {
		global $wgNoticeUseLanguageConversion;

		$parentLang = $lang = $this->context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$parentLang = $lang->getParentLanguage();
		}
		'@phan-var Language $parentLang';

		if ( $this->previewContent !== null ) {
			// Preview mode, banner content is ephemeral
			// TODO Double-check that this is the correct way to get process as a i18n
			// message, and add documentation to the core method.
			$bannerHtml = MediaWikiServices::getInstance()->getMessageCache()->transform(
				$this->previewContent,
				false,
				$parentLang
			);

		} else {
			// Normal mode, banner content is stored as message
			$bannerKey = $this->banner->getDbKey();
			$bannerContentMessage = $this->context->msg( $bannerKey )->inLanguage( $parentLang );
			if ( !$bannerContentMessage->exists() ) {
				// Translation subsystem failure
				throw new RuntimeException(
					"Banner message key $bannerKey could not be found in {$parentLang->getCode()}" );
			}
			$bannerHtml = $bannerContentMessage->text();
		}

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
	 * TODO: Remove/refactor. See T225831.
	 *
	 * @return string JavaScript code
	 */
	public function getPreloadJs() {
		$code = $this->substituteMagicWords( $this->getPreloadJsRaw() );

		// Minify the code, if any.
		if ( !$this->debug && $code ) {
			$code = ResourceLoader::filter( 'minify-js', $code, [ 'cache' => false ] );
		}
		return $code;
	}

	/**
	 * Unrendered blob of preload javascript snippets
	 *
	 * This is only used internally, and will be parsed for magic words
	 * before use.
	 *
	 * TODO: Remove/refactor. See T225831.
	 *
	 * @return string JavaScript code
	 */
	public function getPreloadJsRaw() {
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
	public function getResourceLoaderHtml() {
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
	public function substituteMagicWords( $contents ) {
		// FIXME The syntax {{{magicword:param1|param2}}} for magic words does not work,
		// since it is munged by core before we get it here. It was part of the in-banner
		// mixin system, currently unused.
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
	public function getMagicWords() {
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
	 *              FIXME Doesn't work, unused
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

		// FIXME This doesn't work; part of the unused in-banner mixin system.
		$params = [];
		if ( isset( $re_matches[2] ) ) {
			$params = explode( "|", $re_matches[2] );
		}

		$value = $this->mixinController->renderMagicWord( $field, $params );
		if ( $value !== null ) {
			return $value;
		}

		// Treat anything else as a translatable message
		$messageFields = explode( ',', $field, 2 );
		if ( isset( $messageFields[ 1 ] ) ) {
			// A translatable message from a named banner. String before the comma is the
			// banner that defines the message, and the rest is the name of the
			// translatable message from that banner.
			$bannerMessage = Banner::getMessageFieldForBanner(
				trim( $messageFields[ 0 ] ),
				trim( $messageFields[ 1 ] )
			);

		} elseif ( $this->previewMessages !== null &&
			array_key_exists( $field, $this->previewMessages ) ) {
			// If we're rendering an unsaved preview and the field is provided as an
			// unsaved preview message, transform as a messages, sanitize and return that.

			// TODO As above, double-check that this is the correct way to get process as
			// a i18n message.
			return MediaWikiServices::getInstance()->getMessageCache()->transform(
				BannerMessage::sanitize( $this->previewMessages[ $field ] ) );

		} else {
			$bannerMessage = $this->banner->getMessageField( $field );
		}

		return $bannerMessage->toHtml( $this->context );
	}

}
