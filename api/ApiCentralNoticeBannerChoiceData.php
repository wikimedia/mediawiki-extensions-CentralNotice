<?php

/***
 * Module for the centralnoticebannerchoicedata Web API.
 *
 * This is provided as a fallback mechanism for getting banner choice data
 * from an infrastructure wiki, for cases in which direct cross-wiki DB
 * queries are not possible.
 */
class ApiCentralNoticeBannerChoiceData extends ApiCentralNoticeAllocationBase {

	/**
	 * Regex for filtering values of the status parameter
	 */
	const STATUS_FILTER = '/loggedin|anonymous/';

	public function execute() {

		// Extract, sanitize and munge the parameters
		$params = $this->extractRequestParams();

		$project = parent::sanitizeText(
				$params['project'],
				parent::PROJECT_FILTER
		);

		$lang = parent::sanitizeText(
				$params['language'],
				parent::LANG_FILTER
		);

		$status = parent::sanitizeText(
				$params['status'],
				self::STATUS_FILTER
		);

		if ( $status === 'loggedin' ) {
			$status = BannerChoiceDataProvider::LOGGED_IN;
		} else if ( $status === 'anonymous' ) {
			$status = BannerChoiceDataProvider::ANONYMOUS;
		} else {
			$this->dieUsage(
				'Invalid status value: must be "loggedin" or "anonymous".',
				'invalid-status');
		}

		$choicesProvider = new BannerChoiceDataProvider(
			$project, $lang, $status, BannerChoiceDataProvider::USE_DEFAULT_DB );

		$choices = $choicesProvider->getChoices();

		// Get the result object for creating the output
		$apiResult = $this->getResult();

		$apiResult->addValue(
			null,
			'choices',
			$choices
		 );
	}

	public function getAllowedParams() {
		return array(
			'project' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
			),
			'language' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
			),
			'status' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
			)
		);
	}

	protected function getExamplesMessages() {
		return array(
			'action=centralnoticebannerchoicedata&project=wikpedia&language=en&status=anonymous'
			=> 'apihelp-centralnoticebannerchoicedata-example-1'
		);
	}
}