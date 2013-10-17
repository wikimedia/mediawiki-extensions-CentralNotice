<?php

class ComparisonUtil {
	/**
	 * Check that all elements of $inner match in $super, recursively.
	 */
	static public function assertSuperset( $super, $inner, $path = array() ) {
		$expected_value = static::array_dereference( $inner, $path );
		if ( is_array( $expected_value ) ) {
			foreach ( array_keys( $expected_value ) as $key ) {
				$inner_path = $path;
				$inner_path[] = $key;
				self::assertSuperset( $super, $inner, $inner_path );
			}
		} else {
			$actual_value = static::array_dereference( $super, $path );
			if ( $expected_value !== $actual_value ) {
				throw new Exception( "Non-match at " . implode( ".", $path ) . " expected {$expected_value}, found {$actual_value}" );
			}
		}
		return true;
	}

	static protected function array_dereference( $root, $path ) {
		$cur_path = array();
		while ( count( $path ) ) {
			$key = array_shift( $path );
			$cur_path[] = $key;
			if ( !is_array( $root ) or !array_key_exists( $key, $root ) ) {
				throw new Exception( "Missing value for key " . implode( ".", $cur_path ) );
			}
			$root = $root[$key];
		}
		return $root;
	}
}
