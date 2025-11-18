<?php

class CNCountry {

	/**
	 * @param string $name
	 * @param string[] $regions
	 */
	public function __construct(
		private string $name,
		private array $regions,
	) {
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
