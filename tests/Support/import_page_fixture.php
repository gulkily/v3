<?php
// Prints the Tools → Import page for a browser check that serves it from memory:
// php tests/Support/import_page_fixture.php <idle|running|result|pending|signed-out>
declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';
require dirname(__DIR__) . '/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Http\ImportController;
use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

putenv('FORUM_SITE_ID=zenmemes');
putenv('FORUM_INSTANCE_IMPORT_PAGE_ENABLED=1');
$state = $argv[1] ?? 'idle';
$base = sys_get_temp_dir() . '/import-page-fixture-' . bin2hex(random_bytes(6));
$imports = $base . '/repo/.git/instance-import';
mkdir($imports, 0700, true);
$queuePath = $base . '/private/tasks.sqlite3';
mkdir(dirname($queuePath), 0700, true);
$store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
$task = InstanceImportTask::forQueue($queuePath);
$longSource = 'https://a-community-with-a-rather-long-hostname.example.org/forum/base-path';

if ($state === 'running') {
    $queued = $task->queue($store, 'import', $longSource, 'openpgp:x');
    $task->sources()->remember('qdb.us');
    $store->claimNext();
    foreach (['Import time limit: 300 seconds', 'Source: ' . $longSource, 'Downloading repository archive...', 'Progress: download 0/?', 'Progress: download 48211/48211', 'Downloaded 48211 bytes; validating and extracting archive...', 'Progress: extract', 'Planning public records, dependencies, and conflicts...', 'Progress: plan', 'Installing 7172 record file(s)...', 'Progress: install 0/7172', 'Progress: install 1200/7172'] as $line) {
        $store->recordTaskProgress($queued['task_id'], $line);
    }
}
if ($state === 'result' || $state === 'pending') {
    $run = $imports . '/20261116UTC120000-aaaaaaaaaaaa';
    mkdir($run, 0700);
    $entries = ['records/posts/a.txt' => ['state' => 'import']];
    for ($i = 0; $i < 40; $i++) {
        $entries['records/posts/thread-with-a-long-identifier-' . $i . '.txt'] = ['state' => 'conflict', 'reason' => 'local copy differs', 'root_cause' => 'records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'];
    }
    file_put_contents($run . '/report.json', json_encode([
        'status' => 'partial', 'source' => $longSource, 'review_path' => $run,
        'counts' => ['import' => 412, 'duplicate' => 1830, 'conflict' => 40, 'invalid' => 0, 'unsupported' => 0, 'excluded' => 3],
        'review_causes' => array_merge(
            [['path' => 'records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt', 'state' => 'conflict', 'reason' => 'local copy differs', 'affected_count' => 40]],
            array_map(static fn (int $i): array => ['path' => 'records/thread-labels/thread-label-' . $i . '.txt', 'state' => 'invalid', 'reason' => 'Missing dependency: post thread-' . $i, 'affected_count' => 1], range(1, 193)),
        ),
        'exclusion_reasons' => ['private message envelope' => 3],
        'entries' => $entries,
    ]));
    $queued = $task->queue($store, 'import', $longSource, 'openpgp:x');
    $store->claimNext();
    $store->recordTaskProgress($queued['task_id'], 'Images: complete');
    $store->markCompleted($queued['task_id']);
    $task->sources()->remember('qdb.us');
}
if ($state === 'pending') {
    mkdir($imports . '/20261116UTC130000-bbbbbbbbbbbb', 0700);
    file_put_contents($imports . '/pending.json', json_encode(['id' => '20261116UTC130000-bbbbbbbbbbbb', 'phase' => 'prepared', 'report' => ['source' => 'qdb.us']]));
}

$_COOKIE = [];
if ($state !== 'signed-out') {
    TestViewerSession::signIn('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
}
$application = new Application(dirname(__DIR__, 2), dirname(__DIR__) . '/fixtures/parity_minimal_v1', $base . '/index.sqlite3');
ob_start();
$application->handle('GET', '/threads/');
ob_end_clean();
$routeServices = (fn () => $this->routeServices())->call($application);
echo (new ImportController($routeServices, $base . '/repo', $queuePath))->render();
TestViewerSession::reset();
