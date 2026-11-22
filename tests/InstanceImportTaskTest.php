<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

final class InstanceImportTaskTest
{
    public function testWorkerRunsQueuedImportRecordsProgressAndRefusesASecondRequest(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($source . '/records/posts/queued-thread.txt', $w->post('queued-thread'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $queuePath = $w->root . '/private/tasks.sqlite3';
        [$store, $task] = $this->queueStore($queuePath);

        $queued = $task->queue($store, 'import', $server->url, 'openpgp:operator');
        assertTrue($queued['enqueued']);
        $second = $task->queue($store, 'resume', null, 'openpgp:operator');
        assertSame(false, $second['enqueued']);
        assertSame($queued['task_id'], $second['task_id']);
        $request = $task->request((string) $store->findById($queued['task_id'])['deduplication_key']);
        assertSame('import', $request['Action']);
        assertSame($server->url, $request['Source']);
        assertSame('0600', substr(sprintf('%o', fileperms($task->stateDirectory() . '/' . $store->findById($queued['task_id'])['deduplication_key'] . '.txt')), -4));

        $summary = $this->worker($store, $task, $w, $target)->run(1);
        assertSame(1, $summary['completed']);
        assertSame('completed', $store->findById($queued['task_id'])['status']);
        assertTrue(is_file($target . '/records/posts/queued-thread.txt'));
        $progress = array_column($store->recentTaskProgress(200), 'message');
        assertTrue(in_array('Import result: complete', $progress, true), implode("\n", $progress));
        $key = (string) $store->findById($queued['task_id'])['deduplication_key'];
        $seconds = \ForumRewrite\Import\ImportProgressView::decodeDurations((string) ($task->request($key)['Step-Seconds'] ?? ''));
        assertSame(['download', 'extract', 'plan', 'install', 'commit', 'publish', 'images'], array_keys($seconds));
        assertSame($seconds, $task->previousStepSeconds());
        assertSame([], $task->previousStepSeconds($key));
        $log = $task->log((string) $store->findById($queued['task_id'])['deduplication_key']);
        assertStringContains('Import result: complete', (string) $log);
    }

    public function testFailedSourceMarksTaskFailedWithTheLastLine(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($w->root . '/router.php', '<?php http_response_code(500); echo "nope";');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        [$store, $task] = $this->queueStore($w->root . '/private/tasks.sqlite3');
        $queued = $task->queue($store, 'import', $server->url, 'openpgp:operator');
        $summary = $this->worker($store, $task, $w, $target)->run(1);
        assertSame(1, $summary['failed']);
        $row = $store->findById($queued['task_id']);
        assertSame('failed', $row['status']);
        assertSame('instance_import_failed', $row['failure_code']);
        assertStringContains('Elapsed time', (string) $row['failure_message']);
    }

    public function testQueueRejectsBadInputAndCancelNeedsNoSource(): void
    {
        $w = new ImportTestWorkspace();
        [$store, $task] = $this->queueStore($w->root . '/private/tasks.sqlite3');
        foreach ([['import', ''], ['import', '--resume'], ['import', "a\nb"], ['wipe', 'x']] as [$action, $source]) {
            $failed = false;
            try { $task->queue($store, $action, $source, 'openpgp:operator'); } catch (InvalidArgumentException) { $failed = true; }
            assertTrue($failed, $action . ' ' . $source);
        }
        $queued = $task->queue($store, 'cancel', null, 'openpgp:operator');
        $request = $task->request((string) $store->findById($queued['task_id'])['deduplication_key']);
        assertSame('cancel', $request['Action']);
        assertSame('', $request['Source']);
        assertSame(null, $task->request('../etc/passwd'));
    }

    public function testQueuedImportsAreRememberedNewestFirstAndCapped(): void
    {
        $w = new ImportTestWorkspace();
        [$store, $task] = $this->queueStore($w->root . '/private/tasks.sqlite3');
        $queued = $task->queue($store, 'import', 'one.example', 'openpgp:operator');
        $store->claimNext(1); $store->markCompleted($queued['task_id']);
        $queued = $task->queue($store, 'import', 'https://two.example', 'openpgp:operator');
        $store->claimNext(1); $store->markCompleted($queued['task_id']);
        $queued = $task->queue($store, 'import', 'one.example', 'openpgp:operator');
        $store->claimNext(1); $store->markCompleted($queued['task_id']);
        $task->queue($store, 'cancel', null, 'openpgp:operator');
        assertSame(['one.example', 'https://two.example'], array_column($task->sources()->all(), 'source'));
        for ($i = 0; $i < 25; $i++) $task->sources()->remember('host' . $i . '.example');
        $all = $task->sources()->all();
        assertSame(\ForumRewrite\Import\InstanceImportSources::LIMIT, count($all));
        assertSame('host24.example', $all[0]['source']);
        $file = $task->stateDirectory() . '/sources.txt';
        assertStringContains("Source: host24.example\nLast-Used: ", (string) file_get_contents($file));
        assertSame('0600', substr(sprintf('%o', fileperms($file)), -4));
        assertTrue(!str_starts_with(realpath($file), realpath(dirname(__DIR__)) . '/state/local_repository'));
        chmod($file, 0000);
        if (!is_readable($file)) assertSame([], $task->sources()->all());
        chmod($file, 0600);
    }

    /** @return array{0: SqliteTaskQueueStore, 1: InstanceImportTask} */
    public function testRequestsQueueForANamedRequesterAndReportTheQueuedSteps(): void
    {
        // The local start page has no signed-in identity; it queues under a fixed name.
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        $queuePath = $w->root . '/private/tasks.sqlite3';
        $launches = 0;
        $requests = new \ForumRewrite\Import\InstanceImportRequests($target, $queuePath, static function () use (&$launches): string { $launches++; return 'launched'; }, static fn (): int => time());

        assertSame(400, $requests->perform('bogus', null, 'local-development')['code']);
        assertSame(409, $requests->perform('resume', null, 'local-development')['code']);
        assertSame(400, $requests->perform('import', '', 'local-development')['code']);
        $result = $requests->perform('import', 'http://127.0.0.1:1', 'local-development');
        assertSame(200, $result['code'], json_encode($result));
        assertSame(1, $launches);
        $state = $result['payload']['state'];
        assertTrue($state['busy']);
        assertSame('starting', $state['start']);
        assertSame('Starting the task worker', $state['steps'][0]['label']);
        assertSame('current', $state['steps'][0]['status']);
        assertSame('download', $state['steps'][1]['key']);
        assertTrue($state['timing'] !== null);
        [$store, $task] = $this->existingQueue($queuePath);
        $request = $task->request((string) $store->findById($state['task']['id'])['deduplication_key']);
        assertSame('local-development', $request['Requested-By']);
        assertSame(409, $requests->perform('import', 'http://127.0.0.1:1', 'local-development')['code']);
        assertSame(200, $requests->perform('status', null, 'local-development')['code']);
    }

    private function queueStore(string $queuePath): array
    {
        mkdir(dirname($queuePath), 0700, true);

        return $this->existingQueue($queuePath);
    }

    private function existingQueue(string $queuePath): array
    {
        $pdo = new PDO('sqlite:' . $queuePath);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return [new SqliteTaskQueueStore($pdo), InstanceImportTask::forQueue($queuePath)];
    }

    private function worker(SqliteTaskQueueStore $store, InstanceImportTask $task, ImportTestWorkspace $w, string $target): TaskQueueWorker
    {
        $projectRoot = dirname(__DIR__);

        return new TaskQueueWorker(
            $store,
            static function (): void {},
            runInstanceImport: static fn (array $claimed): array => $task->run($claimed, $projectRoot, [
                'repository_root' => $target,
                'database_path' => $w->root . '/cache/index.sqlite3',
                'static_html_root' => $w->root . '/static',
            ], static function (string $line) use ($store, $claimed): void {
                $store->recordTaskProgress((int) $claimed['id'], $line);
            }),
        );
    }
}
