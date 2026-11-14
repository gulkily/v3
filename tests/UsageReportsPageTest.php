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

putenv('FORUM_USAGE_REPORTS_ENABLED=0');
$off = page($application(), 'GET', '/threads/');
expect(!str_contains($off, 'data-usage-'), 'flag off: usage attributes rendered');
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
