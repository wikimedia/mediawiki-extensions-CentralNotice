<?php

use MediaWiki\Api\ApiBase;
use Wikimedia\ParamValidator\ParamValidator;

/** @todo This needs some major cleanup to work more like the rest of the API. */
class ApiCentralNoticeQueryCampaign extends ApiBase {

	/**
	 * @var string sanitize campaign name
	 * FIXME: the string is apparently unrestricted in Special:CentralNotice
	 */
	private const CAMPAIGNS_FILTER = '/^[a-zA-Z0-9 _|\-]+$/';

	public function execute() {
		// Get our language/project/country
		$params = $this->extractRequestParams();

		if ( !preg_match( self::CAMPAIGNS_FILTER, $params['campaign'] ) ) {
			return;
		}

		$result = $this->getResult();

		$campaigns = explode( '|', $params['campaign'] );

		foreach ( $campaigns as $campaign ) {
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

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'campaign' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
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

}
