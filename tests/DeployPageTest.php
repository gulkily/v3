<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Deploy\AppUpdate;
use ForumRewrite\Http\DeployController;

require_once __DIR__ . '/../autoload.php';

final class DeployPageTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testOpeningThePageDoesNotFetch(): void
    {
        $base = sys_get_temp_dir() . '/deploy-page-' . bin2hex(random_bytes(6));
        mkdir($base, 0777, true);
        $this->run(['git', 'init', '-q', '--bare', '-b', 'main', $base . '/remote.git']);
        $this->run(['git', 'init', '-q', '-b', 'main', $base . '/app']);
        file_put_contents($base . '/app/README.md', "app\n");
        $this->run(['git', '-c', 'user.name=T', '-c', 'user.email=t@example.invalid', '-C', $base . '/app', 'add', '.']);
        $this->run(['git', '-c', 'user.name=T', '-c', 'user.email=t@example.invalid', '-C', $base . '/app', 'commit', '-q', '-m', 'initial']);
        $this->run(['git', '-C', $base . '/app', 'remote', 'add', 'origin', $base . '/remote.git']);

        $application = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $base . '/index.sqlite3');
        $routeServices = (fn () => $this->routeServices())->call($application);
        $state = (new DeployController($routeServices, new AppUpdate($base . '/app'), $base . '/missing-queue.sqlite3'))->state();

        assertSame(false, is_file($base . '/app/.git/FETCH_HEAD'));
        assertSame(null, $state['last_checked_at']);
        assertSame('not_observed', $state['worker']);
        assertSame('main', $state['branch']);
        assertSame(false, is_file($base . '/missing-queue.sqlite3'));
    }

    public function testPageRendersForOperatorAndReadOnlyForOthers(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';

use ForumRewrite\Application;

$databasePath = sys_get_temp_dir() . '/deploy-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$sessionId = 'deploy-page-' . bin2hex(random_bytes(8));
$render = static function (Application $app, string $path): string {
    ob_start();
    $app->handle('GET', $path);
    return (string) ob_get_clean();
};
try {
    session_id($sessionId);
    session_start();
    $_SESSION['authenticated_identity_id'] = $argv[3];
    session_write_close();
    session_id($sessionId);
    $_COOKIE = [session_name() => $sessionId];
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $page = $render($app, '/tools/deploy/');
    foreach (['<h1>Deploy</h1>', 'Behind origin/main', 'Task worker', 'href="/tools/deploy/"'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Operator page missing: ' . $needle);
    }
    $tools = $render($app, '/tools/');
    if (!str_contains($tools, 'href="/tools/deploy/"')) throw new RuntimeException('Tools launcher does not list Deploy.');

    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_id('');
    unset($_SESSION);
    $_COOKIE = ['identity_hint' => $argv[3]];
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $hinted = $render($app, '/tools/deploy/');
    if (!str_contains($hinted, '<h1>Deploy</h1>') || str_contains($hinted, 'data-deploy-form')) {
        throw new RuntimeException('Identity-hint-only viewer must see the read-only page without controls.');
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
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
     * @param list<string> $command
     */
    private function run(array $command): void
    {
        $pipes = [];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException(implode(' ', $command) . ' failed: ' . $err);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}
