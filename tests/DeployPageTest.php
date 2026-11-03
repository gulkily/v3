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
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

$databasePath = sys_get_temp_dir() . '/deploy-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . sys_get_temp_dir() . '/deploy-page-missing-' . bin2hex(random_bytes(6)) . '/tasks.sqlite3');
$render = static function (Application $app, string $path): string {
    ob_start();
    $app->handle('GET', $path);
    return (string) ob_get_clean();
};
try {
    $_COOKIE = [];
    TestViewerSession::signIn($argv[3]);
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $page = $render($app, '/tools/deploy/');
    foreach (['<h1>Deploy</h1>', 'Behind origin/main', 'Task worker', 'href="/tools/deploy/"', 'data-deploy-form', 'value="now"', 'value="check"', '/assets/deploy_control', 'tool-details', 'which has not run on this server yet. Deploy now runs immediately.'] as $needle) {
        if (!str_contains($page, $needle)) throw new RuntimeException('Operator page missing: ' . $needle);
    }
    $tools = $render($app, '/tools/');
    if (!str_contains($tools, 'href="/tools/deploy/"')) throw new RuntimeException('Tools launcher does not list Deploy.');

    TestViewerSession::reset();
    $_COOKIE = ['identity_hint' => $argv[3]];
    $app = new Application($argv[1], $argv[2], $databasePath);
    $render($app, '/threads/');
    $hinted = $render($app, '/tools/deploy/');
    if (!str_contains($hinted, '<h1>Deploy</h1>') || str_contains($hinted, 'data-deploy-form') || str_contains($hinted, 'Deploy now runs immediately')) {
        throw new RuntimeException('Identity-hint-only viewer must see the read-only page without controls.');
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

    public function testActionsRequireSameOriginOperatorSession(): void
    {
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        $refused = [
            'signed out' => [null, $same, null],
            'identity hint only' => [null, $same, self::OPERATOR],
            'non-operator session' => ['openpgp:' . str_repeat('ab', 20), $same, null],
            'cross-site' => [self::OPERATOR, ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.test'], null],
            'origin mismatch' => [self::OPERATOR, ['HTTP_ORIGIN' => 'https://evil.test'], null],
            'no origin evidence' => [self::OPERATOR, [], null],
        ];
        foreach ($refused as $name => [$identity, $server, $hint]) {
            $result = $this->postAction($identity, $server, 'queue', $hint);
            assertSame('error', $result['status'] ?? null, "Deploy action allowed for {$name}");
        }
        $ok = $this->postAction(self::OPERATOR, $same, 'status');
        assertSame('ok', $ok['status'] ?? null, json_encode($ok));
        assertSame(true, array_key_exists('commit', $ok['state'] ?? []));
        assertSame('error', $this->postAction(self::OPERATOR, $same, 'reboot')['status'] ?? null);
    }

    /**
     * One request per process, as on a server.
     *
     * @param array<string, string> $server
     * @return array<string, mixed>
     */
    private function postAction(?string $identity, array $server, string $action, ?string $hint = null): array
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

[$identity, $server, $action, $hint] = json_decode($argv[3], true);
$databasePath = sys_get_temp_dir() . '/deploy-actions-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . sys_get_temp_dir() . '/deploy-actions-queue-' . bin2hex(random_bytes(6)) . '.sqlite3');
$_COOKIE = [];
if ($identity !== null) {
    TestViewerSession::signIn($identity);
}
if ($hint !== null) $_COOKIE['identity_hint'] = $hint;
$_SERVER = array_merge($_SERVER, ['HTTP_X_REQUESTED_WITH' => 'ForumDeploy', 'HTTP_HOST' => 'forum.test'], $server);
// A single POST, with no earlier request in this process to start the session.
$app = new Application($argv[1], $argv[2], $databasePath);
$_POST = ['action' => $action];
ob_start();
$app->handle('POST', '/tools/deploy/');
echo "\n---JSON---\n" . ob_get_clean();
@unlink($databasePath);
PHP;
        $command = sprintf(
            'php -r %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg((string) json_encode([$identity, $server, $action, $hint])),
        );
        $output = (string) shell_exec($command);
        $json = substr($output, (int) strrpos($output, "---JSON---\n") + 11);

        return json_decode($json, true) ?? ['raw' => $output];
    }

    public function testDeployNowThatRewritesThisPageStillResponds(): void
    {
        $base = sys_get_temp_dir() . '/deploy-self-' . bin2hex(random_bytes(6));
        mkdir($base, 0777, true);
        $head = trim($this->capture(['git', '-C', dirname(__DIR__), 'rev-parse', 'HEAD']));
        $this->run(['git', 'clone', '-q', '--bare', '--shared', dirname(__DIR__), $base . '/remote.git']);
        $this->run(['git', '-C', $base . '/remote.git', 'update-ref', 'refs/heads/main', $head]);
        $this->run(['git', '-C', $base . '/remote.git', 'symbolic-ref', 'HEAD', 'refs/heads/main']);
        $this->run(['git', 'clone', '-q', '--shared', '-b', 'main', $base . '/remote.git', $base . '/app']);
        $this->run(['git', 'clone', '-q', '--shared', '-b', 'main', $base . '/remote.git', $base . '/seed']);
        file_put_contents($base . '/seed/templates/pages/deploy.php', "<?php // replaced by the deploy under test\n", FILE_APPEND);
        $this->run(['git', '-c', 'user.name=T', '-c', 'user.email=t@example.invalid', '-C', $base . '/seed', 'commit', '-q', '-am', 'change the deploy page']);
        $this->run(['git', '-C', $base . '/seed', 'push', '-q', 'origin', 'main']);
        $target = trim($this->capture(['git', '-C', $base . '/seed', 'rev-parse', 'HEAD']));

        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;

$databasePath = $argv[4] . '/index.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $argv[4] . '/tasks.sqlite3');
$_COOKIE = [];
TestViewerSession::signIn($argv[3]);
$_SERVER = array_merge($_SERVER, ['HTTP_X_REQUESTED_WITH' => 'ForumDeploy', 'HTTP_HOST' => 'forum.test', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);
$app = new Application($argv[4] . '/app', $argv[2], $databasePath);
$send = static function (string $action) use ($app): array {
    $_POST = ['action' => $action];
    ob_start();
    $app->handle('POST', '/tools/deploy/');
    return json_decode((string) ob_get_clean(), true) ?? [];
};
$queued = $send('queue');
$again = $send('queue');
if (($queued['result']['outcome'] ?? null) !== 'queued' || ($again['result']['task_id'] ?? null) !== ($queued['result']['task_id'] ?? 0) || !str_contains($again['result']['message'] ?? '', 'already queued')) {
    throw new RuntimeException('Queue action did not coalesce: ' . json_encode([$queued, $again]));
}
$now = $send('now');
if (($now['status'] ?? null) !== 'ok' || ($now['result']['outcome'] ?? null) !== 'deployed' || ($now['state']['commit'] ?? null) !== $argv[5]) {
    throw new RuntimeException('Deploy now did not complete its response: ' . json_encode($now));
}
if (!str_contains((string) file_get_contents($argv[4] . '/app/templates/pages/deploy.php'), 'replaced by the deploy under test')) {
    throw new RuntimeException('Deployed template was not written.');
}
PHP;
        $this->runScript($script, [$base, $target]);
    }

    private function runScript(string $script, array $extra = []): void
    {
        $command = sprintf(
            'php -r %s %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg(self::OPERATOR),
        );
        foreach ($extra as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }

    /**
     * @param list<string> $command
     */
    private function capture(array $command): string
    {
        return (string) shell_exec(implode(' ', array_map('escapeshellarg', $command)));
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
