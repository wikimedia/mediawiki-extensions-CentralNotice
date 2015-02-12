<?php

class BannerChooser {
	const ALLOCATION_KEY = 'allocation';

	protected $allocContext;

	protected $campaigns;
	protected $banners;

	/**
	 * @param array $campaigns structs of the type returned by getHistoricalCampaigns
	 * @param AllocationContext $allocContext used for filtering campaigns and banners
	 */
	function __construct( AllocationContext $allocContext, $campaigns = null ) {
		$this->allocContext = $allocContext;

		if ( $campaigns !== null ) {
			$this->campaigns = $campaigns;

			$this->banners = array();
			$this->filterCampaigns();
			foreach ( $this->campaigns as $campaign ) {
				foreach ( $campaign['banners'] as $name => $banner ) {
					$this->banners[] = $banner;
				}
			}
		} else {
			$this->campaigns = Campaign::getCampaigns(
				$allocContext->getProject(),
				$allocContext->getLanguage(),
				$allocContext->getCountry()
			);
			$this->banners = Banner::getCampaignBanners( $this->campaigns );
		}
		$this->filterBanners();
		$this->banners = BannerAllocationCalculator::calculateAllocations( $this->banners );
	}

	protected function filterCampaigns() {
		$filtered = array();
		foreach ( $this->campaigns as $campaign ) {
			$projectAllowed = (
				!$this->allocContext->getProject()
				or in_array( $this->allocContext->getProject(), $campaign['projects'] )
			);
			$languageAllowed = (
				!$this->allocContext->getLanguage()
				or in_array( $this->allocContext->getLanguage(), $campaign['languages'] )
			);
			$countryAllowed = (
				!$this->allocContext->getCountry()
				or !$campaign['geo']
				or in_array( $this->allocContext->getCountry(), $campaign['countries'] )
			);
			if ( $projectAllowed and $languageAllowed and $countryAllowed ) {
				$filtered[] = $campaign;
			}
		}
		$this->campaigns = $filtered;
	}

	/**
	 * From the selected group of banners we wish to now filter only for those that
	 * are relevant to the user. The banners choose if they display to anon/logged
	 * out, what device, and what bucket. They must also take into account their
	 * campaigns priority level.
	 *
	 * Logged In/Out and device are considered independent of the campaign priority
	 * for allocation purposes so are filtered for first.
	 *
	 * Then we filter for campaign dependent variables -- primarily the priority
	 * followed by the banner bucket.
	 */
	protected function filterBanners() {
		// Filter on Logged
		if ( $this->allocContext->getAnonymous() !== null ) {
			$display_column = ( $this->allocContext->getAnonymous() ? 'display_anon' : 'display_account' );
			$this->filterBannersOnColumn( $display_column, 1 );
		}

		// Filter for device category
		if ( $this->allocContext->getDevice() ) {
			$this->filterBannersOnColumn( 'device', $this->allocContext->getDevice() );
		}

		// Filter for the provided bucket.
		$bucket = $this->allocContext->getBucket();
		$this->banners = array_filter(
			$this->banners,
			function ( $banner ) use ( $bucket ) {
				global $wgNoticeNumberOfBuckets;

				// In case we change the number of buckets available, will map
				// the banner bucket down
				$bannerBucket = intval( $banner[ 'bucket' ] ) % $wgNoticeNumberOfBuckets;

				// Actual mapping. It is assumed the user always was randomly choosing out
				// of a ring with $wgNoticeNumberOfBuckets choices. This implies that we will
				// always be mapping the ring down, never up.
				$userBucket = intval( $bucket ) % intval( $banner[ 'campaign_num_buckets' ] );

				return ( $bannerBucket === $userBucket );
			}
		);

		// Reset the keys
		$this->banners = array_values( $this->banners );
	}

	protected function filterBannersOnColumn( $key, $value ) {
		$this->banners = array_filter(
			$this->banners,
			function( $banner ) use ( $key, $value ) {
				return ( $banner[$key] === $value );
			}
		);
	}

	/**
	 * @return array of campaigns after filtering on criteria
	 */
	function getCampaigns() {
		return $this->campaigns;
	}

	/**
	 * @return array of banners after filtering on criteria
	 */
	function getBanners() {
		return $this->banners;
	}
}
