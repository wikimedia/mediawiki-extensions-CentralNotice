<?php

class BannerChooser {
	const SLOTS_KEY = 'slots';
	const ALLOCATION_KEY = 'allocation';
	const RAND_MAX = 30;

	protected $allocContext;

	protected $campaigns;
	protected $banners;

	/**
	 * @param array $campaigns structs of the type returned by getHistoricalCampaigns
	 * @param AllocationContext $allocContext used for filtering campaigns and banners
	 */
	function __construct( AllocationContext $allocContext, $campaigns = null ) {
		$this->allocContext = $allocContext;

		if ( $campaigns ) {
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
		$this->allocate();
	}

	/**
	 * @param $rand [1-RAND_MAX]
	 */
	function chooseBanner( $slot ) {
		// Convert slot to a float, [0-1]
		$slot = intval( $slot );
		if ( $slot < 1 || $slot > self::RAND_MAX ) {
			wfDebugLog( 'CentralNotice', "Illegal banner slot: {$slot}" );
			$slot = rand( 1, self::RAND_MAX );
		}

		// Choose a banner
		$counter = 0;
		foreach ( $this->banners as $banner ) {
			$counter += $banner[ self::SLOTS_KEY ];
			if ( $slot <= $counter ) {
				return $banner;
			}
		}

		// If there was some error, return the last banner (but only if we have banners to return!)
		if ( count( $this->banners ) ) {
			return $this->banners[ count( $this->banners ) - 1 ];
		}
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

	protected function filterBanners() {
		if ( $this->allocContext->getAnonymous() !== null ) {
			$display_column = ( $this->allocContext->getAnonymous() ? 'display_anon' : 'display_account' );
			$this->filterBannersOnColumn( $display_column, 1 );
		}

		// Always filter out lower Z-levels
		$highest_z = CentralNotice::LOW_PRIORITY;
		foreach ( $this->banners as $banner ) {
			$highest_z = max( $banner[ 'campaign_z_index' ], $highest_z );
		}
		$this->filterBannersOnColumn( 'campaign_z_index', $highest_z );

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
	 * Calculate allocation proportions and store them in the banners
	 *
	 * note: lumps all campaigns weights together according to absolute proportions of total.
	 */
	protected function allocate() {
		$total = array_reduce(
			$this->banners,
			function ( $result, $banner ) {
				return $result + $banner[ 'weight' ];
			},
			0
		);

		if ( $total === 0 ) {
			//TODO wfDebug
			return;
		}

		// Sort the banners by weight, smallest to largest - this helps in slot allocation
		// because we are not guaranteed to underallocate but we do want to attempt to give
		// one slot per banner
		usort( $this->banners, function( $a, $b ) {
				return ( $a[ 'weight' ] >= $b[ 'weight' ] ) ? 1 : -1;
			} );

		// First pass allocate the minimum number of slots to each banner, giving at least one
		// slot per banner up to RAND_MAX slots.
		$sum = 0;
		foreach ( $this->banners as &$banner ) {
			$slots = max( floor( ( $banner[ 'weight' ] / $total ) * self::RAND_MAX ), 1 );

			// Compensate for potential overallocation
			if ( $slots + $sum > self::RAND_MAX ) {
				$slots = self::RAND_MAX - $sum;
			}

			$banner[ self::SLOTS_KEY ] = $slots;
			$sum += $slots;
		}

		// Allocate each remaining slot one at a time to each banner
		$bannerIndex = 0;
		while ( $sum < self::RAND_MAX ) {
			$this->banners[ $bannerIndex ][ self::SLOTS_KEY ] += 1;
			$sum += 1;
			$bannerIndex = ( $bannerIndex + 1 ) % count( $this->banners );
		}

		// Determine allocation percentage
		foreach ( $this->banners as &$banner ) {
			$banner[ self::ALLOCATION_KEY ] = $banner[ self::SLOTS_KEY ] / self::RAND_MAX;
		}
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
