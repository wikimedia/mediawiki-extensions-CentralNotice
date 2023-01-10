<?php

class MixinController {
	/** @var array */
	protected $mixins;

	/** @var array */
	protected $magicWords = [];
	/** @var IContextSource */
	protected $uiContext;

	public function __construct( IContextSource $uiContext, $mixins ) {
		$this->uiContext = $uiContext;
		$this->mixins = $mixins;

		$this->loadPhp();
	}

	public function getContext() {
		return $this->uiContext;
	}

	public function getMagicWords() {
		$words = array_keys( $this->magicWords );
		sort( $words );
		return $words;
	}

	/**
	 * Initialize php modules.
	 */
	public function loadPhp() {
		foreach ( $this->mixins as $name => $info ) {
			if ( !empty( $info['php'] ) ) {
				// The module must register itself with this controller.
				$php_module_path = $info['localBasePath'] . DIRECTORY_SEPARATOR . $info['php'];
				// Strip the file extension and assume the mixin class is eponymous.
				// TODO: maybe they should be registered using hooks instead...
				$php_module_name = preg_replace( "/[.].+$/", "", $info['php'] );
				require_once $php_module_path;
				$mod = new $php_module_name();
				if ( !( $mod instanceof IBannerMixin ) ) {
					throw new MixinNotFoundException( $name );
				}
				$mod->register( $this );
			}
		}
	}

	public function getPreloadJsSnippets() {
		$snippets = [];
		foreach ( $this->mixins as $name => $info ) {
			if ( !empty( $info['preloadJs'] ) ) {
				$filename = $info['localBasePath'] . DIRECTORY_SEPARATOR . $info['preloadJs'];
				$snippet = file_get_contents( $filename );
				if ( !$snippet ) {
					throw new MixinNotFoundException( $name );
				}
				$snippets[$name] = $snippet;
			}
		}
		return $snippets;
	}

	public function getResourceLoaderModules() {
		$modules = [];
		foreach ( $this->mixins as $name => $info ) {
			if ( !empty( $info['resourceLoader'] ) ) {
				$modules[$name] = $info['resourceLoader'];
			}
		}
		return $modules;
	}

	public function registerMagicWord( $word, $callback ) {
		$this->magicWords[$word] = $callback;
	}

	public function renderMagicWord( $word, $params = [] ) {
		if ( array_key_exists( $word, $this->magicWords ) ) {
			$callback = $this->magicWords[$word];
			if ( is_callable( $callback ) ) {
				return call_user_func_array( $callback, $params );
			}
		}
	}
}
