<?php

/***
 * Query of currently active CentralNotice campaigns.
 */

class ApiCentralNoticeQueryActiveCampaigns extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		// Though there are no parameters, we set a prefix for them, just in case we
		// add parameters later.
		parent::__construct( $query, $moduleName, 'cnac' );
	}

	public function execute() {
		// Obtain the ApiResults object from the base class
		$result = $this->getResult();

		$result->addValue(
			[ 'query', $this->getModuleName() ],
			'campaigns',
			Campaign::getActiveCampaignsAndBanners()
		 );
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=centralnoticeactivecampaigns&format=json'
				=> 'apihelp-query+centralnoticeactivecampaigns-example-1',
		];
	}
}
