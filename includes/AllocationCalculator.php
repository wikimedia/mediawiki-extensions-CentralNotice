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
 * Calculates banner and campaign allocation percentages for use in
 * Special:BannerAllocation and Special:GlobalAllocation. The actual
 * calculations used to decide which banner is shown are performed on the
 * client. Most methods here closely mirror the client-side methods for that,
 * found in ext.centralNotice.display.chooser.js (exposed in JS as
 * cn.internal.chooser).
 */
class AllocationCalculator {

	const LOGGED_IN = 0;
	const ANONYMOUS = 1;

	/**
	 * Filter an array in the format output by
	 * ChoiceDataProvider::getChoices(), based on country, logged-in
	 * status and device. This method is the server-side equivalent of
	 * mw.cnBannerControllerLib.filterChoiceData(). (However, this method does
	 * not perform campaign freshness checks like the client-side one.)
	 *
	 * @param array $choiceData Campaigns with banners as returned by
	 *   ChoiceDataProvider::getChoices(). This array will be modified.
	 *
	 * @param string $country Country of interest
	 *
	 * @param integer $status A status constant defined by this class (i.e.,
	 *   AllocationCalculator::ANONYMOUS or
	 *   AllocationCalculator::LOGGED_IN).
	 *
	 * @param string $device target device code
	 */
	public static function filterChoiceData( &$choiceData, $country, $status, $device ) {

		$filteredChoiceData = array();

		foreach ( $choiceData as $campaign ) {

			$keepCampaign = false;

			// Filter for country if geotargeted
			if ( $campaign['geotargeted'] &&
				!in_array( $country, $campaign['countries'] ) ) {

				continue;
			}

			// Now filter by banner logged-in status and device
			foreach ( $campaign['banners'] as $banner ) {

				// Logged-in status
				if ( $status === AllocationCalculator::ANONYMOUS &&
					!$banner['display_anon'] ) {
					continue;
				}
				if ( $status === AllocationCalculator::LOGGED_IN &&
					!$banner['display_account'] ) {
					continue;
				}

				// Device
				if ( !in_array( $device, $banner['devices'] ) ) {
					continue;
				}

				// We get here if the campaign targets the requested country,
				// and has at least one banner for the requested logged-in status
				// and device.
				$keepCampaign = true;
				break;
			}

			if ( $keepCampaign ) {
				$filteredChoiceData[] = $campaign;
			}
		}
		$choiceData = $filteredChoiceData;
	}

	/**
	 * On $filteredChoiceData calculate the probability that the user has
	 * of receiving each campaign in this.choiceData. This takes into account
	 * campaign priority and throttling. The equivalent client-side method
	 * is mw.cnBannerControllerLib.calculateCampaignAllocations().
	 *
	 * @param array $filteredChoiceData Data in the format provided by
	 *   filteredChoiceData().
	 */
	public static function calculateCampaignAllocations( &$filteredChoiceData ) {

		// Make an index of campaigns by priority level.
		// Note that the actual values of priority levels are integers,
		// and higher integers represent higher priority. These values are
		// defined by class constants in CentralNotice.

		$campaignsByPriority = array();
		foreach ( $filteredChoiceData as &$campaign ) {
			$priority = $campaign['preferred'];
			$campaignsByPriority[$priority][] = &$campaign;
		}

		// Sort the index by priority, in descending order
		krsort( $campaignsByPriority );

		// Now go through the priority levels from highest to lowest. If
		// campaigns are not throttled, then campaigns with a higher
		// priority level will eclipse all campaigns with lower priority.
		// Only if some campaigns are throttled will they allow some space
		// for campaigns at the next level down.

		$remainingAllocation = 1;

		foreach ( $campaignsByPriority as $priority => &$campaignsAtThisPriority ) {

			// If we fully allocated at a previous level, set allocations
			// at this level to zero. (We check with 0.01 instead of 0 in
			// case of issues due to finite precision.)
			if ( $remainingAllocation < 0.01 ) {
				foreach ( $campaignsAtThisPriority as &$campaign ) {
					$campaign['allocation'] = 0;
				}
				continue;
			}

			// If we are here, there is some allocation remaining.

			// All campaigns at a given priority level are alloted the same
			// allocation, unless they are throttled, in which case the
			// throttling value (taken as a percentage of the whole
			// allocation pie) is their maximum possible allocation.

			// To calculate this, we'll loop through the campaigns at this
			// level in order from the most throttled (lowest throttling
			// value) to the least throttled (highest value) and on each
			// loop, we'll re-calculate the remaining total allocation and
			// the proportional (i.e. unthrottled) allocation available to
			// each campaign.

			// First, sort the campaigns by throttling value (ascending)

			usort( $campaignsAtThisPriority, function ( $a, $b ) {
				if ( $a['throttle'] < $b['throttle'] ) {
					return -1;
				}
				if ( $a['throttle'] > $b['throttle'] ) {
					return 1;
				}
				return 0;
			} );

			$campaignsAtThisPriorityCount = count( $campaignsAtThisPriority );
			foreach ( $campaignsAtThisPriority as $i => &$campaign ) {

				// Calculate the proportional, unthrottled allocation now
				// available to a campaign at this level.
				$currentFullAllocation =
					$remainingAllocation / ( $campaignsAtThisPriorityCount - $i );

				// A campaign may get the above amount, or less, if
				// throttling indicates that'd be too much.
				$actualAllocation =
					min( $currentFullAllocation, $campaign['throttle'] / 100 );

				$campaign['allocation'] = $actualAllocation;

				// Update remaining allocation
				$remainingAllocation -= $actualAllocation;
			}
		}
	}

