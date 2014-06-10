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
	protected $campaignName = "";

	protected $mixinController = null;

	function __construct( IContextSource $context, Banner $banner, $campaignName = null, AllocationContext $allocContext = null ) {
		$this->context = $context;

		$this->banner = $banner;
		$this->campaignName = $campaignName;

		if ( $allocContext === null ) {
			/**
			 * This should only be used when banners are previewed in management forms.
			 * TODO: set realistic context in the admin ui, drawn from the campaign
			 * configuration and current translation settings.
			 */
			$this->allocContext = new AllocationContext( 'XX', 'en', 'wikipedia', true, 'desktop', 0 );
		} else {
			$this->allocContext = $allocContext;
		}

		$this->mixinController = new MixinController( $this->context, $this->banner->getMixins(), $allocContext );

		//FIXME: it should make sense to do this:
		// $this->mixinController->registerMagicWord( 'campaign', array( $this, 'getCampaign' ) );
		// $this->mixinController->registerMagicWord( 'banner', array( $this, 'getBanner' ) );
	}

	function linkTo() {
		return Linker::link(
			SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$this->banner->getName()}" ),
			htmlspecialchars( $this->banner->getName() ),
			array( 'class' => 'cn-banner-title' )
		);
	}

	/**
	 * Render the banner as an html fieldset
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
			array(
				 'src' => $previewUrl,
				 'alt' => $bannerName,
			)
		);

		$label = $this->context->msg( 'centralnotice-preview', $lang )->text();

		return Xml::fieldset(
			$label,
			$preview,
			array(
				 'class' => 'cn-bannerpreview',
				 'id' => Sanitizer::escapeId( "cn-banner-preview-{$this->banner->getName()}" ),
			)
		);
	}

	/**
	 * Get the body of the banner, with all transformations applied.
	 *
	 * FIXME: "->inLanguage( $context->getLanguage() )" is necessary due to a bug in DerivativeContext
	 */
	function toHtml() {
		global $wgNoticeUseLanguageConversion;
		$parentLang = $lang = $this->context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$parentLang = $lang->getParentLanguage();
		}

		$bannerHtml = $this->context->msg( $this->banner->getDbKey() )->inLanguage( $parentLang )->text();
		$bannerHtml .= $this->getResourceLoaderHtml();
		$bannerHtml = $this->substituteMagicWords( $bannerHtml );

		if ( $wgNoticeUseLanguageConversion ) {
			$bannerHtml = $parentLang->getConverter()->convertTo( $bannerHtml, $lang->getCode() );
		}
		return $bannerHtml;
	}

	function getPreloadJs() {
		return $this->substituteMagicWords( $this->getPreloadJsRaw() );
	}

	function getPreloadJsRaw() {
		$snippets = $this->mixinController->getPreloadJsSnippets();
		$bundled = array();
		$bundled[] = 'var retval = true;';

		if ( $snippets ) {
			foreach ( $snippets as $mixin => $code ) {
				if ( !$this->context->getRequest()->getFuzzyBool( 'debug' ) ) {
					$code = JavaScriptMinifier::minify( $code );
				}

				$bundled[] = "/* {$mixin}: */ retval &= {$code}";
			}
		}
		$bundled[] = 'return retval;';
		return implode( "\n", $bundled );
	}

	function getResourceLoaderHtml() {
		$modules = $this->mixinController->getResourceLoaderModules();
		if ( $modules ) {
			$html = "<!-- " . implode( ", ", array_keys( $modules ) ) . " -->";
			$html .= Html::inlineScript(
				ResourceLoader::makeLoaderConditionalScript(
					Xml::encodeJsCall( 'mw.loader.load', array_values( $modules ) )
				)
			);
			return $html;
		}
		return "";
	}

	function substituteMagicWords( $contents ) {
		return preg_replace_callback(
			'/{{{([^}:]+)(?:[:]([^}]*))?}}}/',
			array( $this, 'renderMagicWord' ),
			$contents
		);
	}

	function getMagicWords() {
		$words = array( 'banner', 'campaign' );
		$words = array_merge( $words, $this->mixinController->getMagicWords() );
		return $words;
	}

	protected function renderMagicWord( $re_matches ) {
		$field = $re_matches[1];
		if ( $field === 'banner' ) {
			return $this->banner->getName();
		} elseif ( $field === 'campaign' ) {
			return $this->campaignName;
		}
		$params = array();
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
