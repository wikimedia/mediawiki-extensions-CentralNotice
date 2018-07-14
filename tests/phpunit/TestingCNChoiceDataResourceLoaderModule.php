<?php

/**
 * Wrapper to circumvent access control
 */
class TestingCNChoiceDataResourceLoaderModule extends CNChoiceDataResourceLoaderModule {
	public function getChoicesForTesting( $rlContext ) {
		return $this->getChoices( $rlContext );
	}
}
