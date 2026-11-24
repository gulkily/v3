<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Http\RouteServices;
use ForumRewrite\Push\PushAfterResponse;
use ForumRewrite\Push\PushDelivery;
use ForumRewrite\Push\PushNotifier;
use ForumRewrite\Push\PushStorageConfig;
use ForumRewrite\Push\PushSubscriptionStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\TaskQueue\DetachedTaskQueueLauncher;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use ForumRewrite\TaskQueue\TaskQueueWorker;
use ForumRewrite\View\TemplateRenderer;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** How a recorded notice leaves: after the response on php-fpm, by a detached worker elsewhere, and by the queue. */
final class PushAfterResponseTest
{
    public function testNothingRecordedMeansNothingRuns(): void
    {
        assertSame(null, PushAfterResponse::run('/nonexistent', '/nonexistent', static fn (): bool => throw new RuntimeException('must not finish')));
    }

    public function testWithFastcgiTheSameRequestSendsAfterItsResponse(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PushSubscriptionStore($pdo);
        // A notice for a member with no device: the pass finishes it, so we can see it ran and how.
        $store->addPending(['kind' => 'reply', 'source_id' => 'r-1', 'recipient' => 'ilyag', 'tag' => 'thread:t', 'actor' => 'claude', 'body' => 'x', 'url' => '/posts/r-1', 'site' => 'https://zenmemes.com'], time());
        $finished = 0;
        PushAfterResponse::request(7);
        $route = PushAfterResponse::run('/nonexistent', '/nonexistent', static function () use (&$finished): bool {
            $finished++;
            return true;
        }, static fn (int $id): string => throw new RuntimeException('no launch needed'), static fn (): PushNotifier => new PushNotifier(
            $store,
            static fn (string $site): PushDelivery => throw new RuntimeException('no device to send to'),
            static fn (array $row): bool => false,
        ));
        assertSame(['after-response', 1, []], [$route, $finished, $store->pending()]);
        assertSame('after-response', $pdo->query("SELECT route FROM push_pending WHERE source_id = 'r-1'")->fetchColumn());
        assertSame(false, PushAfterResponse::requested(), 'one run per request');
    }

    public function testWithoutFastcgiADetachedWorkerRunsOnlyThatTask(): void
    {
        $launched = [];
        PushAfterResponse::request(42);
        assertSame('detached', PushAfterResponse::run('/nonexistent', '/nonexistent', static fn (): bool => false, static function (int $id) use (&$launched): string {
            $launched[] = $id;
            return 'launched';
        }));
        assertSame([42], $launched);
        PushAfterResponse::request(43);
        assertSame('launch-failed', PushAfterResponse::run('/nonexistent', '/nonexistent', static fn (): bool => false, static fn (int $id): string => 'failed'));

        $command = '';
        $launcher = new DetachedTaskQueueLauncher(dirname(__DIR__), '/tmp/queue.sqlite3', static function (string $candidate) use (&$command): bool {
            $command = $candidate;
            return true;
        });
        assertSame('launched', $launcher->launchForTask(42));
        assertSame(true, str_contains($command, ' run --quiet --task-id=42 --queue-database-path='), $command);
        assertSame('unavailable', $launcher->launchForTask(0));
    }

    public function testRecordingANoticeQueuesOneTaskAndRemembersTheSite(): void
    {
        $root = sys_get_temp_dir() . '/v3-push-after-' . bin2hex(random_bytes(6));
        mkdir($root . '/state/private', 0777, true);
        $server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'zenmemes.com';
        try {
            $services = new RouteServices(
                $root . '/read.sqlite3',
                new TemplateRenderer(__DIR__ . '/../templates', 'test'),
                'test',
                false,
                static fn (): ?array => null,
                __DIR__ . '/fixtures/parity_minimal_v1',
                $root,
                null,
                null,
                FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
                static fn (): ?array => null,
            );
            assertSame(0, $services->recordPushNotices(static fn (): int => 0));
            assertSame(false, PushAfterResponse::requested(), 'no notice, no task');
            assertSame(1, $services->recordPushNotices(static fn (): int => 1));
            assertSame(1, $services->recordPushNotices(static fn (): int => 1));
            assertSame(true, PushAfterResponse::requested());
            // The test runner may share one queue across tests, so count only this task type.
            $queued = (new PDO('sqlite:' . TaskQueueDatabaseConfig::path($root)))
                ->query("SELECT COUNT(*) FROM internal_tasks WHERE type = 'push_notify' AND status = 'queued'")->fetchColumn();
            assertSame(1, (int) $queued, 'two notices share one queued task');
            assertSame('https://zenmemes.com', PushSubscriptionStore::open(PushStorageConfig::databasePath($root))->site());
            assertSame(0, $services->recordPushNotices(static fn (): int => throw new RuntimeException('broken')), 'a failure is swallowed');
        } finally {
            $_SERVER = $server;
            PushAfterResponse::run($root, $root . '/read.sqlite3', static fn (): bool => false, static fn (int $id): string => 'failed');
            (new PDO('sqlite:' . TaskQueueDatabaseConfig::path($root)))->exec("DELETE FROM internal_tasks WHERE type = 'push_notify'");
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    public function testTheQueueTaskRequeuesWhileNoticesAreHeld(): void
    {
        $store = new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        $task = $store->enqueue(SqliteTaskQueueStore::PUSH_NOTIFY, 'push-notify');
        $remaining = true;
        $worker = new TaskQueueWorker($store, static function (): void {
        }, runPushNotify: static function () use (&$remaining): array {
            return ['sent' => 1, 'held' => $remaining ? 1 : 0, 'remaining' => $remaining];
        });
        assertSame(1, $worker->run()['continued']);
        assertSame('queued', $store->findById($task['id'])['status']);
        $remaining = false;
        assertSame(1, $worker->run()['completed']);
        assertSame('completed', $store->findById($task['id'])['status']);
        assertSame(true, str_contains((string) $store->latestTaskProgress($task['id'])['message'], 'sent=1'));

        $failing = new TaskQueueWorker($store, static function (): void {
        });
        $other = $store->enqueue(SqliteTaskQueueStore::PUSH_NOTIFY, 'push-notify');
        assertSame(1, $failing->run()['failed'], 'a worker without the push runner says so');
        assertSame('push_notify_unavailable', $store->findById($other['id'])['failure_code']);
    }
}
