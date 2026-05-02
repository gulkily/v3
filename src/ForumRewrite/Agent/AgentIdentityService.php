<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Canonical\ApprovalSeedRecordParser;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\IdentityBootstrapRecord;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Security\OpenPgpKeyInspector;
use ForumRewrite\Write\StaticArtifactInvalidator;
use RuntimeException;

final class AgentIdentityService
{
    public const USERNAME = 'reply-agent';

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
        private readonly string $privateKeyDirectory,
        private readonly CanonicalRecordRepository $canonicalRepository,
        private readonly OpenPgpKeyInspector $keyInspector = new OpenPgpKeyInspector(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureReplyAgentIdentity(): array
    {
        $existing = $this->findExistingReplyAgentIdentity();
        if ($existing !== null) {
            return $this->resultFromIdentity($existing, false, null, []);
        }

        $this->assertWritableRepository();
        $this->assertPrivateDirectoryIsNotPublic();

        $keypair = $this->generateKeypair();
        $inspected = $this->keyInspector->inspect($keypair['public_key']);
        $fingerprintUpper = $inspected['fingerprint'];
        $fingerprintLower = strtolower($fingerprintUpper);
        $identityId = 'openpgp:' . $fingerprintLower;
        $profileSlug = 'openpgp-' . $fingerprintLower;
        $privateKeyPath = $this->privateKeyDirectory . '/reply-agent-openpgp-' . $fingerprintLower . '.private.asc';

        $this->writePrivateKey($privateKeyPath, $keypair['private_key']);

        $createdAt = gmdate('Y-m-d\TH:i:s\Z');
        $bootstrapPostId = $this->generateRecordId('agent-bootstrap');
        $bootstrapContents = "Post-ID: {$bootstrapPostId}\n"
            . "Created-At: {$createdAt}\n"
            . "Board-Tags: identity internal\n"
            . "Subject: agent identity bootstrap\n"
            . "\nAutomatic reply-agent identity bootstrap anchor.\n";
        (new PostRecordParser())->parse($bootstrapContents);

        $publicKey = trim($keypair['public_key']) . "\n";
        $identityPostId = 'identity-openpgp-' . $fingerprintLower;
        $identityContents = "Post-ID: {$identityPostId}\n"
            . "Board-Tags: identity\n"
            . "Subject: identity bootstrap\n"
            . "Username: " . self::USERNAME . "\n"
            . "Identity-ID: {$identityId}\n"
            . "Signer-Fingerprint: {$fingerprintUpper}\n"
            . "Bootstrap-By-Post: {$bootstrapPostId}\n"
            . "Bootstrap-By-Thread: {$bootstrapPostId}\n"
            . "\n{$publicKey}";
        (new IdentityBootstrapRecordParser())->parse($identityContents);

        $approvalContents = "Approved-Identity-ID: {$identityId}\n"
            . "Seed-Reason: automatic reply agent\n"
            . "\nSeed the canonical reply-agent identity for server-authored agent replies.\n";
        (new ApprovalSeedRecordParser())->parse($approvalContents);

        $written = [
            CanonicalPathResolver::post($bootstrapPostId) => $bootstrapContents,
            CanonicalPathResolver::publicKey($fingerprintUpper) => $publicKey,
            CanonicalPathResolver::identity($fingerprintLower) => $identityContents,
            CanonicalPathResolver::approvalSeed($fingerprintLower) => $approvalContents,
        ];

        foreach ($written as $relativePath => $contents) {
            $this->writeCanonicalFile($relativePath, $contents);
        }

        $commitResult = $this->commitCanonicalWrite(
            array_keys($written),
            'Bootstrap reply-agent identity'
        );
        $timings = array_merge($commitResult['timings'], $this->refreshDerivedState($commitResult['commit_sha']));
        $this->invalidator()->invalidateIdentityLink($profileSlug, $bootstrapPostId, $bootstrapPostId);

        return [
            'status' => 'ok',
            'created' => true,
            'identity_id' => $identityId,
            'profile_slug' => $profileSlug,
            'username' => self::USERNAME,
            'bootstrap_post_id' => $bootstrapPostId,
            'bootstrap_thread_id' => $bootstrapPostId,
            'private_key_path' => $privateKeyPath,
            'commit_sha' => $commitResult['commit_sha'],
            'timings' => $timings,
        ];
    }

    private function findExistingReplyAgentIdentity(): ?IdentityBootstrapRecord
    {
        foreach ($this->findRelativePaths('records/identity') as $relativePath) {
            $identity = $this->canonicalRepository->loadIdentity($relativePath);
            if ($identity->username === self::USERNAME) {
                return $identity;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromIdentity(IdentityBootstrapRecord $identity, bool $created, ?string $privateKeyPath, array $timings): array
    {
        return [
            'status' => 'ok',
            'created' => $created,
            'identity_id' => $identity->identityId,
            'profile_slug' => $identity->identitySlug(),
            'username' => $identity->username,
            'bootstrap_post_id' => $identity->bootstrapByPost,
            'bootstrap_thread_id' => $identity->bootstrapByThread,
            'private_key_path' => $privateKeyPath,
            'timings' => $timings,
        ];
    }

    /**
     * @return array{public_key:string,private_key:string}
     */
    private function generateKeypair(): array
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-agent-gpg-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        $homedir = escapeshellarg($home);

        try {
            $this->runShell(
                'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
                . $homedir . ' --quick-generate-key ' . escapeshellarg(self::USERNAME) . ' ed25519 sign 0',
                $home,
                'Unable to generate reply-agent OpenPGP keypair'
            );
            $publicKey = $this->runShell(
                'gpg --batch --no-tty --homedir ' . $homedir . ' --armor --export',
                $home,
                'Unable to export reply-agent public key'
            );
            $privateKey = $this->runShell(
                'gpg --batch --no-tty --pinentry-mode loopback --passphrase "" --homedir '
                . $homedir . ' --armor --export-secret-keys',
                $home,
                'Unable to export reply-agent private key'
            );
        } finally {
            $this->deleteTree($home);
        }

        return [
            'public_key' => trim($publicKey) . "\n",
            'private_key' => trim($privateKey) . "\n",
        ];
    }

    private function writePrivateKey(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write reply-agent private key.');
        }

        @chmod($path, 0600);
    }

    private function writeCanonicalFile(string $relativePath, string $contents): void
    {
        $path = $this->repositoryRoot . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write canonical file: ' . $relativePath);
        }
    }

    /**
     * @param list<string> $relativePaths
     * @return array{commit_sha:string,timings:array<string,float>}
     */
    private function commitCanonicalWrite(array $relativePaths, string $message): array
    {
        $timings = [];
        $startedAt = hrtime(true);
        $this->runGitCommand(array_merge(['add', '--'], $relativePaths), 'Unable to stage reply-agent identity records');
        $timings['git_add'] = $this->elapsedMilliseconds($startedAt);

        $startedAt = hrtime(true);
        $this->runGitCommand([
            '-c', 'user.name=Forum Rewrite',
            '-c', 'user.email=forum-rewrite@example.invalid',
            'commit', '-m', $message,
        ], 'Unable to commit reply-agent identity records');
        $timings['git_commit'] = $this->elapsedMilliseconds($startedAt);

        $startedAt = hrtime(true);
        $commitSha = trim($this->runGitCommand(['rev-parse', 'HEAD'], 'Unable to read commit SHA'));
        $timings['git_rev_parse'] = $this->elapsedMilliseconds($startedAt);

        return [
            'commit_sha' => $commitSha,
            'timings' => $timings,
        ];
    }

    /**
     * @return array<string,float>
     */
    private function refreshDerivedState(string $commitSha): array
    {
        $startedAt = hrtime(true);
        try {
            $builder = new ReadModelBuilder(
                $this->repositoryRoot,
                $this->databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
                'agent_identity_bootstrap',
            );
            $builder->rebuild();
            $this->staleMarker()->clear();
        } catch (\Throwable $throwable) {
            $this->staleMarker()->mark([
                'reason' => 'write_refresh_failed',
                'commit_sha' => $commitSha,
                'failed_at' => gmdate('c'),
                'message' => $throwable->getMessage(),
            ]);

            throw new RuntimeException(
                'Canonical write committed at ' . $commitSha . ' but read-model refresh failed. Derived state marked stale.'
            );
        }

        $timings = ['read_model_rebuild' => $this->elapsedMilliseconds($startedAt)];
        foreach ($builder->timings() as $name => $duration) {
            $timings['read_model_' . $name] = $duration;
        }

        return $timings;
    }

    /**
     * @return list<string>
     */
    private function findRelativePaths(string $relativeDirectory): array
    {
        $basePath = $this->repositoryRoot . '/' . $relativeDirectory;
        if (!is_dir($basePath)) {
            return [];
        }

        $paths = [];
        foreach (glob($basePath . '/*.txt') ?: [] as $path) {
            $paths[] = substr($path, strlen($this->repositoryRoot) + 1);
        }

        sort($paths);

        return $paths;
    }

    /**
     * @param list<string> $args
     */
    private function runGitCommand(array $args, string $failureMessage): string
    {
        $command = 'git';
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        return $this->runShell($command, $this->repositoryRoot, $failureMessage);
    }

    private function runShell(string $command, string $workdir, string $failureMessage): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException($failureMessage . ': ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function assertWritableRepository(): void
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            throw new RuntimeException('Writable repository must be a git checkout before writes are allowed.');
        }

        if (str_contains($this->repositoryRoot, '/tests/fixtures/')) {
            throw new RuntimeException('Write APIs are disabled against the committed fixture repository. Initialize a local writable copy and set FORUM_REPOSITORY_ROOT.');
        }
    }

    private function assertPrivateDirectoryIsNotPublic(): void
    {
        $artifactRoot = realpath($this->artifactRoot);
        if ($artifactRoot === false) {
            return;
        }

        $privateParent = realpath(dirname($this->privateKeyDirectory));
        if ($privateParent === false) {
            return;
        }

        if ($privateParent === $artifactRoot || str_starts_with($privateParent, rtrim($artifactRoot, '/') . '/')) {
            throw new RuntimeException('reply-agent private key directory must not be under the public artifact root.');
        }
    }

    private function invalidator(): StaticArtifactInvalidator
    {
        return new StaticArtifactInvalidator($this->artifactRoot);
    }

    private function staleMarker(): ReadModelStaleMarker
    {
        return new ReadModelStaleMarker($this->databasePath);
    }

    private function generateRecordId(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, gmdate('YmdHis'), substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
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
