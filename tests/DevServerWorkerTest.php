<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Support\DevServerWorker;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class DevServerWorkerTest
{
    public function testWorkerRunsRepeatedlyAndAQueuedTaskCompletes(): void
    {
        [$w, $env] = $this->workspace();
        $this->withEnvironment($env, function () use ($env): void {
            $worker = new DevServerWorker(dirname(__DIR__), 0.5);
            $worker->tick(0.0);
            assertSame(1, $worker->starts());
            $worker->tick(0.1);
            assertSame(1, $worker->starts(), 'no second start while one runs');
            $this->waitFor(fn (): bool => !$worker->running(), 30);
            $store = $this->store($env['FORUM_TASK_QUEUE_DATABASE_PATH']);
            assertSame('fresh', $store->executorHeartbeatStatus()['status']);

            $queued = $store->enqueue(SqliteTaskQueueStore::INSTANCE_IMPORT, 'instance-import-missing-request', 1);
            $worker->tick(0.2);
            assertSame(1, $worker->starts(), 'interval not yet passed');
            $worker->tick(1.0);
            assertSame(2, $worker->starts());
            $this->waitFor(fn (): bool => !$worker->running(), 30);
            assertSame('failed', $store->findById((int) $queued['id'])['status'], 'the queued task was claimed and run');
            assertSame(true, $worker->finish());
        });
    }

    public function testDevServerRunsTheWorkerUnlessToldNotToAndLeavesNoIdleWorker(): void
    {
        foreach ([[], ['--no-worker']] as $options) {
            [$w, $env] = $this->workspace();
            $port = $this->freePort();
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/scripts/dev_server.php', '--address=127.0.0.1:' . $port, '--quiet', ...$options],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                $env + ['V3_DEV_CONTROL' => '1', 'PATH' => (string) getenv('PATH')],
            );
            stream_set_blocking($pipes[1], false);
            $queuePath = $env['FORUM_TASK_QUEUE_DATABASE_PATH'];
            if ($options === []) {
                $this->waitFor(fn (): bool => is_file($queuePath) && $this->store($queuePath)->executorHeartbeatStatus()['status'] === 'fresh', 30);
            } else {
                usleep(3000000);
                assertTrue(!is_file($queuePath) || $this->store($queuePath)->executorHeartbeatStatus()['status'] !== 'fresh', 'no worker with --no-worker');
            }
            fwrite($pipes[0], "\n");
            $this->waitFor(static fn (): bool => !proc_get_status($process)['running'], 15);
            $output = (string) stream_get_contents($pipes[1]);
            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
            if ($options === []) {
                assertStringContains('task worker on', $output);
            } else {
                assertStringNotContains('task worker', $output);
            }
            $leftover = trim((string) shell_exec('ps -eo args | grep -F ' . escapeshellarg('task_queue.php run --quiet') . ' | grep -v grep || true'));
            assertTrue(!str_contains($leftover, $queuePath), $leftover);
        }
    }

    /** @return array{0: ImportTestWorkspace, 1: array<string, string>} */
    private function workspace(): array
    {
        $w = new ImportTestWorkspace();
        $repository = $w->repository('repository', true);
        $w->git($repository);
        mkdir($w->root . '/cache', 0700, true);

        return [$w, [
            'FORUM_REPOSITORY_ROOT' => $repository,
            'FORUM_DATABASE_PATH' => $w->root . '/cache/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/static',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/private/tasks.sqlite3',
        ]];
    }

    /** @param array<string, string> $env */
    private function withEnvironment(array $env, callable $body): void
    {
        $previous = [];
        foreach ($env as $name => $value) {
            $previous[$name] = getenv($name);
            putenv($name . '=' . $value);
        }
        try {
            $body();
        } finally {
            foreach ($previous as $name => $value) {
                $value === false ? putenv($name) : putenv($name . '=' . $value);
            }
        }
    }

    private function store(string $path): SqliteTaskQueueStore
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return new SqliteTaskQueueStore($pdo);
    }

    private function waitFor(callable $condition, int $seconds): void
    {
        $deadline = microtime(true) + $seconds;
        while (!$condition()) {
            if (microtime(true) > $deadline) throw new RuntimeException('Timed out waiting.');
            usleep(100000);
        }
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) stream_socket_get_name($socket, false), strrpos((string) stream_socket_get_name($socket, false), ':') + 1);
        fclose($socket);

        return $port;
    }
}
