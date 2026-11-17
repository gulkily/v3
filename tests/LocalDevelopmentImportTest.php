<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

final class LocalDevelopmentImportTest
{
    public function testOnlyLocalDevelopmentRequestsReceiveImportControls(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        $environment = $this->environment($w, $target);
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/dev.log', $environment);
        [$code, $html, $headers] = $this->request($server->url, '/');
        assertSame(200, $code, $html);
        assertStringContains('Import public content', $html);
        assertStringContains('https://zenmemes.com', $html);
        assertStringContains('no-store', implode("\n", $headers));
        assertSame(200, $this->request($server->url, '/threads/')[0]);
        assertStringNotContains('data-local-import', $this->request($server->url, '/?view=all')[1]);
        assertStringContains('<rss', $this->request($server->url, '/?format=rss')[1]);
        foreach ([['Host: evil.example'], ['X-Forwarded-For: 127.0.0.1'], ['Forwarded: for=127.0.0.1'], ['X-Forwarded-Host: localhost'], ['X-Forwarded-Proto: https']] as $extra) {
            [, $body] = $this->request($server->url, '/', null, $extra);
            assertStringNotContains('data-local-import', $body);
        }
        $normal = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/normal.log', array_merge($environment, ['V3_DEV_LOG' => '0']));
        assertStringNotContains('data-local-import', $this->request($normal->url, '/')[1]);
        assertSame(404, $this->request($normal->url, '/local/')[0]);
        $token = $this->token($html);
        foreach ([['token' => 'wrong'], ['token' => $token]] as $data) {
            $extra = $data['token'] === $token ? ['Origin: https://evil.example'] : [];
            [$code, $body] = $this->request($server->url, '/local/', $data + ['source' => 'http://127.0.0.1:1'], $extra);
            assertSame(403, $code, $body);
            assertStringContains('Import was not started', $body);
        }
    }

    public function testBrowserImportFailureRetryRepeatAndPublicationResume(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        $source = $w->repository('source');
        $w->put($source . '/records/posts/local-import-example.txt', $w->post('local-import-example'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $w->put($w->root . '/source-router.php', '<?php if (str_contains($_SERVER["REQUEST_URI"], "repository")) { readfile(__DIR__ . "/source.tar.gz"); } else { http_response_code(404); }');
        $remote = new ImportHttpServer($w->root . '/source-router.php', $w->root . '/source.log');
        $environment = $this->environment($w, $target);
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/dev.log', $environment);
        $token = $this->token($this->request($server->url, '/')[1]);
        $data = ['token' => $token, 'source' => 'http://127.0.0.1:1', 'action' => 'import'];
        [$code, $body] = $this->request($server->url, '/local/', $data);
        assertSame(200, $code, $body);
        assertStringContains('Retry import', $body);
        assertStringContains('value="http://127.0.0.1:1"', $body);
        assertTrue(!is_file($target . '/records/posts/local-import-example.txt'));
        $data['source'] = $remote->url;
        // Fail after copying content, then repair publication and resume through the form.
        $w->command(['rm', '-rf', '--', $environment['FORUM_STATIC_HTML_ROOT'] . '/offline']);
        $w->put($environment['FORUM_STATIC_HTML_ROOT'] . '/offline', 'Blocked publication directory');
        [, $body] = $this->request($server->url, '/local/', $data);
        assertStringContains('Resume import', $body);
        assertTrue(is_file($target . '/records/posts/local-import-example.txt'));
        assertTrue(is_file($target . '/.git/instance-import/pending.json'));
        assertStringContains('value="' . $remote->url . '"', $this->request($server->url, '/local/')[1]);
        unlink($environment['FORUM_STATIC_HTML_ROOT'] . '/offline');
        [, $body] = $this->request($server->url, '/local/', array_merge($data, ['action' => 'resume']));
        assertStringContains('Browse imported threads', $body);
        assertTrue(!is_file($target . '/.git/instance-import/pending.json'));
        $board = $this->request($server->url, '/threads/?view=all')[1];
        assertStringContains('Imported local-import-example', $board);
        assertStringContains('Imported body', $this->request($server->url, '/threads/local-import-example')[1]);
        [, $body] = $this->request($server->url, '/local/', $data);
        assertStringContains('Browse imported threads', $body);
        assertStringContains("\nimport: 0\n", $body);
        assertSame(1, count(glob($target . '/records/posts/local-import-example.txt')));
        $original = file_get_contents($target . '/records/posts/root-001.txt');
        $w->put($source . '/records/posts/root-001.txt', $w->post('root-001', '', 'A conflicting remote version'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        [, $body] = $this->request($server->url, '/local/', $data);
        assertStringContains('Some content needs review', $body);
        assertStringNotContains('Browse imported threads', $body);
        assertStringContains('Browse local content', $body);
        assertSame($original, file_get_contents($target . '/records/posts/root-001.txt'));

    }

    private function environment(ImportTestWorkspace $w, string $target): array
    {
        $w->put($w->root . '/secrets.php', '<?php return [];');
        return ['V3_DEV_LOG' => '1', 'FORUM_REPOSITORY_ROOT' => $target,
            'FORUM_DATABASE_PATH' => $w->root . '/cache/index.sqlite3', 'FORUM_STATIC_HTML_ROOT' => $w->root . '/static',
            'FORUM_PUBLIC_ARTIFACT_ROOT' => $w->root . '/public', 'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/private/tasks.sqlite3',
            'FORUM_SECRETS_PATH' => $w->root . '/secrets.php', 'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
            'FAST_SCORING_ENABLED' => 'false'];
    }

    private function token(string $html): string
    {
        assertTrue(preg_match('/name="token" value="([a-f0-9]{64})"/', $html, $match) === 1, $html);
        return $match[1];
    }

    private function request(string $url, string $path, ?array $data = null, array $headers = []): array
    {
        $options = ['method' => $data === null ? 'GET' : 'POST', 'ignore_errors' => true, 'timeout' => 60,
            'header' => implode("\r\n", array_merge($headers, $data === null ? [] : ['Content-Type: application/x-www-form-urlencoded']))];
        if ($data !== null) $options['content'] = http_build_query($data);
        $body = file_get_contents($url . $path, false, stream_context_create(['http' => $options]));
        preg_match('/ ([0-9]{3}) /', $http_response_header[0], $match);
        return [(int) $match[1], $body, $http_response_header];
    }
}
