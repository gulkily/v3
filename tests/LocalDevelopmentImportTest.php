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

        // Import answers at once with a redirect; the page then shows the queued steps.
        $started = microtime(true);
        [$code, , $headers] = $this->request($server->url, '/local/', $data, [], false);
        assertSame(303, $code);
        assertTrue(microtime(true) - $started < 1.0, 'Import should return without waiting for the import.');
        assertStringContains('Location: /local/', implode("\n", $headers));
        // No worker runs beside this test server, so the page starts one (the --no-worker case).
        $body = $this->finished($server->url);
        assertStringContains('Retry import', $body);
        assertStringContains('<p role="alert" data-import-result>Unable to download source archive (connection, timeout, or TLS failure). Retry or resume below.</p>', $body);
        assertStringNotContains('Last request failed', $body);
        assertStringContains('Browse local content', $body);
        assertStringContains('value="http://127.0.0.1:1"', $body);
        assertStringNotContains('data-import-last', $body);
        assertTrue(!is_file($target . '/records/posts/local-import-example.txt'));

        $data['source'] = $remote->url;
        // Fail after copying content, then repair publication and resume through the form.
        $w->command(['rm', '-rf', '--', $environment['FORUM_STATIC_HTML_ROOT'] . '/offline']);
        $w->put($environment['FORUM_STATIC_HTML_ROOT'] . '/offline', 'Blocked publication directory');
        $body = $this->import($server->url, $data);
        assertStringContains('Resume import', $body);
        assertStringContains('An interrupted import needs to finish', $body);
        assertStringContains('role="alert"', $body);
        assertTrue(is_file($target . '/records/posts/local-import-example.txt'));
        assertTrue(is_file($target . '/.git/instance-import/pending.json'));
        assertStringContains('value="' . $remote->url . '"', $body);
        // A new import is refused while one is interrupted, and nothing is queued.
        [$code, $refused] = $this->request($server->url, '/local/', $data);
        assertSame(409, $code, $refused);
        assertStringContains('resume or cancel it first', $refused);
        unlink($environment['FORUM_STATIC_HTML_ROOT'] . '/offline');
        $body = $this->import($server->url, array_merge($data, ['action' => 'resume']));
        assertStringContains('Browse imported threads', $body);
        assertStringNotContains('role="alert"', $body);
        assertTrue(!is_file($target . '/.git/instance-import/pending.json'));
        assertStringContains('</time> · 1 imported</p>', $body);
        $board = $this->request($server->url, '/threads/?view=all')[1];
        assertStringContains('Imported local-import-example', $board);
        assertStringContains('Imported body', $this->request($server->url, '/threads/local-import-example')[1]);
        $body = $this->import($server->url, $data);
        assertStringContains('Browse imported threads', $body);
        assertStringContains("\nimport: 0\n", $body);
        assertStringNotContains('role="alert"', $body);
        // One action re-imports from the copy's own source; the field is folded under Change source.
        assertStringContains('>Check for new content</button>', $body);
        assertStringContains('<input type="hidden" name="source" value="' . $remote->url . '">', $body);
        assertTrue(preg_match('#<p class="meta">Downloads everything again; last time took \d+\x{00A0}s\.</p>#u', $body) === 1);
        assertTrue(preg_match('#<details data-import-result>\s*<summary>Change source</summary>.*?<input type="text" name="source" value="' . preg_quote($remote->url, '#') . '" required#s', $body) === 1);
        assertStringNotContains('Local posts and settings stay', $body);
        // A failed re-import from another source: its reason and Retry, above the last good status.
        $body = $this->import($server->url, ['token' => $token, 'source' => 'http://127.0.0.1:1', 'action' => 'import']);
        assertStringContains('Unable to download source archive (connection, timeout, or TLS failure). Retry or resume below.', $body);
        assertStringContains('>Retry import</button>', $body);
        assertStringContains('<input type="hidden" name="source" value="http://127.0.0.1:1">', $body);
        assertTrue(strpos($body, 'role="alert"') < strpos($body, 'data-import-last'));
        assertStringContains('Imported from <strong>' . substr($remote->url, 7) . '</strong>', $body);
        assertStringContains('· nothing new</p>', $body);
        // A refused request from Change source reopens it with what was typed.
        [$code, $refused] = $this->request($server->url, '/local/', ['token' => $token, 'source' => '-bad', 'action' => 'import']);
        assertSame(400, $code, $refused);
        assertTrue(preg_match('#<details data-import-result open>\s*<summary>Change source</summary>.*?value="-bad"#s', $refused) === 1);
        // The card reports on the copy: source, age and what came in. "duplicate" stays in the details.
        assertTrue(preg_match('#<p data-import-result data-import-last>Imported from <strong>127\.0\.0\.1:\d+</strong> <time [^>]*>just now</time> · nothing new</p>#', $body) === 1, substr($body, (int) strpos($body, 'data-import-root'), 600));
        assertStringContains('<summary>Import details</summary>', $body);
        assertStringNotContains('duplicate', substr($body, 0, (int) strpos($body, '<summary>')));
        assertSame(1, count(glob($target . '/records/posts/local-import-example.txt')));
        $original = file_get_contents($target . '/records/posts/root-001.txt');
        $w->put($source . '/records/posts/root-001.txt', $w->post('root-001', '', 'A conflicting remote version'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        $body = $this->import($server->url, $data);
        // Held-back records are a quiet line that opens the details, with conflicts named; no alert.
        assertStringContains('<summary>1 held back for review · 1 conflict</summary>', $body);
        assertStringContains('· nothing new</p>', $body);
        assertStringNotContains('role="alert"', $body);
        assertStringNotContains('Some content needs review', $body);
        assertStringContains('Browse imported threads', $body);
        assertSame($original, file_get_contents($target . '/records/posts/root-001.txt'));

        // An import run from the terminal is the newest import too; the page has no output for it.
        assertStringContains('<pre>', $body);
        sleep(1);
        $w->put($source . '/records/posts/terminal-import-example.txt', $w->post('terminal-import-example'));
        $w->command(['tar', '-czf', $w->root . '/source.tar.gz', '-C', $w->root, 'source']);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/import_instance.php') . ' ' . escapeshellarg($remote->url)
            . ' --repository-root=' . escapeshellarg($target) . ' --database-path=' . escapeshellarg($environment['FORUM_DATABASE_PATH'])
            . ' --static-html-root=' . escapeshellarg($environment['FORUM_STATIC_HTML_ROOT']) . ' 2>&1', $terminalOutput, $terminalCode);
        assertTrue(in_array($terminalCode, [0, 2], true), implode("\n", $terminalOutput));
        $body = $this->request($server->url, '/local/')[1];
        assertStringContains('</time> · 1 new</p>', $body);
        assertStringContains('<summary>1 held back for review · 1 conflict</summary>', $body);
        assertStringNotContains('<pre>', $body);
    }

    public function testQueuedImportShowsStepsAndLeavesTheSiteUsable(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        // The worker the page starts cannot run here, so the import stays queued.
        $environment = array_merge($this->environment($w, $target), ['FORUM_PHP_CLI' => '/bin/false']);
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/dev.log', $environment);
        $token = $this->token($this->request($server->url, '/')[1]);
        [$code, $body] = $this->request($server->url, '/local/', ['token' => $token, 'source' => 'http://127.0.0.1:1', 'action' => 'import']);
        assertSame(200, $code, $body);
        assertStringContains('data-import-busy="1"', $body);
        assertStringContains('<li class="is-current"><span>Starting the task worker</span></li>', $body);
        assertStringContains('<li class="is-pending"><span>Downloading</span></li>', $body);
        assertStringContains('Elapsed 0:0', $body);
        // The form and its explanation are hidden while the steps show; scripts-off pages reload themselves.
        assertTrue(preg_match('/<form[^>]*data-import-form hidden>/', $body) === 1, $body);
        assertStringContains('<noscript><meta http-equiv="refresh" content="5;url=/local/"></noscript>', $body);
        // Other pages answer while the import waits, and a reload shows the same state.
        $started = microtime(true);
        assertSame(200, $this->request($server->url, '/threads/?view=all')[0]);
        assertTrue(microtime(true) - $started < 2.0);
        assertStringContains('<li class="is-current"><span>Starting the task worker</span></li>', $this->request($server->url, '/local/')[1]);
        // The page script's requests: JSON in the shape Tools → Import answers with.
        $script = ['X-Requested-With: ForumImport'];
        [$code, $json] = $this->request($server->url, '/local/', ['token' => $token, 'action' => 'status'], $script);
        $status = json_decode($json, true);
        assertSame(200, $code, $json);
        assertSame(true, $status['state']['busy']);
        assertSame('worker', $status['state']['steps'][0]['key']);
        assertSame('current', $status['state']['steps'][0]['status']);
        assertTrue(is_int($status['state']['timing']['elapsed']));
        assertSame('php scripts/task_queue.php run --limit=1', $status['state']['worker_command']);
        [$code, $json] = $this->request($server->url, '/local/', ['token' => $token, 'action' => 'start_worker'], $script);
        assertSame(200, $code, $json);
        [$code, $json] = $this->request($server->url, '/local/', ['token' => 'wrong', 'action' => 'status'], $script);
        assertSame(403, $code, $json);
        assertSame('error', json_decode($json, true)['status']);
        assertStringContains('data-import-form-retry', $body);
        assertTrue(preg_match('/data-import-not-started hidden>/', $body) === 1);
        // A second import is refused, as are requests without the token or from elsewhere.
        assertSame(409, $this->request($server->url, '/local/', ['token' => $token, 'source' => 'http://127.0.0.1:1', 'action' => 'import'])[0]);
        foreach (['import', 'resume', 'status', 'start_worker'] as $action) {
            assertSame(403, $this->request($server->url, '/local/', ['token' => 'wrong', 'action' => $action])[0], $action);
            foreach ([['Origin: https://evil.example'], ['Sec-Fetch-Site: cross-site'], ['X-Forwarded-For: 127.0.0.1'], ['Host: evil.example']] as $extra) {
                $code = $this->request($server->url, '/local/', ['token' => $token, 'action' => $action], $extra)[0];
                // Proxied and wrong-Host requests never reach the local page; ordinary routing answers them.
                assertTrue(in_array($code, [403, 404, 405], true), $action . ' ' . $extra[0] . ' answered ' . $code);
            }
        }
        assertSame(400, $this->request($server->url, '/local/', ['token' => $token, 'action' => 'cancel'])[0]);
    }

    /** Post the form, follow the redirect, and wait for the page-started worker to finish. */
    private function import(string $url, array $data): string
    {
        [$code, $body] = $this->request($url, '/local/', $data);
        assertSame(200, $code, $body);
        assertStringContains('data-import-busy="1"', $body);

        return $this->finished($url);
    }

    private function finished(string $url): string
    {
        for ($attempt = 0; $attempt < 300; $attempt++) {
            $body = $this->request($url, '/local/')[1];
            if (str_contains($body, 'data-import-busy="0"')) return $body;
            usleep(200000);
        }
        throw new RuntimeException('The import did not finish: ' . substr($body, (int) strpos($body, 'data-import-root'), 3000));
    }

    public function testRemoteRelayRegistersAKeyThroughFixedPathsOnly(): void
    {
        $w = new ImportTestWorkspace();
        $target = $w->repository('target', true);
        $w->git($target);
        // A stand-in source that logs each request and answers the registration calls.
        $w->put($w->root . '/relay-source.php', '<?php
            $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            file_put_contents(__DIR__ . "/relay-requests.log", $_SERVER["REQUEST_METHOD"] . " " . $path . "\n", FILE_APPEND);
            header("Content-Type: application/json");
            $exists = is_file(__DIR__ . "/relay-created");
            if ($path === "/api/get_username") { header("Content-Type: text/plain"); echo "Username-Token: ilyag\nApproved-Keys: 1\n"; }
            elseif ($path === "/api/get_profile") { if (!$exists) { http_response_code(404); echo "profile not found\n"; } else { header("Content-Type: text/plain"); echo "Approved: no\n"; } }
            elseif ($path === "/api/prepare_identity") { echo $exists ? json_encode(["status" => "error", "error" => "Identity already exists for this fingerprint."]) : json_encode(["status" => "ok", "prepare_token" => "t", "canonical_record" => "record"]); }
            elseif ($path === "/api/create_identity") { touch(__DIR__ . "/relay-created"); echo json_encode(["status" => "ok", "signature" => $_POST["detached_signature"] ?? ""]); }
            else { http_response_code(418); echo "{}"; }');
        $source = new ImportHttpServer($w->root . '/relay-source.php', $w->root . '/relay-source-server.log');
        $environment = $this->environment($w, $target);
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/dev.log', $environment);
        $token = $this->token($this->request($server->url, '/')[1]);
        $base = ['token' => $token, 'source' => $source->url];

        [$code, $body] = $this->request($server->url, '/local/remote', ['token' => 'wrong'] + $base + ['action' => 'prepare_identity', 'public_key' => 'k']);
        assertSame(403, $code, $body);
        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'prepare_identity', 'public_key' => 'k'], ['Origin: https://evil.example']);
        assertSame(403, $code, $body);
        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'export_everything']);
        assertSame(400, $code, $body);
        $normal = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/normal.log', array_merge($environment, ['V3_DEV_LOG' => '0']));
        assertTrue($this->request($normal->url, '/local/remote', $base + ['action' => 'prepare_identity', 'public_key' => 'k'])[0] !== 200);

        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'prepare_identity', 'public_key' => 'k']);
        assertSame(200, $code, $body);
        assertSame('record', json_decode($body, true)['canonical_record']);
        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'create_identity', 'prepare_token' => 't', 'canonical_record' => 'record', 'detached_signature' => 'sig']);
        assertSame(200, $code, $body);
        assertSame('sig', json_decode($body, true)['signature']);
        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'prepare_identity', 'public_key' => 'k']);
        assertSame(200, $code, $body);
        assertSame(true, json_decode($body, true)['exists']);
        assertSame("POST /api/prepare_identity\nPOST /api/create_identity\nPOST /api/prepare_identity\n", file_get_contents($w->root . '/relay-requests.log'));

        $home = $w->root . '/gpg';
        mkdir($home, 0700);
        $gpg = 'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir ' . escapeshellarg($home);
        exec($gpg . ' --quick-generate-key ilyag ed25519 sign 0 2>&1');
        $publicKey = trim((string) shell_exec($gpg . ' --armor --export')) . "\n";
        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'key_status', 'public_key' => $publicKey]);
        assertSame(200, $code, $body);
        $state = json_decode($body, true);
        assertSame('ilyag', $state['username']);
        assertSame(1, $state['source_approved_keys']);
        assertSame(['registered' => true, 'approved' => false, 'locally_known' => false, 'locally_approved' => false],
            array_intersect_key($state, array_flip(['registered', 'approved', 'locally_known', 'locally_approved'])));
        assertStringContains('/tools/user-access/profiles/openpgp-', $state['source_key_url']);

        [$code, $body] = $this->request($server->url, '/local/remote', $base + ['action' => 'pull', 'identity_id' => $state['identity_id'], 'challenge' => 'c', 'signature' => 's']);
        assertSame(400, $code, $body);
        assertStringContains('Run Import above', json_decode($body, true)['error']);
        assertStringNotContains('authenticate_identity', file_get_contents($w->root . '/relay-requests.log'));
    }

    public function testRemoteRefusesUnlistedPathsAndCarriesSessionCookies(): void
    {
        $seen = [];
        $transport = function (string $method, string $url, array $headers, string $body) use (&$seen): array {
            $seen[] = [$method, $url, $headers];
            if (str_ends_with(parse_url($url, PHP_URL_PATH), '/auth_challenge')) return ['status' => 200, 'headers' => [], 'body' => "challenge=abc123\n"];
            if (str_ends_with(parse_url($url, PHP_URL_PATH), '/authenticate_identity')) return ['status' => 200, 'headers' => ['Set-Cookie: forum_session=s1; Path=/; HttpOnly'], 'body' => "status=ok\napproved=1\n"];
            return ['status' => 200, 'headers' => [], 'body' => json_encode(['status' => 'ok', 'messages' => [], 'transfers' => [], 'next' => null])];
        };
        $remote = new \ForumRewrite\Support\LocalDevelopmentRemote('https://source.example', $transport(...));
        assertSame('abc123', $remote->challenge());
        assertSame(['approved' => true], $remote->signIn('openpgp:' . str_repeat('a', 40), 'abc123', 'sig'));
        $remote->export();
        assertStringContains('Cookie: forum_session=s1', implode("\n", $seen[2][2]));
        assertStringNotContains('Cookie:', implode("\n", $seen[0][2]));
        foreach (['https://source.example/x', 'https://source.example?q=1', 'file:///etc/passwd'] as $bad) {
            try { new \ForumRewrite\Support\LocalDevelopmentRemote($bad, $transport(...)); throw new LogicException('Accepted ' . $bad); }
            catch (RuntimeException $expected) { /* Expected. */ }
        }
        $call = new ReflectionMethod($remote, 'call');
        try { $call->invoke($remote, 'POST', '/api/private_messages'); throw new LogicException('Forwarded an unlisted path'); }
        catch (RuntimeException $expected) { assertStringContains('does not forward', $expected->getMessage()); }
        assertSame(3, count($seen));
    }

    public function testRemoteFailuresNameTheirCauseAndNextStep(): void
    {
        $remote = static fn (\Closure $transport) => new \ForumRewrite\Support\LocalDevelopmentRemote('https://source.example', $transport);
        $cases = [
            'unreachable' => [fn () => throw new RuntimeException('Could not reach https://source.example. Check the connection and retry.'), 'export', 'Check the connection and retry'],
            'old source' => [fn () => ['status' => 404, 'headers' => [], 'body' => 'not found'], 'export', 'does not support pulling messages yet. Deploy a newer version there'],
            'expired sign-in' => [fn () => ['status' => 400, 'headers' => [], 'body' => "error=Authentication challenge is missing or expired.\n"], 'signIn', 'https://source.example: Authentication challenge is missing or expired.'],
            'retired key' => [fn () => ['status' => 403, 'headers' => [], 'body' => "error=This key was retired.\n"], 'signIn', 'This key was retired.'],
            'rate limit' => [fn () => ['status' => 429, 'headers' => [], 'body' => json_encode(['status' => 'error', 'error' => 'Too many history requests. Retry shortly.'])], 'acknowledge', 'Retry shortly'],
        ];
        foreach ($cases as $name => [$transport, $method, $expected]) {
            try {
                $client = $remote($transport);
                match ($method) {
                    'export' => $client->export(),
                    'signIn' => $client->signIn('openpgp:' . str_repeat('a', 40), 'c', 's'),
                    'acknowledge' => $client->acknowledge([]),
                };
                throw new LogicException($name . ' did not fail');
            } catch (RuntimeException $error) {
                assertStringContains($expected, $error->getMessage(), $name);
            }
        }
        $unapproved = $remote(fn () => ['status' => 200, 'headers' => [], 'body' => "status=ok\napproved=0\n"]);
        assertSame(['approved' => false], $unapproved->signIn('openpgp:' . str_repeat('a', 40), 'c', 's'));
        $missing = $remote(fn () => ['status' => 404, 'headers' => [], 'body' => "profile not found\n"]);
        assertSame(['registered' => false, 'approved' => false], $missing->keyState('openpgp:' . str_repeat('a', 40)));
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

    private function request(string $url, string $path, ?array $data = null, array $headers = [], bool $follow = true): array
    {
        $options = ['method' => $data === null ? 'GET' : 'POST', 'ignore_errors' => true, 'timeout' => 60, 'follow_location' => $follow ? 1 : 0,
            'header' => implode("\r\n", array_merge($headers, $data === null ? [] : ['Content-Type: application/x-www-form-urlencoded']))];
        if ($data !== null) $options['content'] = http_build_query($data);
        $body = file_get_contents($url . $path, false, stream_context_create(['http' => $options]));
        // After a followed redirect the last status line is the page's.
        $statusLines = array_values(array_filter($http_response_header, static fn (string $line): bool => str_starts_with($line, 'HTTP/')));
        preg_match('/ ([0-9]{3})(?: |$)/', (string) end($statusLines), $match);
        return [(int) $match[1], $body, $http_response_header];
    }
}
