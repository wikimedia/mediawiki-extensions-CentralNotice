<?php

use MediaWiki\Api\ApiBase;
use Wikimedia\ParamValidator\ParamValidator;

/** @todo: This needs some major cleanup to work more like the rest of the API. */
class ApiCentralNoticeQueryCampaign extends ApiBase {

	/**
	 * @var string sanitize campaign name
	 * FIXME: the string is apparently unrestricted in Special:CentralNotice
	 */
	private const CAMPAIGNS_FILTER = '/^[a-zA-Z0-9 _|\-]+$/';

	private const MAX_CAMPAIGNS = 32;

	public function execute() {
		$params = $this->extractRequestParams();

		$result = $this->getResult();

		foreach ( $params['campaign'] as $campaign ) {
			if ( !preg_match( self::CAMPAIGNS_FILTER, $campaign ) ) {
				continue;
			}
			$settings = Campaign::getCampaignSettings( $campaign );
			if ( $settings ) {
				$settings['banners'] = json_decode( $settings['banners'] );

				# TODO this should probably be pushed down:
				$settings['projects'] = explode( ', ', $settings['projects'] );
				$settings['countries'] = explode( ', ', $settings['countries'] );
				$settings['regions'] = explode( ', ', $settings['regions'] );
				$settings['languages'] = explode( ', ', $settings['languages'] );

				$settings['enabled'] = (bool)$settings['enabled'];
				$settings['preferred'] = (bool)$settings['preferred'];
				$settings['locked'] = (bool)$settings['locked'];
				$settings['geo'] = (bool)$settings['geo'];
			}

			$result->addValue( [ $this->getModuleName() ], $campaign, $settings );
		}
	}

	public function getAllowedParams() {
		return [
			'campaign' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => self::MAX_CAMPAIGNS,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => self::MAX_CAMPAIGNS,
			],
		];
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
