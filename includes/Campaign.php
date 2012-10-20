<?php

class Campaign {
	function __construct( $name ) {
		$this->name = $name;
	}

	function getSettings() {
		$cndb = new CentralNoticeDB();
		$settings = $cndb->getCampaignSettings( $this->name, true );
		$settings[ 'enabled' ] = ( $settings[ 'enabled' ] == '1' );
		$settings[ 'locked' ] = ( $settings[ 'locked' ] == '1' );
		$settings[ 'geotargeted' ] = ( $settings[ 'geo' ] == '1' );
		$settings[ 'priority' ] = $settings[ 'preferred' ];
		return $settings;
	}

	function updateSettings( $user, $params ) {
		$result = array();

		$cndb = new CentralNoticeDB();
		$initialCampaignSettings = $cndb->getCampaignSettings( $this->name, true );

		// Handle locking/unlocking campaign
		if ( array_key_exists( 'locked', $params ) ) {
			$cndb->setBooleanCampaignSetting( $this->name, 'locked', $params[ 'locked' ] );
		}

		// Handle enabling/disabling campaign
		if ( array_key_exists( 'enabled', $params ) ) {
			$cndb->setBooleanCampaignSetting( $this->name, 'enabled', $params[ 'enabled' ] );
		}

		if ( array_key_exists( 'priority', $params ) ) {
			// Handle setting campaign priority
			$cndb->setNumericCampaignSetting(
				$this->name,
				'preferred',
				$params[ 'priority' ],
				CentralNotice::EMERGENCY_PRIORITY,
				CentralNotice::LOW_PRIORITY
			);
		}

		// Handle updating geotargeting
		if ( array_key_exists( 'geotargeted', $params ) ) {
			if ( $params[ 'geotargeted' ] ) {
				$cndb->setBooleanCampaignSetting( $this->name, 'geo', 1 );
				if ( array_key_exists( 'countries', $params ) &&
					$params[ 'countries' ] )
				{
					$cndb->updateCountries( $this->name, $params[ 'countries' ] );
				}
			} else {
				$cndb->setBooleanCampaignSetting( $this->name, 'geo', 0 );
			}
		}

		// Handle updating the start and end settings
		if ( array_key_exists( 'start', $params ) &&
			array_key_exists( 'end', $params ) )
		{
			if ( $params[ 'start' ] && $params[ 'end' ] ) {
				$cndb->updateNoticeDate( $this->name, $params[ 'start' ], $params[ 'end' ] );
			}
		}

		// Handle adding of banners to the campaign
		if ( array_key_exists( 'addBanners', $params ) &&
			array_key_exists( 'weight', $params ) )
		{
			if ( $params[ 'addBanners' ] && $params[ 'weight' ] ) {
				foreach ( $params[ 'addBanners' ] as $bannerName ) {
					$templateId = $cndb->getTemplateId( $bannerName );
					$result = $cndb->addTemplateTo(
						$this->name, $bannerName, $params[ 'weight' ][ $templateId ]
					);
					if ( $result !== true ) {
						$errors[] = $result;
					}
				}
			}
		}

		// Handle removing of banners from the campaign
		if ( array_key_exists( 'removeBanners', $params ) ) {
			foreach ( $params[ 'removeBanners' ] as $bannerName ) {
				$cndb->removeTemplateFor( $this->name, $bannerName );
			}
		}

		// Handle weight changes
		if ( array_key_exists( 'weight', $params ) {
			foreach ( $params[ 'weight' ] as $bannerId => $weight ) {
				$cndb->updateWeight( $this->name, $bannerId, $weight );
			}
		}

		// Handle new projects
		if ( array_key_exists( 'projects', $params ) &&
			$params[ 'projects' ] )
		{
			$cndb->updateProjects( $this->name, $params[ 'projects' ] );
		}

		// Handle new project languages
		if ( array_key_exists( 'project_languages', $params ) &&
			$params[ 'project_languages' ] )
		{
			$cndb->updateProjectLanguages( $this->name, $params[ 'project_languages' ] );
		}

		$finalCampaignSettings = $cndb->getCampaignSettings( $this->name );
		$campaignId = $cndb->getNoticeId( $this->name );
		$cndb->logCampaignChange( 'modified', $campaignId, $user,
			$initialCampaignSettings, $finalCampaignSettings );

		return $result;
	}
}
