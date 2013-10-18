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
	 * Calculate allocation proportions and store them in the banners.
	 */
	protected function allocate() {
		// Normalize banners to a proportion of the total campaign weight.
		$campaignTotalWeights = array();
		foreach ( $this->banners as $banner ) {
			if ( empty( $campaignTotalWeights[$banner['campaign']] ) ) {
				$campaignTotalWeights[$banner['campaign']] = 0;
			}
			$campaignTotalWeights[$banner['campaign']] += $banner['weight'];
		}
		foreach ( $this->banners as &$banner ) {
			// Adjust the maximum allocation for the banner according to
			// campaign throttle settings.  The max_allocation would be
			// this banner's allocation if only one campaign were present.
			$banner['max_allocation'] = ( $banner['weight'] / $campaignTotalWeights[$banner['campaign']] )
				* ( $banner['campaign_throttle'] / 100.0 );
		}

		// Collect banners by priority level, and determine total desired
		// allocation for each level.
		$priorityTotalAllocations = array();
		$priorityBanners = array();
		foreach ( $this->banners as &$banner ) {
			$priorityBanners[$banner['campaign_z_index']][] = &$banner;

			if ( empty( $priorityTotalAllocations[$banner['campaign_z_index']] ) ) {
				$priorityTotalAllocations[$banner['campaign_z_index']] = 0;
			}
			$priorityTotalAllocations[$banner['campaign_z_index']] += $banner['max_allocation'];
		}

		// Distribute allocation by priority.
		$remainingAllocation = 1.0;
		// Order by priority, descending.
		krsort( $priorityBanners );
		foreach ( $priorityBanners as $z_index => $banners ) {
			if ( $remainingAllocation <= 0.01 ) {
				// Don't show banners at lower priority levels if we've used up
				// the full 100% already.
				foreach ( $banners as &$banner ) {
					$banner[self::ALLOCATION_KEY] = 0;
				}
				continue;
			}

			if ( $priorityTotalAllocations[$z_index] > $remainingAllocation ) {
				$scaling = $remainingAllocation / $priorityTotalAllocations[$z_index];
				$remainingAllocation = 0;
			} else {
				$scaling = 1;
				$remainingAllocation -= $priorityTotalAllocations[$z_index];
			}
			foreach ( $banners as &$banner ) {
				$banner[self::ALLOCATION_KEY] = $banner['max_allocation'] * $scaling;
			}

		}

		// To be deprecated by continuous allocation:
		$this->quantizeAllocationToSlots();
	}

	/**
	 * Take banner allocations in [0, 1] real form and convert to slots.
	 * Adjust the real form to reflect final slot numbers.
	 */
	function quantizeAllocationToSlots() {
		// Sort the banners by weight, smallest to largest.  This helps
		// prevent allocating zero slots to a banner, by rounding in
		// favor of the banners with smallest allocations.
		$alloc_key = self::ALLOCATION_KEY;
		usort( $this->banners, function( $a, $b ) use ( $alloc_key ) {
				return ( $a[$alloc_key] >= $b[$alloc_key] ) ? 1 : -1;
			} );

		// First pass: allocate the minimum number of slots to each banner,
		// giving at least one slot per banner up to RAND_MAX slots.
		$sum = 0;
		foreach ( $this->banners as &$banner ) {
			$slots = intval( max( floor( $banner[self::ALLOCATION_KEY] * self::RAND_MAX ), 1 ) );

			// Don't give any slots if the banner is hidden due to e.g. priority level
			if ( $banner[self::ALLOCATION_KEY] == 0 ) {
				$slots = 0;
			}

			// Compensate for potential overallocation
			if ( $slots + $sum > self::RAND_MAX ) {
				$slots = self::RAND_MAX - $sum;
			}

			$banner[self::SLOTS_KEY] = $slots;
			$sum += $slots;
		}

		// Second pass: allocate each remaining slot one at a time to each
		// banner if they are underallocated
		foreach ( $this->banners as &$banner ) {
			if ( $sum >= self::RAND_MAX ) {
				break;
			}
			if ( ( $banner[self::ALLOCATION_KEY] * self::RAND_MAX ) > $banner[self::SLOTS_KEY] ) {
				$banner[self::SLOTS_KEY] += 1;
				$sum += 1;
			}
		}

		// Refresh allocation levels according to quantization
		foreach ( $this->banners as &$banner ) {
			$banner[self::ALLOCATION_KEY] = $banner[self::SLOTS_KEY] / self::RAND_MAX;
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
