<?php

declare(strict_types=1);

/** Tools → History and the visit recorder, rendered through Application in a child process. */
final class VisitHistoryPageTest
{
    public function testRecorderAndHistoryPageExistOnlyWhenTheFlagIsOn(): void
    {
        $this->runScript(<<<'PHP'
putenv('FORUM_VISIT_HISTORY_ENABLED=1');
$board = page($application(), 'GET', '/threads/');
expect(str_contains($board, '/assets/visit_history.'), 'flag on: recorder script missing');
expect(strpos($board, '/assets/visit_history.') < strpos($board, '</head>'), 'flag on: recorder not in the head');
$forte = page($application(), 'GET', '/forte');
expect(str_contains($forte, '/assets/visit_history.'), 'flag on: recorder missing on Forte');
http_response_code(200);
$history = page($application(), 'GET', '/tools/history/');
expect(http_response_code() === 200, 'flag on: history status ' . var_export(http_response_code(), true));
expect(str_contains($history, '<h1>History</h1>'), 'flag on: history page');
expect(str_contains($history, 'data-role="visit-history-tree"') && str_contains($history, 'data-action="visit-history-clear"'), 'flag on: tree and Clear');
expect(str_contains($history, "localStorage.getItem('forum_visit_history')"), 'flag on: the tree is drawn in the page, before paint');
expect(str_contains($history, '/assets/tools.'), 'flag on: tools styles');
expect(page($application(), 'GET', '/tools/history') !== '', 'flag on: history without the slash');
$tools = page($application(), 'GET', '/tools/');
expect(str_contains($tools, 'class="tool-launcher-button" href="/tools/history/"'), 'flag on: Tools launcher entry');
expect(str_contains($history, 'href="/tools/history/"'), 'flag on: Tools bar entry on the History page');

putenv('FORUM_VISIT_HISTORY_ENABLED=0');
$off = page($application(), 'GET', '/threads/');
expect(!str_contains($off, '/assets/visit_history.'), 'flag off: recorder loaded');
$forteOff = page($application(), 'GET', '/forte');
expect(!str_contains($forteOff, '/assets/visit_history.'), 'flag off: recorder loaded on Forte');
http_response_code(200);
$historyOff = page($application(), 'GET', '/tools/history/');
expect(http_response_code() === 404, 'flag off: history status ' . var_export(http_response_code(), true));
expect(!str_contains($historyOff, 'data-visit-history'), 'flag off: history page rendered');
$toolsOff = page($application(), 'GET', '/tools/');
expect(!str_contains($toolsOff, '/tools/history/'), 'flag off: Tools entry shown');
PHP);
    }

    private function runScript(string $body): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

$databasePath = sys_get_temp_dir() . '/visit-history-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$reportsPath = sys_get_temp_dir() . '/visit-history-reports-' . bin2hex(random_bytes(6)) . '.sqlite3';
$secretsPath = sys_get_temp_dir() . '/visit-history-secrets-' . bin2hex(random_bytes(6)) . '.php';
file_put_contents($secretsPath, '<?php return ' . var_export(['USAGE_REPORTS_DATABASE_PATH' => $reportsPath], true) . ';');
putenv('FORUM_SECRETS_PATH=' . $secretsPath);
$_COOKIE = [];
$application = static fn (): ForumRewrite\Application => new ForumRewrite\Application($argv[1], $argv[2], $databasePath);
function page(ForumRewrite\Application $app, string $method, string $path): string
{
    $_SERVER['REQUEST_METHOD'] = $method;
    ob_start();
    $app->handle($method, $path);
    return (string) ob_get_clean();
}
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
try {
PHP . "\n" . $body . "\n" . <<<'PHP'
} finally {
    @unlink($databasePath);
    @unlink($reportsPath);
    @unlink($secretsPath);
}
PHP;
        exec(sprintf(
            'php -r %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
        ), $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }
}
