<?php

/**
 * Provides the criteria needed to do allocation.
 */
class AllocationContext {
	protected $country;
	protected $language;
	protected $project;
	protected $anonymous;
	protected $device;
	protected $bucket;

	/**
	 * All criteria are required for banner requests, but when AllocationContext's
	 * are created internally, some criteria may be null to allow special filtering.
	 *
	 * @param string $country
	 * @param string $language
	 * @param string $project
	 * @param boolean $anonymous
	 * @param string $device
	 * @param int $bucket
	 */
	function __construct( $country, $language, $project, $anonymous, $device, $bucket ) {
		$this->country = $country;
		$this->language = $language;
		$this->project = $project;
		$this->anonymous = $anonymous;
		$this->device = $device;
		$this->bucket = $bucket;
	}

	function getCountry() {
		return $this->country;
	}
	function getLanguage() {
		return $this->language;
	}
	function getProject() {
		return $this->project;
	}
	function getAnonymous() {
		return $this->anonymous;
	}
	function getDevice() {
		return $this->device;
	}
	function getBucket() {
		return $this->bucket;
	}
}
