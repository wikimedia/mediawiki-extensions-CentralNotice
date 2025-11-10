<?php

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query of currently active CentralNotice campaigns.
 */
class ApiCentralNoticeQueryActiveCampaigns extends ApiQueryBase {

	public function __construct( ApiQuery $query, string $moduleName ) {
		// Though there are no parameters, we set a prefix for them, just in case we
		// add parameters later.
		parent::__construct( $query, $moduleName, 'cnac' );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$this->getResult()->addValue(
			[ 'query', $this->getModuleName() ],
			'campaigns',
			Campaign::getActiveCampaignsAndBanners( $params[ 'includefuture' ] )
		 );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'includefuture' => [
				ParamValidator::PARAM_TYPE => 'boolean',
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
