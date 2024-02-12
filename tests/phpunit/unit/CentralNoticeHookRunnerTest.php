<?php

use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \CentralNoticeHookRunner
 */
class CentralNoticeHookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield CentralNoticeHookRunner::class => [ CentralNoticeHookRunner::class ];
	}
}
