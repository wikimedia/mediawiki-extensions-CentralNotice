<?php

class CentralNoticeTestFixtures {
	public $spec = array();
	protected $user;

	// Use exactly the api defaults where available
	static public $defaultCampaign;
	static public $defaultBanner;
	
	function __construct() {
		$this->user = User::newFromName( 'UTSysop' );

		static::$defaultCampaign = array(
			'enabled' => 1,
			// inclusive comparison is used, so this does not cause a race condition.
			'startTs' => wfTimestamp( TS_MW ),
			'projects' => array( ApiCentralNoticeAllocations::DEFAULT_PROJECT ),
			'project_languages' => array( ApiCentralNoticeAllocations::DEFAULT_LANGUAGE ),
			'preferred' => CentralNotice::NORMAL_PRIORITY,
			'geotargeted' => 0,
			'geo_countries' => array( ApiCentralNoticeAllocations::DEFAULT_COUNTRY ),
			'throttle' => 100,
			'banners' => array(),
		);
		static::$defaultBanner = array(
			'body' => 'testing',
			'displayAnon' => ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS === 'true',
			'displayAccount' => ApiCentralNoticeAllocations::DEFAULT_ANONYMOUS === 'false',
			'fundraising' => 1,
			'autolink' => 0,
			'landingPages' => 'JA1, JA2',
			'campaign_z_index' => 0,
			'weight' => 25,
		);
	}

	function addFixtures( $spec ) {
		CNDeviceTarget::addDeviceTarget( 'desktop', '{{int:centralnotice-devicetype-desktop}}' );

		foreach ( $spec['campaigns'] as $campaignSpec ) {
			$campaign = $campaignSpec + static::$defaultCampaign + array(
				'name' => 'TestCampaign_' . rand(),
			);

			Campaign::addCampaign(
				$campaign['name'],
				$campaign['enabled'],
				$campaign['startTs'],
				$campaign['projects'],
				$campaign['project_languages'],
				$campaign['geotargeted'],
				$campaign['geo_countries'],
				$campaign['throttle'],
				$campaign['preferred'],
				$this->user
			);

			$banners = array();
			foreach ( $campaign['banners'] as $bannerSpec ) {
				$banner = $bannerSpec + static::$defaultBanner + array(
					'name' => 'TestBanner_' . rand(),
				);

				Banner::addTemplate(
					$banner['name'],
					$banner['body'],
					$this->user,
					$banner['displayAnon'],
					$banner['displayAccount'],
					$banner['fundraising'],
					$banner['autolink'],
					$banner['landingPages']
				);
				Campaign::addTemplateTo(
					$campaign['name'],
					$banner['name'],
					$banner['weight']
				);

				$banners[] = $banner;
			}
			$campaign['banners'] = $banners;

			$this->spec['campaigns'][] = $campaign;
		}
	}

	function removeFixtures() {
		if ( $this->spec ) {
			foreach ( $this->spec['campaigns'] as $campaign ) {
				foreach ( $campaign['banners'] as $banner ) {
					Campaign::removeTemplateFor( $campaign['name'], $banner['name'] );
					Banner::removeTemplate( $banner['name'], $this->user );
				}

				Campaign::removeCampaign( $campaign['name'], $this->user );
			}
		}
	}
}
