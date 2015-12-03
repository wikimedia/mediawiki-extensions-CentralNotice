<?php

/***
 * Module for the centralnoticechoicedata Web API.
 *
 * This is provided as a fallback mechanism for getting banner choice data
 * from an infrastructure wiki, for cases in which direct cross-wiki DB
 * queries are not possible.
 */
class ApiCentralNoticeChoiceData extends ApiBase {

	const LANG_FILTER = '/[a-zA-Z0-9\-]+/';

	const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';

	const LOCATION_FILTER = '/[a-zA-Z0-9_\-]+/';

	const DEVICE_NAME_FILTER = '/[a-zA-Z0-9_\-]+/';

	/**
	 * Regex for filtering values of the status parameter
	 */
	const STATUS_FILTER = '/loggedin|anonymous/';

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

		$choicesProvider = new ChoiceDataProvider( $project, $lang );

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
			)
		);
	}

	protected function getExamplesMessages() {
		return array(
			'action=centralnoticechoicedata&project=wikipedia&language=en'
			=> 'apihelp-centralnoticechoicedata-example-1'
		);
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	protected static function sanitizeText( $param, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
