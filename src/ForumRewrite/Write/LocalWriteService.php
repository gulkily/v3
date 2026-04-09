<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

class LocalWriteService
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
        private readonly CanonicalRecordRepository $canonicalRepository,
        private readonly OpenPgpKeyInspector $keyInspector = new OpenPgpKeyInspector(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function createThread(array $input): array
    {
        return $this->executionLock()->withExclusiveLock(function () use ($input): array {
            $this->assertWritableRepository();
            $postId = $this->generateRecordId('thread');
            $boardTags = $this->normalizeBoardTags((string) ($input['board_tags'] ?? 'general'));
            $subject = $this->normalizeAsciiLine((string) ($input['subject'] ?? ''), 'subject');
            $body = $this->normalizeAsciiBody((string) ($input['body'] ?? ''), 'body');

            $contents = "Post-ID: {$postId}\n"
                . "Board-Tags: {$boardTags}\n"
                . ($subject !== '' ? "Subject: {$subject}\n" : '')
                . "\n{$body}";

            (new PostRecordParser())->parse($contents);
            $recordPath = 'records/posts/' . $postId . '.txt';
            $this->writeFile($recordPath, $contents);
            $commitSha = $this->commitCanonicalWrite([$recordPath], 'Create thread ' . $postId);
            $this->refreshDerivedStateAfterCommit($commitSha);
            $this->invalidator()->invalidateBoardThread($postId);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $postId,
                'commit_sha' => $commitSha,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function createReply(array $input): array
    {
        return $this->executionLock()->withExclusiveLock(function () use ($input): array {
            $this->assertWritableRepository();
            $threadId = $this->requireAsciiToken((string) ($input['thread_id'] ?? ''), 'thread_id');
            $parentId = $this->requireAsciiToken((string) ($input['parent_id'] ?? ''), 'parent_id');
            $body = $this->normalizeAsciiBody((string) ($input['body'] ?? ''), 'body');
            $boardTags = $this->normalizeBoardTags((string) ($input['board_tags'] ?? 'general'));

            $thread = $this->canonicalRepository->loadPost('records/posts/' . $threadId . '.txt');
            $parent = $this->canonicalRepository->loadPost('records/posts/' . $parentId . '.txt');
            $parentThreadId = $parent->threadId ?? $parent->postId;
            if ($thread->postId !== $threadId || $parentThreadId !== $threadId) {
                throw new RuntimeException('Parent post must belong to the target thread.');
            }

            $postId = $this->generateRecordId('reply');
            $contents = "Post-ID: {$postId}\n"
                . "Board-Tags: {$boardTags}\n"
                . "Thread-ID: {$threadId}\n"
                . "Parent-ID: {$parentId}\n"
                . "\n{$body}";

            (new PostRecordParser())->parse($contents);
            $recordPath = 'records/posts/' . $postId . '.txt';
            $this->writeFile($recordPath, $contents);
            $commitSha = $this->commitCanonicalWrite([$recordPath], 'Create reply ' . $postId);
            $this->refreshDerivedStateAfterCommit($commitSha);
            $this->invalidator()->invalidateReply($threadId, $postId);

            return [
                'status' => 'ok',
                'post_id' => $postId,
                'thread_id' => $threadId,
                'commit_sha' => $commitSha,
            ];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function linkIdentity(array $input): array
    {
        return $this->executionLock()->withExclusiveLock(function () use ($input): array {
            $this->assertWritableRepository();
            $publicKey = $this->normalizeAsciiBody((string) ($input['public_key'] ?? ''), 'public_key');
            $bootstrapPostId = $this->requireAsciiToken((string) ($input['bootstrap_post_id'] ?? ''), 'bootstrap_post_id');
            $bootstrapPost = $this->canonicalRepository->loadPost('records/posts/' . $bootstrapPostId . '.txt');
            $bootstrapThreadId = $bootstrapPost->threadId ?? $bootstrapPost->postId;

            $inspected = $this->keyInspector->inspect($publicKey);
            $fingerprintUpper = $inspected['fingerprint'];
            $fingerprintLower = strtolower($fingerprintUpper);
            $username = $inspected['username'];
            $identityId = 'openpgp:' . $fingerprintLower;
            $postId = 'identity-openpgp-' . $fingerprintLower;

            $identityPath = 'records/identity/' . $postId . '.txt';
            if (is_file($this->repositoryRoot . '/' . $identityPath)) {
                throw new RuntimeException('Identity already exists for this fingerprint.');
            }

            $publicKeyPath = 'records/public-keys/openpgp-' . $fingerprintUpper . '.asc';
            $writtenPaths = [];
            if (!is_file($this->repositoryRoot . '/' . $publicKeyPath)) {
                $this->writeFile($publicKeyPath, $publicKey);
                $writtenPaths[] = $publicKeyPath;
            }

            $contents = "Post-ID: {$postId}\n"
                . "Board-Tags: identity\n"
                . "Subject: identity bootstrap\n"
                . "Username: {$username}\n"
                . "Identity-ID: {$identityId}\n"
                . "Signer-Fingerprint: {$fingerprintUpper}\n"
                . "Bootstrap-By-Post: {$bootstrapPostId}\n"
                . "Bootstrap-By-Thread: {$bootstrapThreadId}\n"
                . "\n{$publicKey}";

            (new IdentityBootstrapRecordParser())->parse($contents);
            $this->writeFile($identityPath, $contents);
            $writtenPaths[] = $identityPath;
            $commitSha = $this->commitCanonicalWrite($writtenPaths, 'Link identity ' . $postId);
            $this->refreshDerivedStateAfterCommit($commitSha);
            $this->invalidator()->invalidateProfile('openpgp-' . $fingerprintLower);

            return [
                'status' => 'ok',
                'identity_id' => $identityId,
                'profile_slug' => 'openpgp-' . $fingerprintLower,
                'username' => $username,
                'commit_sha' => $commitSha,
            ];
        });
    }

    protected function rebuildReadModel(): void
    {
        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
            'write_refresh',
        );
        $builder->rebuild();
    }

    private function refreshDerivedStateAfterCommit(string $commitSha): void
    {
        try {
            $this->rebuildReadModel();
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
    }

    /**
     * @param list<string> $relativePaths
     */
    private function commitCanonicalWrite(array $relativePaths, string $message): string
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            throw new RuntimeException('Writable repository must be a git checkout before writes are allowed.');
        }

        $this->runGitCommand(array_merge(['add', '--'], $relativePaths), 'Unable to stage canonical write');
        $this->runGitCommand([
            '-c', 'user.name=Forum Rewrite',
            '-c', 'user.email=forum-rewrite@example.invalid',
            'commit', '-m', $message,
        ], 'Unable to commit canonical write');

        return trim($this->runGitCommand(['rev-parse', 'HEAD'], 'Unable to read commit SHA'));
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $path = $this->repositoryRoot . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $bytes = file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new RuntimeException('Unable to write canonical file: ' . $relativePath);
        }
    }

    private function assertWritableRepository(): void
    {
        if (str_contains($this->repositoryRoot, '/tests/fixtures/')) {
            throw new RuntimeException('Write APIs are disabled against the committed fixture repository. Initialize a local writable copy and set FORUM_REPOSITORY_ROOT.');
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

    private function executionLock(): ExecutionLock
    {
        return new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock');
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

        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($this->repositoryRoot) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException($failureMessage . ': ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function generateRecordId(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, gmdate('YmdHis'), substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function normalizeBoardTags(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9 -]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized !== '' ? $normalized : 'general';
    }

    private function normalizeAsciiLine(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[^\x20-\x7E]/', $value)) {
            throw new RuntimeException("{$field} must be printable ASCII.");
        }

        return $value;
    }

    private function requireAsciiToken(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[^A-Za-z0-9._:-]/', $value)) {
            throw new RuntimeException("{$field} is required and must be an ASCII token.");
        }

        return $value;
    }

    private function normalizeAsciiBody(string $value, string $field): string
    {
        $value = str_replace("\r\n", "\n", trim($value));
        if ($value === '') {
            throw new RuntimeException("{$field} is required.");
        }

        if (preg_match('/[^\x0A\x20-\x7E]/', $value)) {
            throw new RuntimeException("{$field} must be ASCII only.");
        }

        return $value . "\n";
    }
}
