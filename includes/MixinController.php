<?php

use MediaWiki\Context\IContextSource;

class MixinController {

	/** @var array */
	private $magicWords = [];

	/**
	 * @param IContextSource $uiContext
	 * @param array<string,array> $mixins
	 */
	public function __construct(
		private readonly IContextSource $uiContext,
		private readonly array $mixins,
	) {
		$this->loadPhp();
	}

	public function getContext(): IContextSource {
		return $this->uiContext;
	}

	/**
	 * @return string[]
	 */
	public function getMagicWords() {
		$words = array_keys( $this->magicWords );
		sort( $words );
		return $words;
	}

	/**
	 * Initialize php modules.
	 *
	 * @throws MixinNotFoundException
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

	/**
	 * @throws MixinNotFoundException
	 */
	public function getPreloadJsSnippets(): array {
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

	public function getResourceLoaderModules(): array {
		$modules = [];
		foreach ( $this->mixins as $name => $info ) {
			if ( !empty( $info['resourceLoader'] ) ) {
				$modules[$name] = $info['resourceLoader'];
			}
		}
		return $modules;
	}

	/**
	 * @param string $word
	 * @param callable $callback
	 */
	public function registerMagicWord( $word, $callback ) {
		$this->magicWords[$word] = $callback;
	}

	/**
	 * @param string $word
	 * @param array $params
	 * @return mixed
	 */
	public function renderMagicWord( $word, $params = [] ) {
		if ( array_key_exists( $word, $this->magicWords ) ) {
			$callback = $this->magicWords[$word];
			if ( is_callable( $callback ) ) {
				return $callback( ...$params );
			}
		}
	}
}
