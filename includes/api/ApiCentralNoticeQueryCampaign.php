<?php

/** @todo: This needs some major cleanup to work more like the rest of the API. */
class ApiCentralNoticeQueryCampaign extends ApiBase {

	/**
	 * @var string sanitize campaign name
	 * FIXME: the string is apparently unrestricted in Special:CentralNotice
	 */
	private const CAMPAIGNS_FILTER = '/^[a-zA-Z0-9 _|\-]+$/';

	public function execute() {
		// Obtain the ApiResults object from the base
		$result = $this->getResult();

		// Get our language/project/country
		$params = $this->extractRequestParams();

		$campaigns = explode( '|', self::sanitizeText( $params['campaign'], self::CAMPAIGNS_FILTER ) );

		foreach ( $campaigns as $campaign ) {
			$settings = Campaign::getCampaignSettings( $campaign );
			if ( $settings ) {
				$settings['banners'] = json_decode( $settings['banners'] );

				# TODO this should probably be pushed down:
				$settings['projects'] = explode( ', ', $settings['projects'] );
				$settings['countries'] = explode( ', ', $settings['countries'] );
				$settings['regions'] = explode( ', ', $settings['regions'] );
				$settings['languages'] = explode( ', ', $settings['languages'] );

				$settings['enabled'] = $settings['enabled'] == '1';
				$settings['preferred'] = $settings['preferred'] == '1';
				$settings['locked'] = $settings['locked'] == '1';
				$settings['geo'] = $settings['geo'] == '1';
			}

			$result->addValue( [ $this->getModuleName() ], $campaign, $settings );
		}
	}

	public function getAllowedParams() {
		$params = [];

		$params['campaign'] = '';

		return $params;
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=centralnoticequerycampaign&format=json&campaign=Plea_US'
				=> 'apihelp-centralnoticequerycampaign-example-1',
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
