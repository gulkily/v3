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
expect(str_contains($log, 'never what you type.'), 'flag on: what is recorded is not explained');
expect(str_contains($log, '<summary>Show exact text (what Upload sends)</summary>') && !str_contains($log, '<details class="usage-log-exact" open'), 'flag on: exact text behind a closed disclosure');
expect(str_contains($log, 'anonymously (you are not signed in)'), 'signed out: uploads attributed anonymously');
expect(str_contains($log, 'data-usage-names="{&quot;pages&quot;:{&quot;/&quot;:&quot;Board&quot;'), 'flag on: names table published');
TestViewerSession::signIn('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
$signedIn = page($application(), 'GET', '/tools/usage-log/');
expect(preg_match('#Uploads go to this site.s operators as <strong>[^<]+</strong>\.#', $signedIn) === 1, 'signed in: uploads attributed to the username');
$_COOKIE = [];
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

    public function testRootSeesShortcutIdeasInWordsOthersGet403(): void
    {
        $this->runScript(<<<'PHP'
$threeClicks = "usage-log v1\nview\t0\t/threads/*\nclick\t40\t/threads/*\tmenu\ttab:account\t-\nclick\t2\t/threads/*\tmenu\tProfile\t/profiles/*\nview\t1\t/profiles/*\nclick\t3\t/profiles/*\tmain\tKeys <b>\t/tools/user-access/profiles/*\nview\t1\t/tools/user-access/profiles/*\n";
$store = new ForumRewrite\UsageReports\UsageReportStore(new PDO('sqlite:' . $reportsPath));
$store->add($threeClicks, 'ilyag', 'a', time());
$store->add($threeClicks, null, 'b', time());
putenv('FORUM_USAGE_REPORTS_ENABLED=1');

http_response_code(200);
$denied = page($application(), 'GET', '/tools/usage-reports/');
expect(http_response_code() === 403 && str_contains($denied, 'root-approved identity'), 'signed out: 403 ' . var_export(http_response_code(), true));
expect(!str_contains($denied, 'data-shortcut-idea') && !str_contains($denied, 'usage-steps'), 'signed out: summary leaked');

TestViewerSession::signIn('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
http_response_code(200);
$all = page($application(), 'GET', '/tools/usage-reports/');
expect(http_response_code() === 200, 'root: status ' . var_export(http_response_code(), true));
expect(str_contains($all, '<dt>Reports</dt><dd>2</dd>'), 'root: report count');
expect(str_contains($all, '<span title="/threads/*">Thread</span> → <span title="/tools/user-access/profiles/*">User Access profile</span>'), 'root: journey heading in words with raw forms on hover');
expect(str_contains($all, '<li title="tab:account">You menu</li>') && str_contains($all, '<li title="Keys &lt;b&gt;">Keys &lt;b&gt;</li>'), 'root: steps in words, labels escaped');
expect(substr_count($all, 'data-shortcut-idea') === 1 && str_contains($all, 'never in one click'), 'root: one shortcut idea');
expect(str_contains($all, '<div class="usage-tile--ideas"><dt>Shortcut ideas</dt><dd>1</dd></div>'), 'root: shortcut ideas tile');
expect(str_contains($all, '<a class="usage-pill" href="/tools/usage-reports/" aria-current="page">Everyone</a>'), 'root: Everyone pill current');
expect(str_contains($all, '<a class="usage-pill" href="/tools/usage-reports/?uploader=anonymous">Anonymous · 1</a>') && str_contains($all, '<a class="usage-pill" href="/tools/usage-reports/?uploader=ilyag">ilyag · 1</a>'), 'root: uploader pills');
expect(str_contains($all, '<strong>You menu</strong> <span class="meta">menu, on Thread</span>'), 'root: most clicked in words');
expect(str_contains($all, '/assets/tools.'), 'root: page stylesheet loaded');

$mine = page($application(), 'GET', '/tools/usage-reports/?uploader=ilyag');
expect(str_contains($mine, '<dt>Reports</dt><dd>1</dd>') && str_contains($mine, '<a class="usage-pill" href="/tools/usage-reports/?uploader=ilyag" aria-current="page">ilyag · 1</a>'), 'root: filter by uploader');
$unknown = page($application(), 'GET', '/tools/usage-reports/?uploader=nobody');
expect(str_contains($unknown, '<dt>Reports</dt><dd>2</dd>'), 'root: unknown uploader falls back to everyone');

putenv('FORUM_USAGE_REPORTS_ENABLED=0');
http_response_code(200);
page($application(), 'GET', '/tools/usage-reports/');
expect(http_response_code() === 404, 'flag off: summary status ' . var_export(http_response_code(), true));
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
