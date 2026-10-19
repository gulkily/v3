<?php

declare(strict_types=1);

// Run in an isolated network namespace; never competes with user/production listeners.
// unshare -Urn sh -c 'ip link set lo up; php tests/Support/frontend_status_acceptance.php'
require dirname(__DIR__, 2) . '/autoload.php';
require __DIR__ . '/FrontendTestServer.php';

use ForumRewrite\Support\DevServerRegistry;
use ForumRewrite\Support\FrontendInstance;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$root = dirname(__DIR__, 2);
$directory = sys_get_temp_dir() . '/v3-status-acceptance-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$repo = $directory . '/repository';
$db = $directory . '/read-model.sqlite3';
LocalRepositoryBootstrap::initializeLocalRepository($root, $repo);
$environment = ['FORUM_REPOSITORY_ROOT' => $repo, 'FORUM_DATABASE_PATH' => $db,
    'FORUM_TASK_QUEUE_DATABASE_PATH' => $directory . '/queue.sqlite3',
    'FORUM_SECRETS_PATH' => $directory . '/secrets.php'] + getenv();
unset($environment['FORUM_STATUS_URL']);
file_put_contents($environment['FORUM_SECRETS_PATH'], '<?php return [];');
$devs = [];
$fixture = null;
function verify(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}
function statusOutput(array $environment, array $arguments = []): string
{
    global $root, $directory, $repo, $db;
    $process = proc_open(['bash', $root . '/v3', 'status', '--repository-root=' . $repo,
        '--database-path=' . $db, '--queue-database-path=' . $directory . '/queue.sqlite3', ...$arguments],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root, $environment);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') { throw new RuntimeException('Status command failed: ' . $error); }
    return $output;
}
function startDev(int $port): array
{
    global $root, $directory, $environment, $repo, $db;
    $process = proc_open(['bash', $root . '/v3', 'start', (string) $port, '--quiet'],
        [['pipe', 'r'], ['file', $directory . '/dev.log', 'a'], ['file', $directory . '/dev.log', 'a']], $pipes, $root, $environment);
    for ($i = 0; $i < 1000; $i++) {
        $entries = DevServerRegistry::active($root, $repo, $db);
        if (array_filter($entries, static fn ($entry) => $entry['url'] === 'http://127.0.0.1:' . $port)) {
            $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.02);
            if ($socket !== false) { fclose($socket); return [$process, $pipes]; }
        }
        usleep(10000);
    }
    stopDev([$process, $pipes]);
    throw new RuntimeException('Development server did not become ready: ' . file_get_contents($directory . '/dev.log'));
}
function stopDev(array $server): void
{
    [$process, $pipes] = $server;
    proc_terminate($process);
    foreach ($pipes as $pipe) { fclose($pipe); }
    proc_close($process);
}
try {
    $devs[] = startDev(8001);
    $out = statusOutput($environment);
    verify((bool) preg_match('/^OK +Frontend .*127\.0\.0\.1:8001 \(development server\)/m', $out), 'plain status follows ./v3 start 8001');
    $devs[] = startDev(8002);
    verify((bool) preg_match('/^WARN +Frontend .*multiple development servers/m', statusOutput($environment)), 'two servers require explicit selection');
    verify((bool) preg_match('/^OK +Frontend .*8002 \(--url\)/m', statusOutput($environment, ['--url=http://127.0.0.1:8002'])), 'explicit override recovers ambiguity');
    stopDev(array_pop($devs)); stopDev(array_pop($devs));
    verify(DevServerRegistry::active($root, $repo, $db) === [], 'shutdown removes active registrations');
    verify((bool) preg_match('/^WARN +Frontend .*unresolved/m', statusOutput($environment)), 'stopped servers do not create false frontend failure');
    $devs[] = startDev(8002);
    verify((bool) preg_match('/^OK +Frontend .*8002 \(development server\)/m', statusOutput($environment)), 'restart on a changed port is rediscovered');
    stopDev(array_pop($devs));

    $identity = FrontendInstance::identity($root, $repo, $db);
    $fixture = new FrontendTestServer('<?php if ($_SERVER["REQUEST_URI"] === "/__v3/status-identity") { header("X-V3-Instance: ' . $identity . '"); http_response_code(204); } elseif ($_SERVER["REQUEST_URI"] === "/health?check=yes" && $_SERVER["HTTP_HOST"] !== "localhost:80") { http_response_code(500); } else { echo "ok"; }', 80);
    verify((bool) preg_match('/^OK +Frontend .*127\.0\.0\.1:80 \(verified local endpoint\)/m', statusOutput($environment)), 'plain status identifies a matching port-80 fixture');
    file_put_contents($environment['FORUM_SECRETS_PATH'], '<?php return ["FORUM_STATUS_URL" => "http://localhost:80/health?check=yes"];');
    verify((bool) preg_match('/^OK +Frontend .*localhost:80\/health\?check=yes \(FORUM_STATUS_URL\)/m', statusOutput($environment)), 'saved virtual-host URL preserves Host and query');
    $fixture->close(); $fixture = null;
    verify((bool) preg_match('/^FAIL +Frontend .*localhost:80/m', statusOutput($environment)), 'configured outage remains FAIL');
    verify((bool) preg_match('/^FAIL +Frontend .*127\.0\.0\.1:1 \(--url\)/m', statusOutput($environment, ['--url=http://127.0.0.1:1'])), 'explicit target wins over saved target');
    verify((bool) preg_match('/^FAIL +Frontend .*127\.0\.0\.1:2 \(FORUM_STATUS_URL\)/m', statusOutput(['FORUM_STATUS_URL' => 'http://127.0.0.1:2'] + $environment)), 'environment wins over private file');
} finally {
    foreach ($devs as $server) { stopDev($server); }
    if ($fixture !== null) { $fixture->close(); }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($directory);
}
