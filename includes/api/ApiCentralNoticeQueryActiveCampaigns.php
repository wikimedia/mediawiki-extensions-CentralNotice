<?php

/**
 * Query of currently active CentralNotice campaigns.
 */

class ApiCentralNoticeQueryActiveCampaigns extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		// Though there are no parameters, we set a prefix for them, just in case we
		// add parameters later.
		parent::__construct( $query, $moduleName, 'cnac' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		// Obtain the ApiResults object from the base class
		$result = $this->getResult();

		$result->addValue(
			[ 'query', $this->getModuleName() ],
			'campaigns',
			Campaign::getActiveCampaignsAndBanners( $params[ 'includefuture' ] )
		 );
	}

	public function getAllowedParams() {
		return [
			'includefuture' => [
				ApiBase::PARAM_TYPE => 'boolean'
			]
		];
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