	/**
	 * Filter banners for $campaign on $bucket, $status and $device, and return
	 * a list of possible banners for this context. The equivalent client-side method
	 * is mw.cnBannerControllerLib.makePossibleBanners().
	 *
	 * @param array $campaign Campaign data in the format of a single entry
	 *   in the array provided by filteredChoiceData().
	 *
	 * @param int $bucket Bucket of interest
	 *
	 * @param integer $status A status constant defined by this class (i.e.,
	 *   AllocationCalculator::ANONYMOUS or
	 *   AllocationCalculator::LOGGED_IN).
	 *
	 * @param string $device target device code
	 */
	public static function makePossibleBanners( $campaign, $bucket, $status, $device ) {

		$banners = array();

		foreach ( $campaign['banners'] as $banner ) {

			// Filter for bucket
			if ( $bucket % $campaign['bucket_count'] != $banner['bucket'] ) {
				continue;
			}

			// Filter for logged-in status
			if ( $status === AllocationCalculator::ANONYMOUS &&
				!$banner['display_anon'] ) {
				continue;
			}
			if ( $status === AllocationCalculator::LOGGED_IN &&
				!$banner['display_account'] ) {
				continue;
			}

			// Filter for device
			if ( !in_array( $device, $banner['devices'] ) ) {
				continue;
			}

			$banners[] = $banner;
		}

		return $banners;
	}

	/**
	 * Calculate the allocation of banners in a single campaign, based on
	 * relative weights. The equivalent client-side method is
	 * mw.cnBannerControllerLib.calculateBannerAllocations().
	 */
	public static function calculateBannerAllocations( &$banners ) {

		$totalWeights = 0;

		// Find the sum of all banner weights
		foreach ( $banners as $banner ) {
			$totalWeights += $banner['weight'];
		}

		// Set allocation property to the normalized weight
		foreach ( $banners as &$banner ) {
			$banner['allocation'] = $banner['weight'] / $totalWeights;
		}
	}

	/**
	 * Provide a list of allocated banners from a list of campaigns, filtering
	 * on the criteria provided.
	 *
	 * @param string $country Country of interest
	 *
	 * @param integer $status A status constant defined by this class (i.e.,
	 *   AllocationCalculator::ANONYMOUS or
	 *   AllocationCalculator::LOGGED_IN).
	 *
	 * @param string $device target device code
	 *
	 * @param int $bucket Bucket of interest
	 *
	 * @param array $campaigns Campaigns with banners as returned by
	 *   ChoiceDataProvider::getChoices() or
	 *   Campaign::getHistoricalCampaigns
	 *
	 * @return array
	 */
	public static function filterAndAllocate(
		$country, $status, $device, $bucket, $campaigns ) {

		// Filter and determine campaign allocation
		AllocationCalculator::filterChoiceData(
			$campaigns,
			$country,
			$status,
			$device
		);

		AllocationCalculator::calculateCampaignAllocations( $campaigns );

		// Go through all campaings to make a flat list of banners from all of
		// them, and calculate overall relative allocations.
		$possibleBannersAllCampaigns = array();
		foreach ( $campaigns as $campaign ) {

			$possibleBanners = AllocationCalculator::makePossibleBanners(
				$campaign,
				$bucket,
				$status,
				$device
			);

			AllocationCalculator::calculateBannerAllocations( $possibleBanners );

			foreach ( $possibleBanners as $banner ) {
				$banner['campaign'] = $campaign['name'];

				$banner['allocation'] =
					$banner['allocation'] * $campaign['allocation'];

				$possibleBannersAllCampaigns[] = $banner;
			}
		}

		return $possibleBannersAllCampaigns;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function getLoggedInStatusFromString( $s ) {
		switch ( $s ) {
			case 'anonymous':
				return AllocationCalculator::ANONYMOUS;
			case 'logged_in':
				return AllocationCalculator::LOGGED_IN;
			default:
				throw new InvalidArgumentException( 'Invalid logged-in status.' );
		}
	}
}
