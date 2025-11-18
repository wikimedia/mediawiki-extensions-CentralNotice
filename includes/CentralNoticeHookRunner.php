<?php

use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class CentralNoticeHookRunner implements
	CentralNoticeCampaignChangeHook
{

	public function __construct(
		private readonly HookContainer $hookContainer,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onCentralNoticeCampaignChange(
		$action,
		$time,
		$campaignName,
		$user,
		$beginSettings,
		$endSettings,
		$summary
	): void {
		$this->hookContainer->run(
			'CentralNoticeCampaignChange',
			[ $action, $time, $campaignName, $user, $beginSettings, $endSettings, $summary ],
			[ 'abortable' => false ]
		);
	}
}
