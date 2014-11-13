<?php

class ComparisonUtil {
	/**
	 * Check that all elements of $inner match in $super, recursively.
	 */
	public static function assertSuperset( $super, $inner, $path = array() ) {
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

	protected static function array_dereference( $root, $path ) {
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

	/**
	 * Match banner allocations arrays, using lenient floating-point comparison
	 */
	public static function assertEqualAllocations( $allocations, $expected ) {
		$delta = 0.001;

		if ( count( $allocations ) != count( $expected ) ) {
			throw new Exception( "Wrong number of banners, expected " . json_encode( $expected ) . ", got " . json_encode( $allocations ) );
		}
		foreach ( $allocations as $banner ) {
			$banner_name = $banner['name'];
			if ( !array_key_exists( $banner_name, $expected ) ) {
				throw new Exception( "Surprise banner {$banner_name}" );
			}
			$actual_allocation = $banner['allocation'];
			$expected_allocation = $expected[$banner_name];
			if ( abs( $actual_allocation - $expected_allocation ) > $delta ) {
				throw new Exception( "Allocation for {$banner_name} should have been {$expected_allocation}, but instead was {$actual_allocation}" );
			}
		}

		return true;
	}
}
