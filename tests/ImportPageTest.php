<?php

declare(strict_types=1);

use ForumRewrite\Import\InstanceImportStatus;

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

final class ImportPageTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testPageIsRootOnlyAndExistsOnlyWithTheFlag(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

$databasePath = sys_get_temp_dir() . '/import-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . sys_get_temp_dir() . '/import-page-missing-' . bin2hex(random_bytes(6)) . '/tasks.sqlite3');
$render = static function (Application $app, string $path): string {
    http_response_code(200);
    ob_start();
    $app->handle('GET', $path);
    return (string) ob_get_clean();
};
try {
    putenv('FORUM_INSTANCE_IMPORT_PAGE_ENABLED=1');
    $_COOKIE = [];
    TestViewerSession::signIn($argv[3]);
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $page = $render($app, '/tools/import/');
    foreach (['<h1>Import</h1>', 'Last import', 'href="/tools/import/"'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Operator page missing: ' . $needle);
    }
    $tools = $render($app, '/tools/');
    if (!str_contains($tools, 'class="tool-launcher-button" href="/tools/import/"')) throw new RuntimeException('Tools launcher does not list Import.');

    TestViewerSession::reset();
    $_COOKIE = ['identity_hint' => $argv[3]];
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $hinted = $render($app, '/tools/import/');
    if (!str_contains($hinted, 'Importing requires a signed-in root-approved operator.') || str_contains($hinted, 'Last import')) {
        throw new RuntimeException('A viewer without an operator session must not see import state.');
    }

    putenv('FORUM_INSTANCE_IMPORT_PAGE_ENABLED=0');
    TestViewerSession::signIn($argv[3]);
    $_COOKIE = [];
    $app = new Application($argv[1], $argv[2], $databasePath);
    $off = $render($app, '/tools/import/');
    if (http_response_code() !== 404 || str_contains($off, '<h1>Import</h1>')) throw new RuntimeException('Flag off: page should be missing, status ' . http_response_code());
    $toolsOff = $render($app, '/tools/');
    if (str_contains($toolsOff, 'href="/tools/import/"')) throw new RuntimeException('Flag off: Tools lists Import.');
} finally {
    TestViewerSession::reset();
    @unlink($databasePath);
}
PHP;
        $command = sprintf(
            'php -r %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg(self::OPERATOR),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testActionsRequireSameOriginOperatorSessionAndQueueOneImport(): void
    {
        $queue = sys_get_temp_dir() . '/import-actions-' . bin2hex(random_bytes(6)) . '/tasks.sqlite3';
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        $refused = [
            'signed out' => [null, $same, null],
            'identity hint only' => [null, $same, self::OPERATOR],
            'non-operator session' => ['openpgp:' . str_repeat('ab', 20), $same, null],
            'cross-site' => [self::OPERATOR, ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.test'], null],
            'no origin evidence' => [self::OPERATOR, [], null],
        ];
        foreach ($refused as $name => [$identity, $server, $hint]) {
            $result = $this->postAction($queue, $identity, $server, ['action' => 'import', 'source' => 'forum.example'], $hint);
            assertSame('error', $result['status'] ?? null, "Import action allowed for {$name}: " . json_encode($result));
        }
        assertSame(false, is_file($queue));

        $status = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'status']);
        assertSame('ok', $status['status'] ?? null, json_encode($status));
        assertSame(false, $status['state']['busy']);
        assertSame('error', $this->postAction($queue, self::OPERATOR, $same, ['action' => 'wipe'])['status'] ?? null);
        assertSame('There is no interrupted import.', $this->postAction($queue, self::OPERATOR, $same, ['action' => 'resume'])['error'] ?? null);
        assertSame('Enter a source URL or hostname.', $this->postAction($queue, self::OPERATOR, $same, ['action' => 'import', 'source' => ' '])['error'] ?? null);

        $queued = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'import', 'source' => 'forum.example']);
        assertSame('ok', $queued['status'] ?? null, json_encode($queued));
        assertSame('Starting a worker…', $queued['message'], 'no fresh worker: the page starts one');
        assertSame('starting', $queued['state']['start']);
        assertSame('php scripts/task_queue.php run --limit=1', $queued['state']['worker_command']);
        $task = \ForumRewrite\Import\InstanceImportTask::forQueue($queue);
        $pdo = new PDO('sqlite:' . $queue);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $key = (string) (new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($pdo))->findById((int) $queued['state']['task']['id'])['deduplication_key'];
        assertSame('launched', $task->request($key)['Worker-Launch'] ?? null);
        $retry = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'start_worker']);
        assertSame('Starting a worker…', $retry['message'] ?? null, json_encode($retry));
        assertSame(true, $queued['state']['busy']);
        assertSame('import', $queued['state']['task']['action']);
        assertSame('forum.example', $queued['state']['task']['source']);
        $plain = $this->postAction($queue, self::OPERATOR, $same + ['HTTP_X_REQUESTED_WITH' => ''], ['action' => 'import', 'source' => 'other.example']);
        assertStringContains('An import is already queued or running.', (string) ($plain['raw'] ?? ''));
        assertStringContains('<h1>Import</h1>', (string) ($plain['raw'] ?? ''));
        $again = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'import', 'source' => 'other.example']);
        assertSame('An import is already queued or running.', $again['error'] ?? null);
        assertSame(['forum.example'], array_column((new \ForumRewrite\Import\InstanceImportSources(dirname($queue) . '/instance_import'))->all(), 'source'));
    }

    public function testPageOffersRememberedSourcesAndFollowsAQueuedImport(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

$databasePath = sys_get_temp_dir() . '/import-follow-' . bin2hex(random_bytes(6)) . '.sqlite3';
$queuePath = sys_get_temp_dir() . '/import-follow-' . bin2hex(random_bytes(6)) . '/tasks.sqlite3';
mkdir(dirname($queuePath), 0700, true);
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
putenv('FORUM_INSTANCE_IMPORT_PAGE_ENABLED=1');
$render = static function (Application $app): string {
    ob_start();
    $app->handle('GET', '/tools/import/');
    return (string) ob_get_clean();
};
try {
    $_COOKIE = [];
    TestViewerSession::signIn($argv[3]);
    $app = new Application($argv[1], $argv[2], $databasePath);
    $page = $render($app);
    foreach (['data-import-form', 'name="source"', 'value="import"', '/assets/import_control', 'data-import-busy="0"', 'data-import-not-started hidden'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Idle page missing: ' . $needle);
    }
    if (!str_contains($page, 'value="https://zenmemes.com"') || !str_contains($page, '<option value="https://zenmemes.com">')) throw new RuntimeException('No sources yet: known instances are offered.');

    $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
    $queued = InstanceImportTask::forQueue($queuePath)->queue($store, 'import', 'https://forum.example', 'openpgp:x');
    $store->claimNext();
    $store->recordTaskProgress($queued['task_id'], 'Downloading repository archive...');
    $store->recordTaskProgress($queued['task_id'], 'Progress: download 1048576/4194304');
    $page = $render($app);
    foreach (['data-import-busy="1"', 'Downloading repository archive...', '<li class="is-done"><span>Task worker started</span></li>',
        '<li class="is-current"><span>Downloading</span> <span class="meta">1.0 MB of 4.0 MB</span></li>', '<li class="is-pending"><span>Installing records</span></li>',
        'Elapsed 0:0', 'estimating time left', '<option value="https://forum.example">', 'value="https://forum.example"'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Running page missing: ' . $needle);
    }
    if (!str_contains($page, 'data-import-form hidden')) throw new RuntimeException('The form must be hidden while running.');

    $store->markFailed($queued['task_id'], 'instance_import_failed', 'Import failed: source unreachable', false);
    $page = $render($app);
    foreach (['Last request failed:</strong> Import failed: source unreachable', 'data-import-busy="0"'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Failed page missing: ' . $needle);
    }
} finally {
    TestViewerSession::reset();
    @unlink($databasePath);
}
PHP;
        $command = sprintf(
            'php -r %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg(self::OPERATOR),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testResultShowsCountsCausesAndFoldsPerFileDetails(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Http\ImportController;
use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

$base = sys_get_temp_dir() . '/import-result-' . bin2hex(random_bytes(6));
$run = $base . '/repo/.git/instance-import/20261116UTC120000-aaaaaaaaaaaa';
mkdir($run, 0700, true);
$entries = ['records/posts/a.txt' => ['state' => 'import']];
for ($i = 0; $i < 600; $i++) $entries['records/a-excluded/e' . $i . '.txt'] = ['state' => 'excluded', 'reason' => 'private record'];
for ($i = 0; $i < 10000; $i++) $entries['records/posts/c' . $i . '.txt'] = ['state' => 'conflict', 'reason' => 'local <b>differs</b>'];
file_put_contents($run . '/report.json', json_encode([
    'status' => 'partial', 'source' => 'https://forum.example', 'review_path' => $run,
    'counts' => ['import' => 1, 'duplicate' => 0, 'conflict' => 10000, 'invalid' => 0, 'unsupported' => 0, 'excluded' => 0],
    'review_causes' => array_merge(
        [['path' => 'records/posts/c0.txt', 'state' => 'conflict', 'reason' => 'local <b>differs</b>', 'affected_count' => 3]],
        array_map(static fn (int $i): array => ['path' => 'records/thread-labels/l' . $i . '.txt', 'state' => 'invalid', 'reason' => 'Missing dependency: post thread-' . $i, 'affected_count' => 1], range(1, 193)),
        array_map(static fn (int $i): array => ['path' => 'records/post-formats/f' . $i . '.txt', 'state' => 'unsupported', 'reason' => 'Unsupported record family or path', 'affected_count' => 1], range(1, 31)),
        [['path' => 'records/posts/private.txt', 'state' => 'excluded', 'reason' => 'private record', 'affected_count' => 2]],
    ),
    'exclusion_reasons' => ['private record' => 2],
    'entries' => $entries,
]));
$queuePath = $base . '/private/tasks.sqlite3';
mkdir(dirname($queuePath), 0700, true);
$store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
$queued = InstanceImportTask::forQueue($queuePath)->queue($store, 'import', 'https://forum.example', 'openpgp:x');
$store->claimNext();
$store->recordTaskProgress($queued['task_id'], 'Images: partial');
$store->markCompleted($queued['task_id']);
try {
    $_COOKIE = [];
    TestViewerSession::signIn($argv[3]);
    $application = new Application($argv[1], $argv[2], $base . '/index.sqlite3');
    ob_start();
    $application->handle('GET', '/threads/');
    ob_end_clean();
    $routeServices = (fn () => $this->routeServices())->call($application);
    $started = hrtime(true);
    $page = (new ImportController($routeServices, $base . '/repo', $queuePath))->render();
    $seconds = (hrtime(true) - $started) / 1e9;
    foreach (['<strong>needs review</strong>', 'from https://forum.example', 'imported 1 · conflict 10000', 'images partial', '<th scope="row">Review</th>',
        '<li>invalid: Missing dependency <span class="meta">(193)</span></li>', '<li>unsupported: Unsupported record family or path <span class="meta">(31)</span></li>',
        '<li>conflict: local &lt;b&gt;differs&lt;/b&gt; <span class="meta">(3)</span></li>', 'excluded: private record',
        '<details class="import-advanced">', '<summary>Advanced</summary>', '10100 more in <code>' . $run . '/report.json</code>'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Result page missing: ' . $needle);
    }
    if (str_contains($page, '<details class="import-advanced" open')) throw new RuntimeException('Advanced must start closed.');
    $review = substr($page, (int) strpos($page, '<th scope="row">Review</th>'), (int) strpos($page, '</tbody>') - (int) strpos($page, '<th scope="row">Review</th>'));
    if (substr_count($review, 'private record') !== 1) throw new RuntimeException('Exclusion listed twice: ' . $review);
    if (str_contains($review, '<code>') || substr_count($review, '<li>') !== 4) throw new RuntimeException('Review row should list grouped causes only: ' . $review);
    if (strpos($review, 'Missing dependency') > strpos($review, 'Unsupported record')) throw new RuntimeException('Largest group first.');
    if (str_contains($page, 'records/a-excluded/')) throw new RuntimeException('Exclusions should be hidden before conflicts.');
    if (substr_count(substr($page, (int) strpos($page, '<summary>Advanced')), '<li><code>records/posts/c') !== 500) throw new RuntimeException('Per-file list should stop at 500.');
    if ($seconds > 3) throw new RuntimeException('Large report rendered slowly: ' . $seconds);
} finally {
    TestViewerSession::reset();
}
PHP;
        $command = sprintf(
            'php -r %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg(self::OPERATOR),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    /**
     * One request per process, as on a server.
     *
     * @param array<string, string> $server
     * @param array<string, string> $post
     * @return array<string, mixed>
     */
    private function postAction(string $queuePath, ?string $identity, array $server, array $post, ?string $hint = null, ?string $repositoryRoot = null): array
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

[$queuePath, $identity, $server, $post, $hint] = json_decode($argv[3], true);
// A worker launch runs `nohup <php> …`; /bin/false makes it start nothing in tests.
putenv('FORUM_PHP_CLI=' . (getenv('IMPORT_TEST_PHP_CLI') ?: '/bin/false'));
$databasePath = sys_get_temp_dir() . '/import-actions-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
putenv('FORUM_INSTANCE_IMPORT_PAGE_ENABLED=1');
$_COOKIE = [];
if ($identity !== null) {
    TestViewerSession::signIn($identity);
}
if ($hint !== null) $_COOKIE['identity_hint'] = $hint;
$_SERVER = array_merge($_SERVER, ['HTTP_X_REQUESTED_WITH' => 'ForumImport', 'HTTP_HOST' => 'forum.test'], $server);
$app = new Application($argv[1], $argv[2], $databasePath);
if ($argv[2] !== $argv[1] . '/tests/fixtures/parity_minimal_v1') { ob_start(); $app->handle('GET', '/threads/'); ob_end_clean(); }
$_POST = $post;
ob_start();
$app->handle('POST', '/tools/import/');
echo "\n---JSON---\n" . ob_get_clean();
@unlink($databasePath);
PHP;
        $command = sprintf(
            'php -r %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg($repositoryRoot ?? __DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg((string) json_encode([$queuePath, $identity, $server, $post, $hint])),
        );
        $output = (string) shell_exec($command);
        $json = substr($output, (int) strrpos($output, "---JSON---\n") + 11);

        return json_decode($json, true) ?? ['raw' => $output];
    }

    public function testImportResumeAndCancelRunEndToEndThroughThePageAndWorker(): void
    {
        $w = new \ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($source . '/records/posts/page-thread.txt', $w->post('page-thread'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $w->put($w->root . '/router.php', '<?php readfile(__DIR__ . "/source.tar.gz");');
        $server = new \ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $queue = $w->root . '/private/tasks.sqlite3';
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        $static = $w->root . '/static';
        $worker = function () use ($queue, $target, $w, $static): array {
            $pdo = new PDO('sqlite:' . $queue);
            $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($pdo);
            $task = \ForumRewrite\Import\InstanceImportTask::forQueue($queue);
            return (new \ForumRewrite\TaskQueue\TaskQueueWorker($store, static function (): void {},
                runInstanceImport: static fn (array $claimed): array => $task->run($claimed, dirname(__DIR__),
                    ['repository_root' => $target, 'database_path' => $w->root . '/worker-cache/index.sqlite3', 'static_html_root' => $static],
                    static function (string $line) use ($store, $claimed): void { $store->recordTaskProgress((int) $claimed['id'], $line); }),
            ))->run(1);
        };
        $status = fn (): array => $this->postAction($queue, self::OPERATOR, $same, ['action' => 'status'], null, $target);

        // An import whose publication fails leaves a committed run: only Resume is offered.
        $w->put($static, 'File blocking publication directory creation');
        $queued = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'import', 'source' => $server->url], null, $target);
        assertSame('ok', $queued['status'] ?? null, json_encode($queued));
        assertSame(1, $worker()['failed']);
        assertTrue(is_file($target . '/records/posts/page-thread.txt'));
        assertSame(true, $status()['state']['pending']);
        $cancel = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'cancel'], null, $target);
        assertSame('Its content is already committed; resume finishes publishing.', $cancel['error'] ?? null);
        $import = $this->postAction($queue, self::OPERATOR, $same, ['action' => 'import', 'source' => $server->url], null, $target);
        assertSame('An interrupted import is waiting; resume or cancel it first.', $import['error'] ?? null);
        unlink($static);
        assertSame('ok', $this->postAction($queue, self::OPERATOR, $same, ['action' => 'resume'], null, $target)['status'] ?? null);
        assertSame(1, $worker()['completed']);
        $after = $status();
        assertSame(false, $after['state']['pending']);
        assertSame(false, $after['state']['busy']);
        assertTrue(in_array('Import result: complete', $after['state']['progress'], true), json_encode($after['state']['progress']));

        // An interruption before the commit can be canceled from the page.
        $w->put($source . '/records/posts/page-second.txt', $w->post('page-second'));
        try {
            (new \ForumRewrite\Import\ContentImportRunner($target, $w->root . '/worker-cache/index.sqlite3', static function ($phase): void {
                if ($phase === 'copied') throw new RuntimeException('stop');
            }))->run($source, static fn () => null, false, 'https://second.example');
        } catch (RuntimeException) {
        }
        assertTrue(is_file($target . '/records/posts/page-second.txt'));
        assertSame('ok', $this->postAction($queue, self::OPERATOR, $same, ['action' => 'cancel'], null, $target)['status'] ?? null);
        assertSame(1, $worker()['completed']);
        assertTrue(!is_file($target . '/records/posts/page-second.txt'));
        assertSame(false, $status()['state']['pending']);
        assertSame('', trim($w->command(['git', '-C', $target, 'status', '--porcelain'])[1]));
    }

    public function testStartStateFollowsLaunchHeartbeatAndTimeout(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Http\ImportController;
use ForumRewrite\Import\InstanceImportTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

$base = sys_get_temp_dir() . '/import-start-' . bin2hex(random_bytes(6));
mkdir($base . '/private', 0700, true);
$queuePath = $base . '/private/tasks.sqlite3';
$pdo = new PDO('sqlite:' . $queuePath);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$store = new SqliteTaskQueueStore($pdo);
$task = InstanceImportTask::forQueue($queuePath);
$queued = $task->queue($store, 'import', 'forum.example', 'openpgp:x');
$key = (string) $store->findById($queued['task_id'])['deduplication_key'];
$application = new Application($argv[1], $argv[2], $base . '/index.sqlite3');
ob_start(); $application->handle('GET', '/threads/'); ob_end_clean();
$routeServices = (fn () => $this->routeServices())->call($application);
$now = 1000;
$controller = new ImportController($routeServices, $base . '/repo', $queuePath, null, static function () use (&$now): int { return $now; });
$check = static function (string $expected, string $why) use ($controller): void {
    $start = $controller->state()['start'];
    if ($start !== $expected) throw new RuntimeException($why . ': expected ' . $expected . ', got ' . var_export($start, true));
};
$check('not_started', 'queued, no worker, no launch');
$task->annotate($key, ['Worker-Launch' => 'launched', 'Worker-Launch-At' => '1000']);
$now = 1005;
$check('starting', 'just launched');
$now = 1000 + ImportController::START_TIMEOUT_SECONDS;
$check('not_started', 'launched but not claimed in time');
$task->annotate($key, ['Worker-Launch' => 'failed', 'Worker-Launch-At' => (string) $now]);
$check('not_started', 'launch failed');
$run = $store->startExecutorRun();
$store->completeExecutorRun((int) $run['id'], [], []);
$check('waiting', 'fresh worker heartbeat');
$store->claimNext();
$check('started', 'claimed');
if ($task->request('../x') !== null) throw new RuntimeException('bad key');
PHP;
        $command = sprintf(
            'php -r %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testStatusReadsPendingAndNewestFinishedOrCanceledRun(): void
    {
        $root = sys_get_temp_dir() . '/import-status-' . bin2hex(random_bytes(6));
        $state = $root . '/.git/instance-import';
        mkdir($state, 0700, true);
        $status = new InstanceImportStatus($root);
        assertSame(['pending' => null, 'last' => null], $status->read());

        mkdir($state . '/20261115UTC100000-aaaaaaaaaaaa');
        file_put_contents($state . '/20261115UTC100000-aaaaaaaaaaaa/report.json', json_encode(['status' => 'partial', 'source' => 'https://old.example']));
        mkdir($state . '/20261116UTC120000-bbbbbbbbbbbb');
        file_put_contents($state . '/20261116UTC120000-bbbbbbbbbbbb/canceled.json', json_encode(['canceled_at' => '2026-11-16T12:05:00Z', 'report' => ['source' => 'https://new.example']]));
        $read = $status->read();
        assertSame('canceled', $read['last']['outcome']);
        assertSame('2026-11-16T12:05:00Z', $read['last']['at']);
        assertSame('https://new.example', $read['last']['report']['source']);

        mkdir($state . '/20261116UTC130000-cccccccccccc');
        file_put_contents($state . '/pending.json', json_encode(['id' => '20261116UTC130000-cccccccccccc', 'phase' => 'publication', 'report' => ['source' => 'https://pending.example']]));
        $read = $status->read();
        assertSame('https://pending.example', $read['pending']['source']);
        assertSame(true, $read['pending']['committed']);
        assertSame('canceled', $read['last']['outcome']);

        unlink($state . '/pending.json');
        file_put_contents($state . '/20261116UTC130000-cccccccccccc/report.json', '{broken');
        assertSame('canceled', $status->read()['last']['outcome']);
        file_put_contents($state . '/20261116UTC130000-cccccccccccc/report.json', json_encode(['status' => 'complete']));
        $read = $status->read();
        assertSame('complete', $read['last']['outcome']);
        assertSame('2026-11-16T13:00:00Z', $read['last']['at']);
    }
}
