<?php

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\Field\HTMLMultiSelectField;

class HTMLLargeMultiSelectField extends HTMLMultiSelectField {
	/** @inheritDoc */
	public function getInputHTML( $value ) {
		if ( !is_array( $value ) ) {
			$value = [ $value ];
		}

		$options = "\n";
		foreach ( $this->mParams[ 'options' ] as $name => $optvalue ) {
			$options .= Html::element(
				'option',
				[ 'value' => $optvalue, 'selected' => in_array( $optvalue, $value ) ],
				(string)$name
			) . "\n";
		}

		$properties = [
			'multiple' => 'multiple',
			'id' => $this->mID,
			'name' => "$this->mName[]",
		];

		if ( !empty( $this->mParams[ 'disabled' ] ) ) {
			$properties[ 'disabled' ] = 'disabled';
		}

		if ( !empty( $this->mParams[ 'cssclass' ] ) ) {
			$properties[ 'class' ] = $this->mParams[ 'cssclass' ];
		}

		return Html::rawElement( 'select', $properties, $options );
	}
}
