<?php

use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \CentralNoticeHookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield CentralNoticeHookRunner::class => [ CentralNoticeHookRunner::class ];
	}
}
