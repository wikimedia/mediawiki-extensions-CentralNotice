<?php

/**
 * Implementation of the query=centralnoticeallocations API call. This call returns the expected banner
 * allocation for the given project, country, and language.
 */
class ApiCentralNoticeQueryCampaign extends ApiBase {

	/**
	 * @var string sanitize campaign name
	 * FIXME: the string is apparently unrestricted in Special:CentralNotice
	 */
	const CAMPAIGNS_FILTER = '/[a-zA-Z0-9_|\-]+/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		$campaigns = explode( '|', $this->sanitizeText( $params['campaign'], static::CAMPAIGNS_FILTER ) );

		$cndb = new CentralNoticeDB();
		foreach ( $campaigns as $campaign ) {
			$settings = $cndb->getCampaignSettings( $campaign );
			if ( $settings ) {
				$settings['banners'] = json_decode( $settings['banners'] );

				# TODO this should probably be pushed down:
				$settings['projects'] = explode( ', ', $settings['projects'] );
				$settings['countries'] = explode( ', ', $settings['countries'] );
				$settings['languages'] = explode( ', ', $settings['languages'] );

				$settings['enabled'] = $settings['enabled'] == '1';
				$settings['preferred'] = $settings['preferred'] == '1';
				$settings['locked'] = $settings['locked'] == '1';
				$settings['geo'] = $settings['geo'] == '1';
			}

			$result->addValue( array( $this->getModuleName() ), $campaign, $settings );
		}
	}

	public function getAllowedParams() {
		$params = array();

		$params['campaign'] = '';

		return $params;
	}

	public function getParamDescription() {
		$params = array();

		$params['campaign'] = "Campaign name. Separate multiple values with a \"|\" (vertical bar).";

		return $params;
	}

	public function getDescription() {
		return 'Get all configuration settings for a campaign.';
	}

	public function getVersion() {
		return 'CentralNoticeQueryCampaign: 1.0';
	}

	/**
	 * Example API calls.
	 *
	 * @return array|bool|string
	 */
	public function getExamples() {
		return "api.php?action=centralnoticequerycampaign&format=json&campaign=Plea_US";
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
