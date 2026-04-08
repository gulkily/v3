<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

final class LocalWriteService
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
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
        $this->writeFile('records/posts/' . $postId . '.txt', $contents);
        $this->rebuildReadModel();

        return [
            'status' => 'ok',
            'post_id' => $postId,
            'thread_id' => $postId,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function createReply(array $input): array
    {
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
        $this->writeFile('records/posts/' . $postId . '.txt', $contents);
        $this->rebuildReadModel();

        return [
            'status' => 'ok',
            'post_id' => $postId,
            'thread_id' => $threadId,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function linkIdentity(array $input): array
    {
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
        if (!is_file($this->repositoryRoot . '/' . $publicKeyPath)) {
            $this->writeFile($publicKeyPath, $publicKey);
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
        $this->rebuildReadModel();

        return [
            'status' => 'ok',
            'identity_id' => $identityId,
            'profile_slug' => 'openpgp-' . $fingerprintLower,
            'username' => $username,
        ];
    }

    private function rebuildReadModel(): void
    {
        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
        );
        $builder->rebuild();
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
