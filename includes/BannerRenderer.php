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

	function __construct( IContextSource $context, Banner $banner, $campaignName = null, IAllocationContext $allocContext = null ) {
		$this->context = $context;

		$this->banner = $banner;
		$this->campaignName = $campaignName;

		if ( $allocContext === null ) {
			$this->allocContext = new FauxAllocContext();
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
		$bannerHtml .= $this->getResourceLoaderHtml();

		return $this->substituteMagicWords( $bannerHtml );
	}

	function getPreloadJs() {
		$snippets = $this->mixinController->getPreloadJsSnippets();
		if ( $snippets ) {
			$bundled = array();
			foreach ( $snippets as $mixin => $code ) {
				if ( !$this->context->getRequest()->getFuzzyBool( 'debug' ) ) {
					$code = JavaScriptMinifier::minify( $code );
				}

				$bundled[] = "/* {$mixin}: */{$code}";
			}
			$js = implode( " && ", $bundled );
			return $this->substituteMagicWords( $js );
		}
		return "";
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

/**
 * This should only be used when banners are previewed in management forms.
 * TODO: set realistic context in the admin ui, drawn from the campaign
 * configuration and current translation settings.
 */
class FauxAllocContext {
    function getCountry() {
		return 'XX';
	}
    function getProject() {
		return 'wikipedia';
	}
    function getAnonymous() {
		return true;
	}
    function getDevice() {
		return 'desktop';
	}
    function getBucket() {
		return 0;
	}
}
