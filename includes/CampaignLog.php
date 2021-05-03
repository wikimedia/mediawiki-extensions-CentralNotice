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
			$comma_explode = static function ( $str ) {
				return explode( ", ", $str );
			};

			$json_decode = static function ( $json ) {
				return json_decode( $json, true );
			};

			$store = function ( $name, $decode = null ) use ( $row ) {
				$beginField = 'notlog_begin_' . $name;
				$endField = 'notlog_end_' . $name;

				if ( !$decode ) {
					$decode = static function ( $v ) {
						return $v;
					};
				}
				$this->begin[ $name ] = $decode( $row->$beginField );
				$this->end[ $name ] = $decode( $row->$endField );
			};

			foreach ( static::$basic_fields as $name ) {
				$store( $name );
			}
			foreach ( static::$list_fields as $name ) {
				$store( $name, $comma_explode );
			}
			foreach ( static::$map_fields as $name ) {
				$store( $name, $json_decode );
			}
		}

		$this->campaign = $row->notlog_not_name;
		$this->action = $row->notlog_action;
		$this->timestamp = $row->notlog_timestamp;
		// TODO temporary code for soft dependency on schema change
		$this->comment = $row->notlog_comment ?? '';
	}

	# TODO: use in logpager
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
