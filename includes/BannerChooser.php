<?php

class BannerChooser {
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
		$p = $slot / self::RAND_MAX;

		// Choose a banner
		$counter = 0;
		foreach ( $this->banners as $banner ) {
			$counter += $banner[ self::ALLOCATION_KEY ];
			if ( $p <= $counter ) {
				return $banner;
			}
		}
		// If there is floating-point precision error, we were close to 1.0,
		// so use the last banner.
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

		foreach ( $this->banners as &$banner ) {
			$banner[ self::ALLOCATION_KEY ] = $banner[ 'weight' ] / $total;
		}

		// Quantize to RAND_MAX to give the effective allocations.
		// Get the closest slot boundary and round to that.
		$quantum = 1 / self::RAND_MAX;
		$sum = 0;
		foreach ( $this->banners as &$banner ) {
			$sum += $banner[ self::ALLOCATION_KEY ];
			$error = $sum - round( $sum / $quantum ) * $quantum;
			$banner[ self::ALLOCATION_KEY ] -= $error;
			$sum -= $error;
		}
		// Arbitrarily move the final banner to 1.0 so we don't have a dead zone.
		if ( count( $this->banners ) ) {
			$this->banners[ count( $this->banners ) - 1 ][ self::ALLOCATION_KEY ] += 1.0 - $sum;
		}
	}
}
