<?php

class CNCountry {
	/** @var string */
	private $name;
	/** @var string[] */
	private $regions;

	public function __construct( $name, array $regions ) {
		$this->name = $name;
		$this->regions = $regions;
	}

	/**
	 * @return string[]
	 */
	public function getRegions() {
		return $this->regions;
	}

	/**
	 * @param string[] $regions
	 */
	public function setRegions( $regions ) {
		$this->regions = $regions;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}
}
