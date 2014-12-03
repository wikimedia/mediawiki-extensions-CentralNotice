<?php

class Experiment {
	protected $description;

	public static function newFromCampaign( Campaign $campaign ) {
		// create a link table entry. serialize settings.
	}

	public function getDescription() {
		return $this->description;
	}
}
