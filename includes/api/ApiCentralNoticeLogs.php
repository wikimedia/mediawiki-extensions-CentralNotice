<?php

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQueryBase;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\NumericDef;

# TODO: bannerlogs

/** @todo This needs some major cleanup to work more like the rest of the API. */
class ApiCentralNoticeLogs extends ApiQueryBase {

	private const USER_FILTER = '/[a-zA-Z0-9_.]+/';
	private const CAMPAIGNS_FILTER = '/[a-zA-Z0-9_|\-]+/';

	public function execute() {
		$params = $this->extractRequestParams();

		$start = $params['start'];
		$end = $params['end'];
		$limit = $params['limit'];
		$offset = $params['offset'];

		$user = self::sanitizeText( $params['user'], self::USER_FILTER );
		# TODO: multiple
		$campaign = self::sanitizeText( $params['campaign'], self::CAMPAIGNS_FILTER );

		$logs = Campaign::campaignLogs( $campaign, $user, $start, $end, $limit, $offset );

		$this->getResult()->addValue( [ 'query', $this->getModuleName() ], 'logs', $logs );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'campaign' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'user' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 50,
				ParamValidator::PARAM_TYPE => 'limit',
				NumericDef::PARAM_MIN  => 1,
				NumericDef::PARAM_MAX  => ApiBase::LIMIT_BIG1,
				NumericDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			],
			'offset' => [
				ParamValidator::PARAM_DEFAULT => 0,
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'start' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'end' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
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
	 * null if there was no match.
	 *
	 * @param ?string $value Incoming value
	 * @param string $regex Sanitization regular expression
	 * @return string|null The sanitized value
	 */
	private static function sanitizeText( ?string $value, string $regex ): ?string {
		if ( preg_match( $regex, $value ?? '', $matches ) ) {
			return $matches[ 0 ];
		}
		return null;
	}
}
