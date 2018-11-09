<?php

class MixinNotFoundException extends Exception {
	public function __construct( $name ) {
		$this->message = "Could not load CentralNotice banner mixin '{$name}'";
	}
}
