<?php

use MediaWiki\User\User;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralNoticeCampaignChange" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralNoticeCampaignChangeHook {
	/**
	 * @param string $action 'created', 'modified', or 'removed'
	 * @param string $time Database depending timestamp of the modification
	 * @param string $campaignName Name of the campaign
	 * @param User $user User causing the change
	 * @param array $beginSettings array of campaign settings before changes
	 * @param array $endSettings array of campaign settings after changes
	 * @param string $summary Change summary provided by the user
	 */
	public function onCentralNoticeCampaignChange(
		$action,
		$time,
		$campaignName,
		$user,
		$beginSettings,
		$endSettings,
		$summary
	): void;
}
