<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\DetachedTaskQueueLauncher;

final class DetachedTaskQueueLauncherTest
{
    public function testLauncherUsesOnlyTheApplicationQueueWorkerWhenEnabled(): void
    {
        $previous = getenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED');
        putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED=true');
        $command = '';

        try {
            $launcher = new DetachedTaskQueueLauncher(
                dirname(__DIR__),
                '/tmp/private queue.sqlite3',
                static function (string $candidate) use (&$command): bool {
                    $command = $candidate;
                    return true;
                },
            );

            assertSame('launched', $launcher->launchQueuedWorker());
        } finally {
            $previous === false ? putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED') : putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED=' . $previous);
        }

        assertStringContains('scripts/task_queue.php', $command);
        assertStringContains('run --quiet --limit=1', $command);
        assertStringContains('--queue-database-path=', $command);
        assertStringNotContains('FORUM_TASK_QUEUE_EMERGENCY_LAUNCHER', $command);
    }

    public function testLauncherDoesNotStartAProcessWhenDisabled(): void
    {
        $previous = getenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED');
        putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED');
        $started = false;

        try {
            $launcher = new DetachedTaskQueueLauncher(
                dirname(__DIR__),
                '/tmp/queue.sqlite3',
                static function () use (&$started): bool {
                    $started = true;
                    return true;
                },
            );

            assertSame('disabled', $launcher->launchQueuedWorker());
        } finally {
            $previous === false ? putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED') : putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED=' . $previous);
        }

        assertSame(false, $started);
    }
}
