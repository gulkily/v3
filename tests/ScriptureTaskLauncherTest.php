<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

final class ScriptureTaskLauncherTest
{
    public function testDispatchQuotesPathsTargetsOneTaskAndWaitsForBusyWorker(): void
    {
        $command = '';
        $launcher = new \ForumRewrite\TaskQueue\ScriptureTaskLauncher(dirname(__DIR__), '/tmp/repo with spaces',
            '/tmp/db', '/tmp/public', '/tmp/static', '/tmp/queue', function ($text) use (&$command) { $command = $text; return true; });
        assertSame('launched', $launcher->launch(12));
        assertStringContains("'--task-id=12'", $command);
        assertStringContains("'--lock-wait-seconds=60'", $command);
        assertStringContains("'--repository-root=/tmp/repo with spaces'", $command);
        assertSame('unavailable', $launcher->launch(0));
    }

    public function testUnavailableAndFailedLaunchesLeaveRecoveryToQueue(): void
    {
        $failed = new \ForumRewrite\TaskQueue\ScriptureTaskLauncher(dirname(__DIR__), '/tmp/r', '/tmp/d', '/tmp/a', '/tmp/s', '/tmp/q', fn () => false);
        assertSame('failed', $failed->launch(1));
        $unavailable = new \ForumRewrite\TaskQueue\ScriptureTaskLauncher('/missing/application', '/tmp/r', '/tmp/d', '/tmp/a', '/tmp/s', '/tmp/q');
        assertSame('unavailable', $unavailable->launch(1));
    }
}
