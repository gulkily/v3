<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/FrontendTestServer.php';

/**
 * Runs docs/examples/agent_api_worked_example.sh against a fresh local
 * instance, so the published example keeps working.
 */
final class AgentApiWorkedExampleTest
{
    public function testWorkedExamplePublishesASignedThread(): void
    {
        foreach (['gpg', 'curl', 'python3', 'php', 'perl', 'bash'] as $tool) {
            exec('command -v ' . escapeshellarg($tool), $unused, $code);
            if ($code !== 0) {
                throw new RuntimeException("The agent API worked example needs {$tool}, which is not installed.");
            }
        }

        $projectRoot = dirname(__DIR__);
        $root = sys_get_temp_dir() . '/agent-api-example-' . bin2hex(random_bytes(6));
        mkdir($root . '/sessions', 0700, true);
        mkdir($root . '/gnupg', 0700);
        file_put_contents($root . '/unused.php', '<?php return [];');
        foreach (['public', 'templates', 'docs', 'prompts'] as $name) {
            symlink($projectRoot . '/' . $name, $root . '/' . $name);
        }
        \ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($projectRoot, $root . '/repository');
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();

        $router = '<?php ini_set("session.save_path", ' . var_export($root . '/sessions', true) . '); require ' . var_export($projectRoot . '/autoload.php', true) . ';'
            . ' (new \ForumRewrite\Host\FrontController(' . var_export($root, true) . ', ' . var_export($root . '/repository', true) . ', '
            . var_export($root . '/read.sqlite3', true) . ', ' . var_export($root . '/static', true) . '))->handle($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"], $_COOKIE);';
        $server = new FrontendTestServer($router, null, [
            'FORUM_SECRETS_PATH' => $root . '/unused.php',
            'FORUM_STATIC_HTML_ROOT' => $root . '/static',
            'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/artifacts',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $root . '/tasks.sqlite3',
            'FAST_SCORING_ENABLED' => 'false',
            'FORUM_APP_VERSION_NOTIFICATION' => 'false',
        ]);
        try {
            $command = 'BASE_URL=' . escapeshellarg($server->url) . ' USERNAME=example-agent GNUPGHOME=' . escapeshellarg($root . '/gnupg')
                . ' bash ' . escapeshellarg($projectRoot . '/docs/examples/agent_api_worked_example.sh') . ' 2>&1';
            exec($command, $output, $exitCode);
            $transcript = implode("\n", $output);
            if (getenv('AGENT_API_EXAMPLE_TRANSCRIPT')) {
                file_put_contents((string) getenv('AGENT_API_EXAMPLE_TRANSCRIPT'), $transcript);
            }
            if ($exitCode !== 0) {
                throw new RuntimeException("Worked example failed ({$exitCode}):\n{$transcript}\n" . @file_get_contents($server->directory . '/server.log'));
            }

            preg_match('/^post_id=(\S+)$/m', $transcript, $post);
            preg_match('/^identity_id=(openpgp:[0-9a-f]+)$/m', $transcript, $identity);
            assertTrue(isset($post[1], $identity[1]), "Missing IDs in transcript:\n" . $transcript);
            assertStringContains('Post-ID: ' . $post[1], $transcript);
            assertStringContains('Author: example-agent', $transcript);
            assertStringContains('This thread was published with the agent API worked example.', $transcript);

            $profile = (string) file_get_contents($server->url . '/api/get_profile?profile_slug=' . str_replace(':', '-', $identity[1]));
            assertStringContains('Username: example-agent', $profile);
            assertStringContains('Posts: 1', $profile);

            // Exercise the file publisher with a real instance and GPG key.
            $body = "File publisher body & + =\n\nSecond paragraph.\n";
            file_put_contents($root . '/post.txt', $body);
            // Only this disposable test keyring bypasses interactive passphrase entry.
            exec('command -v gpg', $gpgPaths);
            mkdir($root . '/bin', 0700);
            file_put_contents($root . '/bin/gpg', "#!/bin/sh\nexec " . escapeshellarg($gpgPaths[0])
                . " --batch --pinentry-mode loopback --passphrase '' \"\$@\"\n");
            chmod($root . '/bin/gpg', 0700);
            foreach (['py' => 'python3', 'php' => 'php', 'pl' => 'perl'] as $extension => $interpreter) {
                $generatedName = 'file-publisher-' . $extension . '-agent';
                $threadId = null;
                $signingFingerprint = substr($identity[1], strlen('openpgp:'));
                foreach (['thread', 'reply', 'generated', 'generated-reply'] as $kind) {
                    if ($kind === 'generated') {
                        $threadId = null;
                    }
                    $isReply = str_contains($kind, 'reply');
                    $publishCommand = 'GNUPGHOME=' . escapeshellarg($root . '/gnupg')
                        . ' PATH=' . escapeshellarg($root . '/bin:' . getenv('PATH'))
                        . ' ' . $interpreter . ' ' . escapeshellarg($projectRoot . '/scripts/publish_text.' . $extension)
                        . ' ' . escapeshellarg($server->url) . ' ' . escapeshellarg($root . '/post.txt')
                        . ($kind === 'generated' ? ' --generate-key ' . escapeshellarg($generatedName)
                            : ' --signing-key ' . escapeshellarg($signingFingerprint))
                        . (!$isReply ? ' --subject ' . escapeshellarg('Signed file')
                            : ' --thread-id ' . escapeshellarg($threadId));
                    $publishOutput = [];
                    exec($publishCommand . ' 2>&1', $publishOutput, $publishCode);
                    $publishTranscript = implode("\n", $publishOutput);
                    assertTrue($publishCode === 0, $publishTranscript);
                    if ($kind === 'generated') {
                        preg_match('/^Signing-key: ([A-F0-9]+)$/m', $publishTranscript, $generated);
                        assertTrue(isset($generated[1]), $publishTranscript);
                        $signingFingerprint = $generated[1];
                        assertStringContains('Identity-ID: openpgp:' . strtolower($signingFingerprint), $publishTranscript);
                    }
                    preg_match('/^Post-ID: (\S+)$/m', $publishTranscript, $published);
                    assertTrue(isset($published[1]), $publishTranscript);
                    $threadId ??= $published[1];
                    $contents = (string) file_get_contents($server->url . '/api/get_post?post_id=' . rawurlencode($published[1]));
                    assertStringContains('Thread-ID: ' . $threadId, $contents);
                    assertStringContains('Author: ' . (str_starts_with($kind, 'generated') ? $generatedName : 'example-agent'), $contents);
                    assertStringContains(trim($body), $contents);
                    $files = glob($root . '/repository/records/posts/*/*/*/' . $published[1] . '.txt');
                    assertTrue(count($files) === 1, 'Expected a canonical post file.');
                    exec('gpg --homedir ' . escapeshellarg($root . '/gnupg') . ' --verify '
                        . escapeshellarg($files[0] . '.asc') . ' ' . escapeshellarg($files[0]) . ' 2>&1', $verification, $verifyCode);
                    assertTrue($verifyCode === 0, 'File publisher signature did not verify.');
                }
            }
        } finally {
            $server->close();
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
