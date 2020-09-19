<?php

# TODO: bannerlogs

/** @todo: This needs some major cleanup to work more like the rest of the API. */
class ApiCentralNoticeLogs extends ApiQueryBase {

	private const USER_FILTER = '/[a-zA-Z0-9_.]+/';
	private const CAMPAIGNS_FILTER = '/[a-zA-Z0-9_|\-]+/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		$params = $this->extractRequestParams();

		$start = $params['start'];
		$end = $params['end'];
		$limit = $params['limit'];
		$offset = $params['offset'];

		$user = self::sanitizeText( $params['user'], self::USER_FILTER );
		# TODO: multiple
		$campaign = self::sanitizeText( $params['campaign'], self::CAMPAIGNS_FILTER );

		$logs = Campaign::campaignLogs( $campaign, $user, $start, $end, $limit, $offset );

		$result->addValue( [ 'query', $this->getModuleName() ], 'logs', $logs );
	}

	public function getAllowedParams() {
		return [
			'campaign' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'user' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 50,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN  => 1,
				ApiBase::PARAM_MAX  => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
			'offset' => [
				ApiBase::PARAM_DFLT => 0,
				ApiBase::PARAM_TYPE => 'integer',
			],
			'start' => [
				ApiBase::PARAM_TYPE => 'timestamp',
			],
			'end' => [
				ApiBase::PARAM_TYPE => 'timestamp',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=centralnoticelogs&format=json'
				=> 'apihelp-query+centralnoticelogs-example-1',
		];
	}

	/**
	 * Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string $value Incoming value
	 * @param string $regex Sanitization regular expression
	 * @param string|null $default Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	private static function sanitizeText( $value, $regex, $default = null ) {
		$matches = [];

		if ( preg_match( $regex, $value, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
