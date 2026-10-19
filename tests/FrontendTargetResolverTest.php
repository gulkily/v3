<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FrontendTargetResolver;
use ForumRewrite\Support\DevServerRegistry;
require_once __DIR__ . '/Support/FrontendTestServer.php';

final class FrontendTargetResolverTest
{
    public function testExplicitTargetWinsAndEnvironmentPersistsAcrossCalls(): void
    {
        $before = getenv('FORUM_STATUS_URL');
        try {
            putenv('FORUM_STATUS_URL=http://127.0.0.1:80');
            $resolver = new FrontendTargetResolver('/nonexistent-checkout', '/nonexistent-repository');
            assertSame(['url' => 'http://127.0.0.1:8001', 'source' => '--url'], $resolver->resolve('http://127.0.0.1:8001'));
            assertSame(['url' => 'http://127.0.0.1:80', 'source' => 'FORUM_STATUS_URL'], $resolver->resolve());
            putenv('FORUM_STATUS_URL=invalid');
            assertThrowsRuntime(static fn () => $resolver->resolve(), 'Frontend target must be an http(s) URL without credentials, fragments, or whitespace.');
        } finally {
            $before === false ? putenv('FORUM_STATUS_URL') : putenv('FORUM_STATUS_URL=' . $before);
        }
    }

    public function testLiveRegistrationStaleIdentityAndAmbiguity(): void
    {
        $server = new FrontendTestServer('<?php echo "ok";');
        $root = $server->directory;
        try {
            $address = substr($server->url, strlen('http://'));
            $path = DevServerRegistry::register($root, $root . '/repo', $root . '/db', $server->pid(), $address);
            assertSame(true, is_string($path));
            $resolver = new FrontendTargetResolver($root, $root . '/repo', $root . '/db');
            assertSame($server->url, $resolver->resolve()['url']);
            // A matching process hint cannot make an unrelated listener healthy.
            assertSame('unavailable', $resolver->check($resolver->resolve())['status']);
            assertSame([], DevServerRegistry::active($root, $root . '/different-repo', $root . '/db'));
            $record = json_decode(file_get_contents($path), true);
            $copy = dirname($path) . '/second.json';
            file_put_contents($copy, json_encode($record));
            assertSame('ambiguous', $resolver->resolve()['status']);
            unlink($copy);
            $record['process'] = str_repeat('0', 64);
            file_put_contents($path, json_encode($record));
            assertSame([], DevServerRegistry::active($root, $root . '/repo', $root . '/db'));
        } finally { $server->close(); }
    }

    public function testRegistrationNormalizesWildcardAndRejectsExitedProcess(): void
    {
        $root = sys_get_temp_dir() . '/v3-registration-' . bin2hex(random_bytes(6));
        mkdir($root . '/public', 0700, true);
        file_put_contents($root . '/public/router.php', '<?php echo "ok";');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = parse_url('tcp://' . stream_socket_get_name($socket, false), PHP_URL_PORT);
        fclose($socket);
        $address = '0.0.0.0:' . $port;
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public', $root . '/public/router.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        try {
            $path = DevServerRegistry::register($root, $root . '/repo', $root . '/db', proc_get_status($process)['pid'], $address);
            assertSame(true, is_string($path));
            assertSame('http://127.0.0.1:' . $port, DevServerRegistry::active($root, $root . '/repo', $root . '/db')[0]['url']);
            proc_terminate($process); fclose($pipes[0]); proc_close($process);
            assertSame([], DevServerRegistry::active($root, $root . '/repo', $root . '/db'));
        } finally {
            if (is_resource($process)) { proc_terminate($process); proc_close($process); }
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
            rmdir($root);
        }
    }

    public function testConventionalDiscoveryRequiresUniqueProofAndNeverMasksExplicitFailure(): void
    {
        $root = '/test-checkout'; $repo = '/test-repository'; $db = '/test-database';
        $identity = \ForumRewrite\Support\FrontendInstance::identity($root, $repo, $db);
        $router = '<?php if ($_SERVER["REQUEST_URI"] === "/__v3/status-identity") { header("X-V3-Instance: ' . $identity . '"); http_response_code(204); } else { http_response_code(503); }';
        $matching = new FrontendTestServer($router);
        $other = new FrontendTestServer('<?php echo "unrelated";');
        $duplicate = new FrontendTestServer($router);
        try {
            $resolver = new FrontendTargetResolver($root, $repo, $db, [$matching->url, $other->url]);
            $result = $resolver->check($resolver->resolve());
            assertSame('error', $result['status']);
            assertSame($matching->url, $result['url']);
            assertSame('verified local endpoint', $result['source']);
            $ambiguous = new FrontendTargetResolver($root, $repo, $db, [$matching->url, $duplicate->url]);
            assertSame('ambiguous', $ambiguous->check($ambiguous->resolve())['status']);
            $unrelated = new FrontendTargetResolver($root, $repo, $db, [$other->url]);
            assertSame('unresolved', $unrelated->check($unrelated->resolve())['status']);
            $explicit = $resolver->check($resolver->resolve($matching->url));
            assertSame('error', $explicit['status']);
            assertSame('--url', $explicit['source']);
        } finally { $matching->close(); $other->close(); $duplicate->close(); }
    }

    public function testHungCandidateCannotEstablishUniquenessAndBudgetIsShared(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $url = 'http://' . stream_socket_get_name($socket, false);
        $identity = \ForumRewrite\Support\FrontendInstance::identity('/test-checkout', '/test-repo', '/test-db');
        $matching = new FrontendTestServer('<?php header("X-V3-Instance: ' . $identity . '"); http_response_code(204);');
        try {
            $resolver = new FrontendTargetResolver('/test-checkout', '/test-repo', '/test-db', [$url, $matching->url]);
            $start = microtime(true);
            assertSame('unresolved', $resolver->check($resolver->resolve())['status']);
            assertSame(true, microtime(true) - $start < 3.2);
            // Repeated hung candidates still share the single deadline.
            $resolver = new FrontendTargetResolver('/test-checkout', '/test-repo', '/test-db', array_fill(0, 5, $url));
            $start = microtime(true);
            assertSame('unresolved', $resolver->check($resolver->resolve())['status']);
            assertSame(true, microtime(true) - $start < 3.2);
        } finally { fclose($socket); $matching->close(); }
    }

    public function testRejectsUnsafeUrls(): void
    {
        foreach (['file:///tmp/test', 'http://user:secret@localhost', "http://localhost/\r\nX: x", 'http://localhost/#fragment'] as $url) {
            assertThrowsRuntime(static fn () => FrontendTargetResolver::validateUrl($url), 'Frontend target must be an http(s) URL without credentials, fragments, or whitespace.');
        }
    }
}
