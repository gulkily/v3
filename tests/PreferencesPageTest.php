<?php

declare(strict_types=1);

/** /tools/preferences/, rendered through Application in a child process. */
final class PreferencesPageTest
{
    public function testPageListsThisBrowsersSettingsWithFlagDependentRows(): void
    {
        $this->runScript(<<<'PHP'
putenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED=1');
putenv('FORUM_READING_MARGINS_ENABLED=1');
putenv('FORUM_DRAGGABLE_WINDOWS_ENABLED=1');
putenv('FORUM_USAGE_REPORTS_ENABLED=1');
$on = page($application(), 'GET', '/tools/preferences/');
expect(http_response_code() === 200, 'status ' . var_export(http_response_code(), true));
expect(str_contains($on, '<h1>Preferences</h1>'), 'heading');
expect(str_contains($on, '/assets/preferences.') && str_contains($on, '/assets/account.'), 'page script or row styles missing');
foreach (ForumRewrite\View\ThemeRegistry::permitted(ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], new \ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator()) as $theme) {
    expect(str_contains($on, '<option value="' . $theme['name'] . '">'), 'theme option ' . $theme['name']);
}
foreach (['id="preference-thread-view"', 'id="preference-window-controls"', 'data-preference-reset="reading-margins"', 'data-preference-reset="table-size"', 'data-preference-reset="gallery-size"',
    'data-preference-reset="phone-tabs"', 'data-preference-reset="page-themes"', 'data-preference-reset="welcome-note"',
    'href="/tools/usage-log/"', 'data-preference-reset="all"', 'window.forumPreferences'] as $hook) {
    expect(str_contains($on, $hook), 'missing ' . $hook);
}
// Every table text size key the layout restores is one the page can reset.
foreach (ForumRewrite\View\ZoomTable::KINDS as $key) {
    expect(str_contains($on, '"' . $key . '"'), 'table size key ' . $key);
}
expect(str_contains($on, 'class="nav-link is-active" href="/tools/preferences/"'), 'Tools bar marks Preferences');

PHP);
    }

    public function testRowsForFeaturesThatAreOffAreLeftOut(): void
    {
        $this->runScript(<<<'PHP'
putenv('FORUM_THREAD_DENSITY_TOGGLE_ENABLED=0');
putenv('FORUM_READING_MARGINS_ENABLED=0');
putenv('FORUM_DRAGGABLE_WINDOWS_ENABLED=0');
putenv('FORUM_USAGE_REPORTS_ENABLED=0');
$off = page($application(), 'GET', '/tools/preferences');
expect(str_contains($off, '<h1>Preferences</h1>'), 'no-slash route');
foreach (['id="preference-thread-view"', 'id="preference-window-controls"', 'data-preference-reset="reading-margins"', 'href="/tools/usage-log/"'] as $hook) {
    expect(!str_contains($off, $hook), 'flag off but shown: ' . $hook);
}
$tools = page($application(), 'GET', '/tools/');
expect(str_contains($tools, 'class="tool-launcher-button" href="/tools/preferences/"'), 'launcher entry');
PHP);
    }

    private function runScript(string $body): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

$databasePath = sys_get_temp_dir() . '/preferences-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$secretsPath = sys_get_temp_dir() . '/preferences-secrets-' . bin2hex(random_bytes(6)) . '.php';
file_put_contents($secretsPath, '<?php return [];');
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
    @unlink($secretsPath);
}
PHP;
        exec(sprintf(
            'php -r %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
        ), $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(implode("\n", $output));
        }
    }
}
