<?php

use MediaWiki\Api\ApiBase;
use Wikimedia\ParamValidator\ParamValidator;

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
		$project = self::sanitizeText( $params['project'], self::PROJECT_FILTER );
		$lang = self::sanitizeText( $params['language'], self::LANG_FILTER );

		if ( $project === null || $lang === null ) {
			// Both database fields are not nullable, the query wouldn't find anything anyway
			$choices = [];
		} else {
			$choices = ChoiceDataProvider::getChoices( $project, $lang );
		}

		$this->getResult()->addValue(
			null,
			'choices',
			$choices
		);
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'project' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'language' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=centralnoticechoicedata&project=wikipedia&language=en'
			=> 'apihelp-centralnoticechoicedata-example-1'
		];
	}

	/**
	 * Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * null if there was no match.
	 *
	 * @param string $value
	 * @param string $regex Sanitization regular expression
	 * @return string|null The sanitized value
	 */
	private static function sanitizeText( string $value, string $regex ): ?string {
		if ( preg_match( $regex, $value, $matches ) ) {
			return $matches[ 0 ];
		}
		return null;
	}
}
