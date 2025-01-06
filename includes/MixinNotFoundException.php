<?php

class MixinNotFoundException extends Exception {
	public function __construct( string $name ) {
		$this->message = "Could not load CentralNotice banner mixin '{$name}'";
	}
}
