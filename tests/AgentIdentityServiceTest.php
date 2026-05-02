<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Agent\AgentIdentityService;
use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;

final class AgentIdentityServiceTest
{
    public function testReplyAgentBootstrapCreatesApprovedIdentityAndPrivateKeyOutsidePublicArtifacts(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $privateKeyDirectory = sys_get_temp_dir() . '/forum-rewrite-agent-private-' . bin2hex(random_bytes(6));
        $service = new AgentIdentityService(
            $repositoryRoot,
            $databasePath,
            $artifactRoot,
            $privateKeyDirectory,
            new CanonicalRecordRepository($repositoryRoot),
        );

        $result = $service->ensureReplyAgentIdentity();
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $profile = $this->renderMethod($application, 'GET', '/profiles/' . $result['profile_slug']);
        $privateKeyPath = (string) $result['private_key_path'];

        assertSame(true, $result['created']);
        assertSame('reply-agent', $result['username']);
        assertStringContains('openpgp:', (string) $result['identity_id']);
        assertTrue(is_file($repositoryRoot . '/records/identity/identity-' . str_replace(':', '-', (string) $result['identity_id']) . '.txt'));
        assertTrue(is_file($repositoryRoot . '/records/approval-seeds/' . str_replace('openpgp:', 'openpgp-', (string) $result['identity_id']) . '.txt'));
        assertTrue(is_file($privateKeyPath));
        assertStringContains('-----BEGIN PGP PRIVATE KEY BLOCK-----', (string) file_get_contents($privateKeyPath));
        assertFalse(str_starts_with(realpath($privateKeyPath) ?: $privateKeyPath, realpath($artifactRoot) ?: $artifactRoot));
        assertSame([], glob($artifactRoot . '/**/*PRIVATE*') ?: []);
        assertStringContains('Visible username:</strong> reply-agent', $profile);
        assertStringContains('Approved:</strong> yes', $profile);
    }

    public function testReplyAgentBootstrapReusesExistingIdentityWithoutNewCommit(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment();
        $privateKeyDirectory = sys_get_temp_dir() . '/forum-rewrite-agent-private-' . bin2hex(random_bytes(6));
        $service = new AgentIdentityService(
            $repositoryRoot,
            $databasePath,
            $artifactRoot,
            $privateKeyDirectory,
            new CanonicalRecordRepository($repositoryRoot),
        );

        $first = $service->ensureReplyAgentIdentity();
        $headAfterFirst = $this->gitOutput($repositoryRoot, 'rev-parse HEAD');
        $second = $service->ensureReplyAgentIdentity();
        $headAfterSecond = $this->gitOutput($repositoryRoot, 'rev-parse HEAD');

        assertSame(true, $first['created']);
        assertSame(false, $second['created']);
        assertSame($first['identity_id'], $second['identity_id']);
        assertSame($headAfterFirst, $headAfterSecond);
    }

    /**
     * @return array{string,string,string}
     */
    private function createTempEnvironment(): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-agent-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-agent-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-agent-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
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

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
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
}
