<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Write\LocalWriteService;

final class WriteApiSmokeTest
{
    public function testCreateThreadAndReplyApisWriteCanonicalFilesCommitAndInvalidateArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $threadResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );
        $threadId = $this->extractValue($threadResponse, 'thread_id');
        $threadCommitSha = $this->extractValue($threadResponse, 'commit_sha');
        $threadPage = $this->renderMethod($application, 'GET', '/threads/' . $threadId);
        $this->seedArtifacts($artifactRoot, [
            '/threads/' . $threadId . '.html',
            '/posts/' . $threadId . '.html',
            '/index.html',
        ]);

        $replyResponse = $this->renderMethod(
            $application,
            'POST',
            '/api/create_reply?thread_id=' . rawurlencode($threadId) . '&parent_id=' . rawurlencode($threadId) . '&body=Reply%20body'
        );
        $replyId = $this->extractValue($replyResponse, 'post_id');
        $replyCommitSha = $this->extractValue($replyResponse, 'commit_sha');
        $postPage = $this->renderMethod($application, 'GET', '/posts/' . $replyId);

        assertStringContains('status=ok', $threadResponse);
        assertTrue(strlen($threadCommitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/posts/' . $threadId . '.txt'));
        assertStringContains('New Thread', $threadPage);
        assertFalse(is_file($artifactRoot . '/index.html'));
        assertStringContains('status=ok', $replyResponse);
        assertTrue(strlen($replyCommitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/posts/' . $replyId . '.txt'));
        assertStringContains('Reply body', $postPage);
        assertFalse(is_file($artifactRoot . '/threads/' . $threadId . '.html'));
        assertFalse(is_file($artifactRoot . '/posts/' . $replyId . '.html'));
        assertTrue(is_file($artifactRoot . '/posts/' . $threadId . '.html'));
        assertSame($replyCommitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testLinkIdentityUsesPublicKeyUserIdForUsernameAndInvalidatesProfileArtifact(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $this->seedArtifacts($artifactRoot, [
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $_POST = [
            'public_key' => $this->readFixturePublicKey(),
        ];
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/link_identity?bootstrap_post_id=root-001'
        );
        $_POST = [];
        $commitSha = $this->extractValue($response, 'commit_sha');

        $profile = $this->renderMethod(
            $application,
            'GET',
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $response);
        assertStringContains('username=forum-user', $response);
        assertTrue(strlen($commitSha) === 40);
        assertTrue(is_file($repositoryRoot . '/records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt'));
        assertTrue(is_file($repositoryRoot . '/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc'));
        assertStringContains('Visible username:</strong> forum-user', $profile);
        assertStringContains('Profile openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $usernameRoute);
        assertFalse(is_file($artifactRoot . '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.html'));
        assertSame($commitSha, $this->gitOutput($repositoryRoot, 'rev-parse HEAD'));
    }

    public function testHtmlComposeAndAccountFormsSubmitAgainstWritableRepo(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'board_tags' => 'general',
            'subject' => 'Form Thread',
            'body' => 'Form body',
        ];
        $threadResponse = $this->renderMethod($application, 'POST', '/compose/thread');
        $_POST = [];

        $threadId = $this->extractHrefId($threadResponse, '/threads/');

        $_POST = [
            'thread_id' => $threadId,
            'parent_id' => $threadId,
            'board_tags' => 'general',
            'body' => 'Form reply body',
        ];
        $replyResponse = $this->renderMethod($application, 'POST', '/compose/reply');
        $_POST = [];

        $_POST = [
            'bootstrap_post_id' => 'root-001',
            'public_key' => $this->readFixturePublicKey(),
        ];
        $accountResponse = $this->renderMethod($application, 'POST', '/account/key/');
        $_POST = [];

        assertStringContains('Redirecting', $threadResponse);
        assertStringContains('Created thread', $threadResponse);
        assertStringContains('Commit ', $threadResponse);
        assertStringContains('Created reply', $replyResponse);
        assertStringContains('Commit ', $replyResponse);
        assertStringContains('Linked identity', $accountResponse);
        assertStringContains('Commit ', $accountResponse);
    }

    public function testWriteApiReportsGitFailureWithoutInvalidatingArtifacts(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->seedArtifacts($artifactRoot, [
            '/index.html',
        ]);

        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $response = $this->renderMethod(
            $application,
            'POST',
            '/api/create_thread?board_tags=general&subject=New%20Thread&body=Thread%20body'
        );

        assertStringContains('error=Writable repository must be a git checkout before writes are allowed.', $response);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testWriteMarksDerivedStateStaleWhenRefreshFailsAfterCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->seedArtifacts($artifactRoot, ['/index.html']);
        $service = new class($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot)) extends LocalWriteService {
            protected function rebuildReadModel(): void
            {
                throw new RuntimeException('simulated refresh failure');
            }
        };

        try {
            $service->createThread([
                'board_tags' => 'general',
                'subject' => 'New Thread',
                'body' => 'Thread body',
            ]);
            throw new RuntimeException('Expected refresh failure.');
        } catch (RuntimeException $exception) {
            assertStringContains('Derived state marked stale', $exception->getMessage());
        }

        $staleMarkerPath = dirname($databasePath) . '/read_model_stale.json';
        assertTrue(is_file($staleMarkerPath));
        $staleMarker = json_decode((string) file_get_contents($staleMarkerPath), true, 512, JSON_THROW_ON_ERROR);
        assertSame('write_refresh_failed', $staleMarker['reason'] ?? null);
        assertTrue(strlen((string) ($staleMarker['commit_sha'] ?? '')) === 40);
        assertTrue(is_file($artifactRoot . '/index.html'));
    }

    public function testLinkIdentityAllowsDuplicateUsernameTokensWithoutBreakingRebuild(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $this->deleteDirectoryContents($repositoryRoot . '/records/identity');
        $this->deleteDirectoryContents($repositoryRoot . '/records/public-keys');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);

        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $firstResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [
            'public_key' => $this->generatePublicKey('forum-user'),
        ];
        $secondResponse = $this->renderMethod($application, 'POST', '/api/link_identity?bootstrap_post_id=root-001');
        $_POST = [];

        $status = $this->renderMethod($application, 'GET', '/api/read_model_status');
        $usernameRoute = $this->renderMethod($application, 'GET', '/user/forum-user');

        assertStringContains('status=ok', $firstResponse);
        assertStringContains('status=ok', $secondResponse);
        assertStringContains('status=ready', $status);
        assertStringContains('Profile openpgp-', $usernameRoute);
    }

    /**
     * @return array{string,string,string}
     */
    private function createTempEnvironment(): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function readFixturePublicKey(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc');
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function extractValue(string $response, string $key): string
    {
        foreach (explode("\n", trim($response)) as $line) {
            if (str_starts_with($line, $key . '=')) {
                return substr($line, strlen($key) + 1);
            }
        }

        throw new RuntimeException('Missing response key: ' . $key);
    }

    private function extractHrefId(string $response, string $prefix): string
    {
        if (preg_match('#href="' . preg_quote($prefix, '#') . '([^"]+)"#', $response, $matches) !== 1) {
            throw new RuntimeException('Missing href prefix: ' . $prefix);
        }

        return $matches[1];
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }

    private function deleteDirectoryContents(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    /**
     * @param list<string> $relativePaths
     */
    private function seedArtifacts(string $artifactRoot, array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            $path = $artifactRoot . $relativePath;
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, '<!doctype html><html><body>artifact</body></html>');
        }
    }

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
    }

    private function generatePublicKey(string $username): string
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-gpg-home-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);
        $this->runCommand(
            $home,
            'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
            . $homedir . ' --quick-generate-key ' . escapeshellarg($username) . ' ed25519 sign 0'
        );

        $publicKey = $this->runCommand(
            $home,
            'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export'
        );

        $this->deleteTree($home);

        return trim($publicKey) . "\n";
    }

    private function gitOutput(string $repositoryRoot, string $command): string
    {
        return trim($this->runCommand($repositoryRoot, 'git ' . $command));
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

}

function assertFalse(bool $condition): void
{
    if ($condition) {
        throw new RuntimeException('Failed asserting that condition is false.');
    }
}
