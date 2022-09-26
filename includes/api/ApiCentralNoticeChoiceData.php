<?php

/**
 * Module for the centralnoticechoicedata Web API.
 *
 * This is provided as a fallback mechanism for getting banner choice data
 * from an infrastructure wiki, for cases in which direct cross-wiki DB
 * queries are not possible.
 */
class ApiCentralNoticeChoiceData extends ApiBase {

	private const LANG_FILTER = '/[a-zA-Z0-9\-]+/';

	private const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';

	public function execute() {
		// Extract, sanitize and munge the parameters
		$params = $this->extractRequestParams();

		$project = self::sanitizeText(
				$params['project'],
				self::PROJECT_FILTER
		);

		$lang = self::sanitizeText(
				$params['language'],
				self::LANG_FILTER
		);

		$choices = ChoiceDataProvider::getChoices( $project, $lang );

		// Get the result object for creating the output
		$apiResult = $this->getResult();

		$apiResult->addValue(
			null,
			'choices',
			$choices
		);
	}

	public function getAllowedParams() {
		return [
			'project' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
			],
			'language' => [
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=centralnoticechoicedata&project=wikipedia&language=en'
			=> 'apihelp-centralnoticechoicedata-example-1'
		];
	}

	/**
	 * Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string $param Name of GET/POST parameter
	 * @param string $regex Sanitization regular expression
	 * @param string|null $default Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	protected static function sanitizeText( $param, $regex, $default = null ) {
		$matches = [];

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
