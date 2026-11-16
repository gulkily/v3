<?php

declare(strict_types=1);

use ForumRewrite\Import\InstanceImportStatus;

require_once __DIR__ . '/../autoload.php';

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
    foreach (['<h1>Import</h1>', 'Last import', 'Task worker', 'never seen', 'href="/tools/import/"'] as $needle) {
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
        assertSame('Queued for the task worker.', $queued['message']);
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
    foreach (['data-import-form', 'name="source"', 'value="import"', '/assets/import_control', 'data-import-busy="0"', 'has not run on this server yet'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Idle page missing: ' . $needle);
    }
    if (str_contains($page, '<datalist')) throw new RuntimeException('No sources yet: no list.');

    $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
    $queued = InstanceImportTask::forQueue($queuePath)->queue($store, 'import', 'https://forum.example', 'openpgp:x');
    $store->claimNext();
    $store->recordTaskProgress($queued['task_id'], 'Downloading repository archive...');
    $page = $render($app);
    foreach (['data-import-busy="1"', 'Downloading repository archive...', 'Importing…', '<option value="https://forum.example">', 'value="https://forum.example"'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Running page missing: ' . $needle);
    }
    if (!str_contains($page, 'value="import" disabled')) throw new RuntimeException('Import button must be disabled while running.');

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

    /**
     * One request per process, as on a server.
     *
     * @param array<string, string> $server
     * @param array<string, string> $post
     * @return array<string, mixed>
     */
    private function postAction(string $queuePath, ?string $identity, array $server, array $post, ?string $hint = null): array
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

[$queuePath, $identity, $server, $post, $hint] = json_decode($argv[3], true);
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
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg((string) json_encode([$queuePath, $identity, $server, $post, $hint])),
        );
        $output = (string) shell_exec($command);
        $json = substr($output, (int) strrpos($output, "---JSON---\n") + 11);

        return json_decode($json, true) ?? ['raw' => $output];
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
