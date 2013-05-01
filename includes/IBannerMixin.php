<?php

/**
 * Must be implemented by PHP banner mixins.
 */
interface IBannerMixin {
	/**
	 * Initialize the module.  At the moment, the only action which would
	 * be taken through the controller is to declare magic words.
	 */
	function register( MixinController $controller );
}
