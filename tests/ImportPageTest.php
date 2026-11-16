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
