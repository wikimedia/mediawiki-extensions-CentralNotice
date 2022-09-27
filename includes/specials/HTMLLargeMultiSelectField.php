<?php

class HTMLLargeMultiSelectField extends HTMLMultiSelectField {
	public function getInputHTML( $value ) {
		if ( !is_array( $value ) ) {
			$value = [ $value ];
		}

		$options = "\n";
		foreach ( $this->mParams[ 'options' ] as $name => $optvalue ) {
			$options .= Xml::option(
				(string)$name,
				$optvalue,
				in_array( $optvalue, $value )
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

		return Xml::tags( 'select', $properties, $options );
	}
}
