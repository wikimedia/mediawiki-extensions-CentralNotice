<?php

class MixinNotFoundException extends Exception {
	function __construct( $name ) {
		$this->message = "Could not load CentralNotice banner mixin '{$name}'";
	}
}
