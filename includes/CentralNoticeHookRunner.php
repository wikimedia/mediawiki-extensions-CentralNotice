<?php

use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class CentralNoticeHookRunner implements
	CentralNoticeCampaignChangeHook
{
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
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
