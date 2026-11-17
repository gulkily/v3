<?php

declare(strict_types=1);

/** Fast navigation and navbar copies: the layout's attributes and page scripts, rendered through Application in a child process. */
final class FastNavigationLayoutTest
{
    public function testLayoutLoadsTheScriptAndAsksForPreloadOnlyWhenTheFlagIsOn(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';

$databasePath = sys_get_temp_dir() . '/fast-navigation-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_SECRETS_PATH=' . sys_get_temp_dir() . '/fast-navigation-absent-secrets.php');
$_COOKIE = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
function page(string $root, string $repository, string $databasePath, string $path): string
{
    ob_start();
    (new ForumRewrite\Application($root, $repository, $databasePath))->handle('GET', $path);
    return (string) ob_get_clean();
}
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
try {
    putenv('FORUM_FAST_NAVIGATION_ENABLED=1');
    foreach (['/', '/users/', '/about/'] as $path) {
        $on = page($argv[1], $argv[2], $databasePath, $path);
        expect(str_contains($on, 'data-fast-navigation="1"'), "flag on, $path: preload not requested");
        expect(preg_match('~<script src="/assets/fast_navigation\.[0-9a-f]{12}\.js" defer></script>~', $on) === 1, "flag on, $path: script missing");
    }

    putenv('FORUM_FAST_NAVIGATION_ENABLED=0');
    $off = page($argv[1], $argv[2], $databasePath, '/');
    expect(str_contains($off, 'data-fast-navigation="0"'), 'flag off: preload not turned off');
    expect(!str_contains($off, '/assets/fast_navigation.'), 'flag off: script loaded');

    putenv('FORUM_NAV_CACHED_COPY_ENABLED=1');
    $copyOn = page($argv[1], $argv[2], $databasePath, '/');
    expect(str_contains($copyOn, 'data-nav-cached-copy="1"'), 'copy flag on: attribute missing');
    expect(preg_match('~<script src="/assets/nav_cached_copy\.[0-9a-f]{12}\.js" defer></script>~', $copyOn) === 1, 'copy flag on: script missing');
    putenv('FORUM_NAV_CACHED_COPY_ENABLED=0');
    $copyOff = page($argv[1], $argv[2], $databasePath, '/');
    expect(str_contains($copyOff, 'data-nav-cached-copy="0"'), 'copy flag off: copies not dropped');
    expect(!str_contains($copyOff, '/assets/nav_cached_copy.'), 'copy flag off: script loaded');
} finally {
    @unlink($databasePath);
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
