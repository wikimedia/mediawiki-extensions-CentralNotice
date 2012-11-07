<?php

class BannerChooser {
	const SLOTS_KEY = 'slots';
	const ALLOCATION_KEY = 'allocation';
	const RAND_MAX = 30;

	var $banners = array();

	function __construct( $project, $language, $country, $anonymous, $bucket ) {
		$cndb = new CentralNoticeDB();
		$campaigns = $cndb->getCampaigns( $project, $language, $country );
		$this->banners = $cndb->getCampaignBanners( $campaigns );

		$this->filterBanners( $anonymous, $bucket );

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

	/**
	 * Filters banners and returns those matching criteria
	 */
	protected function filterBanners( $anonymous, $bucket ) {
		$filterColumn = function ( &$banners, $key, $value ) {
			$banners = array_filter(
				$banners,
				function( $banner ) use ( $key, $value ) {
					return ( $banner[ $key ] === $value );
				}
			);
		};

		if ( $anonymous !== null ) {
			$display_column = ( $anonymous ? 'display_anon' : 'display_account' );
			$filterColumn( $this->banners, $display_column, 1 );
		}

		// Always filter out lower Z-levels
		$highest_z = CentralNotice::LOW_PRIORITY;
		foreach ( $this->banners as $banner ) {
			$highest_z = max( $banner[ 'campaign_z_index' ], $highest_z );
		}
		$filterColumn( $this->banners, 'campaign_z_index', $highest_z );

		$this->banners = array_filter(
			$this->banners,
			function ( $banner ) use ( $bucket ) {
				if ( $banner[ 'campaign_num_buckets' ] == 1 ) {
					return true;
				}
				return ( $banner[ 'bucket' ] === intval( $bucket ) );
			}
		);

		// Reset the keys
		$this->banners = array_values( $this->banners );
	}

	// note: lumps all campaigns weights together according to absolute proportions of total.
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

		// First pass allocate the minimum number of slots to each banner
		$sum = 0;
		foreach ( $this->banners as &$banner ) {
			$slots = floor( ( $banner[ 'weight' ] / $total ) * self::RAND_MAX );
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
}
