<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * Calculates banner allocation percentages
 */
class BannerAllocationCalculator {

	const LOGGED_IN = 0;
	const ANONYMOUS = 1;

	/**
	 * Calculate banner allocations given a list of banners filtered to a single
	 * device and bucket
	 *
	 * @param array $banners each banner should have the following keys
	 *	 'campaign' is the campaign name
	 *   'campaign_throttle' is the total traffic limit for the campaign
	 *   'weight' is the banner's weight within the campaign
	 *   'campaign_z_index' is the campaign priority
	 *   'allocation' is set by this function to a number between 0 and 1,
	 *     indicating the fraction of the time this banner will be chosen.
	 */
	public static function calculateAllocations( $banners ) {
		// Normalize banners to a proportion of the total campaign weight.
		$campaignTotalWeights = array();
		foreach ( $banners as $banner ) {
			if ( empty( $campaignTotalWeights[$banner['campaign']] ) ) {
				$campaignTotalWeights[$banner['campaign']] = 0;
			}
			$campaignTotalWeights[$banner['campaign']] += $banner['weight'];
		}
		foreach ( $banners as &$banner ) {
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
		foreach ( $banners as &$banner ) {
			$priority = $banner['campaign_z_index'];
			$priorityBanners[$priority][] = &$banner;

			if ( empty( $priorityTotalAllocations[$priority] ) ) {
				$priorityTotalAllocations[$priority] = 0;
			}
			$priorityTotalAllocations[$priority] += $banner['max_allocation'];
		}

		// Distribute allocation by priority.
		$remainingAllocation = 1.0;
		// Order by priority, descending.
		krsort( $priorityBanners );
		foreach ( $priorityBanners as $priority => $bannerSet ) {
			if ( $remainingAllocation <= 0.01 ) {
				// Don't show banners at lower priority levels if we've used up
				// the full 100% already.
				foreach ( $bannerSet as &$banner ) {
					$banner['allocation'] = 0;
				}
				continue;
			}

			if ( $priorityTotalAllocations[$priority] > $remainingAllocation ) {
				$scaling = $remainingAllocation / $priorityTotalAllocations[$priority];
				$remainingAllocation = 0;
			} else {
				$scaling = 1;
				$remainingAllocation -= $priorityTotalAllocations[$priority];
			}
			foreach ( $bannerSet as &$banner ) {
				$banner['allocation'] = $banner['max_allocation'] * $scaling;
			}
		}
		return $banners;
	}

	/**
	 * Allocation helper. Maps an array of campaigns with banners to a flattened
	 * list of banners, omitting those not available for the specified logged-in
	 * status, device and bucket.
	 *
	 * @param array $campaigns campaigns with banners as returned by
	 *   @see BannerChoiceDataProvider::getChoicesForCountry
	 *
	 * @param integer $status A status constant defined by this class (i.e.,
	 *   BannerAllocationCalculator::ANONYMOUS or
	 *   BannerAllocationCalculator::LOGGED_IN).
	 *
	 * @param string $device target device code
	 * @param integer $bucket target bucket number
	 *
	 * @return array banners with properties suitable for
	 *   @see BannerAllocationCalculator::calculateAllocations
	 */
	static function filterAndTransformBanners(
		$campaigns, $status, $device, $bucket ) {

		// Set which property we need to check to filter logged-in status
		switch ( $status ) {
			case self::ANONYMOUS:
				$status_prop = 'display_anon';
				break;

			case self::LOGGED_IN:
				$status_prop = 'display_account';
				break;

			default:
				throw new MWException( $this->status . 'is not a valid status '
						. 'for BannerAllocationsCalculator.' );
		}

		$banners = array();
		foreach( $campaigns as $campaign ) {
			foreach ( $campaign['banners'] as $banner ) {
				if ( !$banner[$status_prop] ) {
					continue;
				}
				if ( !in_array( $device, $banner['devices'] ) ) {
					continue;
				}
				if ( $bucket % $campaign['bucket_count'] != $banner['bucket'] ) {
					continue;
				}
				$banner['campaign'] = $campaign['name'];
				$banner['campaign_throttle'] = $campaign['throttle'];
				$banner['campaign_z_index'] = $campaign['preferred'];
				$banners[] = $banner;
			}
		}
		return $banners;
	}
}
