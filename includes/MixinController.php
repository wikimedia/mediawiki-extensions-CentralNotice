<?php

class MixinController {
	protected $mixins;

	protected $magicWords = [];
	protected $uiContext;

	function __construct( IContextSource $uiContext, $mixins ) {
		$this->uiContext = $uiContext;
		$this->mixins = $mixins;

		$this->loadPhp();
	}

	function getContext() {
		return $this->uiContext;
	}

	function getMagicWords() {
		$words = array_keys( $this->magicWords );
		sort( $words );
		return $words;
	}

	/**
	 * Initialize php modules.
	 */
	function loadPhp() {
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

	function getPreloadJsSnippets() {
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

	function getResourceLoaderModules() {
		$modules = [];
		foreach ( $this->mixins as $name => $info ) {
			if ( !empty( $info['resourceLoader'] ) ) {
				$modules[$name] = $info['resourceLoader'];
			}
		}
		return $modules;
	}

	function registerMagicWord( $word, $callback ) {
		$this->magicWords[$word] = $callback;
	}

	function renderMagicWord( $word, $params = [] ) {
		if ( array_key_exists( $word, $this->magicWords ) ) {
			$callback = $this->magicWords[$word];
			if ( is_callable( $callback ) ) {
				return call_user_func_array( $callback, $params );
			}
		}
	}
}
