<?php

declare(strict_types=1);

/** Usage log and usage reports pages, rendered through Application in a child process. */
final class UsageReportsPageTest
{
    public function testLayoutPublishesPageKindAndWordsOnlyWhenTheFlagIsOn(): void
    {
        $this->runScript(<<<'PHP'
putenv('FORUM_USAGE_REPORTS_ENABLED=1');
$on = page($application(), 'GET', '/threads/?sort=new');
expect(str_contains($on, 'data-usage-page="/threads"'), 'flag on: page kind missing');
expect(str_contains($on, 'data-usage-words="about account key'), 'flag on: words missing');
expect(str_contains($on, '/assets/usage_log.'), 'flag on: recorder script missing');

putenv('FORUM_USAGE_REPORTS_ENABLED=0');
$off = page($application(), 'GET', '/threads/');
expect(!str_contains($off, 'data-usage-'), 'flag off: usage attributes rendered');
expect(!str_contains($off, '/assets/usage_log.'), 'flag off: recorder script loaded');
PHP);
    }

    public function testUsageLogPageAndToolsEntriesExistOnlyWhenTheFlagIsOn(): void
    {
        $this->runScript(<<<'PHP'
putenv('FORUM_USAGE_REPORTS_ENABLED=1');
$log = page($application(), 'GET', '/tools/usage-log/');
expect(http_response_code() === 200, 'flag on: usage log status ' . var_export(http_response_code(), true));
expect(str_contains($log, '<h1>Usage log</h1>') && str_contains($log, 'data-role="usage-log-recording"'), 'flag on: usage log page');
expect(str_contains($log, '/assets/usage_log_page.'), 'flag on: page script missing');
expect(str_contains($log, 'It never holds anything you type.'), 'flag on: what is recorded is not explained');
$tools = page($application(), 'GET', '/tools/');
expect(str_contains($tools, 'href="/tools/usage-log/"') && str_contains($tools, 'href="/tools/usage-reports/"'), 'flag on: tools launcher entries');

putenv('FORUM_USAGE_REPORTS_ENABLED=0');
http_response_code(200);
$off = page($application(), 'GET', '/tools/usage-log/');
expect(http_response_code() === 404, 'flag off: usage log status ' . var_export(http_response_code(), true));
expect(!str_contains($off, 'data-usage-log'), 'flag off: usage log page rendered');
$toolsOff = page($application(), 'GET', '/tools/');
expect(!str_contains($toolsOff, '/tools/usage-log/') && !str_contains($toolsOff, '/tools/usage-reports/'), 'flag off: tools entries shown');
PHP);
    }

    public function testUploadRouteIsGoneWithTheFlagOffAndBehindTheMembersOnlyGate(): void
    {
        $this->runScript(<<<'PHP'
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
putenv('FORUM_USAGE_REPORTS_ENABLED=0');
http_response_code(200);
page($application(), 'GET', '/tools/usage-log/upload');
expect(http_response_code() === 404, 'flag off: upload GET status ' . var_export(http_response_code(), true));
http_response_code(200);
$offPost = page($application(), 'POST', '/tools/usage-log/upload');
expect(http_response_code() === 405 && !str_contains($offPost, 'invalid_log'), 'flag off: POST should get the generic page 405, status ' . var_export(http_response_code(), true));

putenv('FORUM_USAGE_REPORTS_ENABLED=1');
http_response_code(200);
$open = page($application(), 'POST', '/tools/usage-log/upload');
expect(http_response_code() === 400 && str_contains($open, 'invalid_log'), 'flag on: empty upload should reach the format check');

putenv('FORUM_APPROVED_MEMBERS_ONLY=1');
http_response_code(200);
page($application(), 'POST', '/tools/usage-log/upload');
expect(http_response_code() === 403, 'members only: anonymous upload status ' . var_export(http_response_code(), true));
putenv('FORUM_APPROVED_MEMBERS_ONLY');
PHP);
    }

    /** Runs $body with $application() (fresh Application), page() and expect() helpers. */
    private function runScript(string $body): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

$databasePath = sys_get_temp_dir() . '/usage-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$reportsPath = sys_get_temp_dir() . '/usage-reports-' . bin2hex(random_bytes(6)) . '.sqlite3';
$secretsPath = sys_get_temp_dir() . '/usage-secrets-' . bin2hex(random_bytes(6)) . '.php';
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
