<?php

class CampaignLog {
	/** @var string[] */
	private static $basic_fields = [
		'start', 'end', 'enabled', 'preferred', 'locked', 'geo', 'buckets'
	];
	/** @var string[] */
	private static $list_fields = [ 'projects', 'languages', 'countries', 'regions' ];
	/** @var string[] */
	private static $map_fields = [ 'banners' ];

	/** @var mixed[] */
	private $begin;
	/** @var mixed[] */
	private $end;
	/** @var string */
	private $campaign;
	/** @var string */
	private $action;
	/** @var string|int */
	private $timestamp;
	/** @var string|null */
	private $comment;

	/**
	 * @param stdClass|null $row
	 */
	public function __construct( $row = null ) {
		$this->begin = [];
		$this->end = [];
		if ( $row ) {
			// Both functions intentionally drop invalid values like "" and "0"
			$commaExplode = static fn ( ?string $str ) => $str ? explode( ', ', $str ) : [];
			$jsonDecode = static fn ( ?string $json ) => $json ? json_decode( $json, true ) : [];

			$store = function ( string $name, ?callable $decode = null ) use ( $row ) {
				$this->begin[$name] = $row->{"notlog_begin_$name"};
				$this->end[$name] = $row->{"notlog_end_$name"};
				if ( $decode ) {
					$this->begin[$name] = $decode( $this->begin[$name] );
					$this->end[$name] = $decode( $this->end[$name] );
				}
			};

			foreach ( static::$basic_fields as $name ) {
				$store( $name );
			}
			foreach ( static::$list_fields as $name ) {
				$store( $name, $commaExplode );
			}
			foreach ( static::$map_fields as $name ) {
				$store( $name, $jsonDecode );
			}
		}

		$this->campaign = $row->notlog_not_name;
		$this->action = $row->notlog_action;
		$this->timestamp = $row->notlog_timestamp;
		$this->comment = $row->notlog_comment ?? '';
	}

	/**
	 * TODO: Use in {@see LogPager}
	 * @return array<string,array>
	 */
	public function changes() {
		$removed = [];
		$added = [];

		$diff_basic = function ( $name ) use ( &$removed, &$added ) {
			if ( $this->begin[ $name ] !== $this->end[ $name ] ) {
				if ( $this->begin[ $name ] !== null ) {
					$removed[ $name ] = $this->begin[ $name ];
				}
				if ( $this->end[ $name ] !== null ) {
					$added[ $name ] = $this->end[ $name ];
				}
			}
		};
		$diff_list = function ( $name ) use ( &$removed, &$added ) {
			if ( $this->begin[ $name ] !== $this->end[ $name ] ) {
				$removed[ $name ] = array_diff( $this->begin[ $name ], $this->end[ $name ] );
				if ( !$removed[ $name ] || $removed[ $name ] === [ "" ] ) {
					unset( $removed[ $name ] );
				}
				$added[ $name ] = array_diff( $this->end[ $name ], $this->begin[ $name ] );
				if ( !$added[ $name ] || $added[ $name ] === [ "" ] ) {
					unset( $added[ $name ] );
				}
			}
		};
		$diff_map = function ( $name ) use ( &$removed, &$added ) {
			$removed[ $name ] = $this->begin[ $name ];
			$added[ $name ] = $this->end[ $name ];

			if ( $this->begin[ $name ] && $this->end[ $name ] ) {
				$all_keys = array_keys( array_merge( $this->begin[ $name ], $this->end[ $name ] ) );
				foreach ( $all_keys as $item ) {
					# simplification: match contents, but diff at item level
					if ( array_key_exists( $item, $this->begin[ $name ] )
						&& array_key_exists( $item, $this->end[ $name ] )
						&& $added[ $name ][ $item ] === $removed[ $name ][ $item ]
					) {
						unset( $added[ $name ][ $item ] );
						unset( $removed[ $name ][ $item ] );
					}
				}
			}
			if ( !$removed[ $name ] ) {
				unset( $removed[ $name ] );
			}
			if ( !$added[ $name ] ) {
				unset( $added[ $name ] );
			}
		};
		foreach ( static::$basic_fields as $name ) {
			$diff_basic( $name );
		}
		foreach ( static::$list_fields as $name ) {
			$diff_list( $name );
		}
		foreach ( static::$map_fields as $name ) {
			$diff_map( $name );
		}

		return [ 'removed' => $removed, 'added' => $added ];
	}
}
