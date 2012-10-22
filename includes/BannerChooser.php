<?php

class BannerChooser {
	const ALLOCATION_KEY = 'allocation';

	var $banners = array();

	function __construct( $project, $language, $country, $anonymous /*, $bucket */ ) {
		$cndb = new CentralNoticeDB();
		$campaigns = $cndb->getCampaigns( $project, $language, $country );
		$this->banners = $cndb->getCampaignBanners( $campaigns );

		$this->filterBanners( $anonymous );

		$this->allocate();
	}

	/**
	 * @param float $rand [0-1]
	 */
	function chooseBanner( $rand ) {
		$counter = 0;
		foreach ( $this->banners as $banner ) {
			$counter += $banner[ self::ALLOCATION_KEY ];
			if ( $rand <= $counter ) {
				return $banner;
			}
		}
	}

	/**
	 * Filters banners and returns those matching criteria
	 */
	protected function filterBanners( $anonymous = null /* TODO $bucket */ ) {
		$filterColumn = function ( &$banners, $key, $value ) {
			$banners = array_filter(
				$banners,
				function( $banner ) use ( $key, $value ) {
					return ( $banner[ $key ] === $value );
				}
			);
		};

		if ( $anonymous !== null ) {
			$display_column = $anonymous ? 'display_anon' : 'display_account';
			$filterColumn( $this->banners, $display_column, 1 );
		}

		// Always filter out lower Z-levels
		$highest_z = CentralNotice::LOW_PRIORITY;
		foreach ( $this->banners as $banner ) {
			$highest_z = max( $banner[ 'campaign_z_index' ], $highest_z );
		}
		$filterColumn( $this->banners, 'campaign_z_index', $highest_z );
	}

	// note: lumps all campaigns weights together according to absolute proportions of total.
	protected function allocate() {
		$total_weight = array_reduce(
			$this->banners,
			function ( $result, $banner ) {
				return $result + $banner[ 'weight' ];
			},
			0
		);

		if ( $total_weight === 0 ) {
			//TODO wfDebug
			return;
		}

		// Construct the relative weights
		foreach ( $this->banners as &$banner ) {
			$banner[ self::ALLOCATION_KEY ] = $banner[ 'weight' ] / $total_weight;
		}
	}
}
