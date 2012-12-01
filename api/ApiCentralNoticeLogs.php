<?php

# TODO: bannerlogs

class ApiCentralNoticeLogs extends ApiQueryBase {

	#XXX
	const USER_FILTER = '/[a-zA-Z0-9_.]+/';
	const CAMPAIGNS_FILTER = '/[a-zA-Z0-9_|\-]+/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		$params = $this->extractRequestParams();

		list ( $start, $end, $limit, $offset ) = array( $params['start'], $params['end'], $params['limit'], $params['offset'] );
		$user = $this->sanitizeText( $params['user'], self::USER_FILTER );
		# TODO: multiple
		$campaign = $this->sanitizeText( $params['campaign'], self::CAMPAIGNS_FILTER );

		$cndb = new CentralNoticeDB();

		$logs = $cndb->campaignLogs( $campaign, $user, $start, $end, $limit, $offset );

		$result->addValue( array( 'query', $this->getModuleName() ), 'logs', $logs );
	}

	public function getAllowedParams() {
		return array(
			'campaign' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 50,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN  => 1,
				ApiBase::PARAM_MAX  => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2,
			),
			'offset' => array(
				ApiBase::PARAM_DFLT => 0,
				ApiBase::PARAM_TYPE => 'integer',
			),
			'start' => array(
				ApiBase::PARAM_TYPE => 'timestamp',
			),
			'end' => array(
				ApiBase::PARAM_TYPE => 'timestamp',
			),
		);
	}

	public function getParamDescription() {
		$params = array();

		$params['campaign'] = "Campaign name (optional). Separate multiple values with a \"|\" (vertical bar).";
		$params['start'] = "Start time of range (optional).";
		$params['end'] = "End time of range (optional).";
		$params['user'] = "Username (optional)";
		$params['limit'] = "Maximum rows to return (optional)";
		$params['offset'] = "Offset into result set (optional)";

		return $params;
	}

	public function getDescription() {
		return 'Get a log of campaign configuration changes.';
	}

	public function getVersion() {
		return 'CentralNoticeLogs: 1.0';
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=query&list=centralnoticelogs&format=json";
	}

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string    $value    Incoming value
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	private static function sanitizeText( $value, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $value, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
