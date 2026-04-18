<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class CanonicalRecordRepository
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly PostRecordParser $postParser = new PostRecordParser(),
        private readonly IdentityBootstrapRecordParser $identityParser = new IdentityBootstrapRecordParser(),
        private readonly PublicKeyRecordParser $publicKeyParser = new PublicKeyRecordParser(),
        private readonly ApprovalSeedRecordParser $approvalSeedParser = new ApprovalSeedRecordParser(),
        private readonly ThreadLabelRecordParser $threadLabelParser = new ThreadLabelRecordParser(),
        private readonly InstancePublicRecordParser $instanceParser = new InstancePublicRecordParser(),
    ) {
    }

    public function loadPost(string $relativePath): PostRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/posts/');
        $contents = $this->read($relativePath);

        try {
            $record = $this->postParser->parse($contents);
        } catch (CanonicalRecordParseException $exception) {
            if ($exception->getMessage() !== 'Missing required post header: Created-At') {
                throw $exception;
            }

            $legacyCreatedAt = $this->resolveLegacyPostCreatedAt($relativePath);
            if ($legacyCreatedAt === null) {
                throw $exception;
            }

            $record = $this->postParser->parse($this->withCreatedAtHeader($contents, $legacyCreatedAt));
        }

        $expectedPath = CanonicalPathResolver::post($record->postId);
        if ($relativePath !== $expectedPath) {
            throw new CanonicalRecordParseException('Post record path must match Post-ID.');
        }

        return $record;
    }

    public function loadIdentity(string $relativePath): IdentityBootstrapRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/identity/');
        $record = $this->identityParser->parse($this->read($relativePath));

        $expectedPath = CanonicalPathResolver::identity(substr($record->identityId, strlen('openpgp:')));
        if ($relativePath !== $expectedPath) {
            throw new CanonicalRecordParseException('Identity record path must match Identity-ID.');
        }

        return $record;
    }

    public function loadPublicKey(string $relativePath): PublicKeyRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/public-keys/');

        $fileName = basename($relativePath);
        if (!preg_match('/^openpgp-([A-Fa-f0-9]+)\.asc$/', $fileName, $matches)) {
            throw new CanonicalRecordParseException('Public key path must use the canonical OpenPGP filename shape.');
        }

        return $this->publicKeyParser->parse($this->read($relativePath), strtoupper($matches[1]));
    }

    public function loadInstancePublic(string $relativePath): InstancePublicRecord
    {
        if ($relativePath !== CanonicalPathResolver::instancePublic()) {
            throw new CanonicalRecordParseException('Instance public record path must be records/instance/public.txt.');
        }

        return $this->instanceParser->parse($this->read($relativePath));
    }

    public function loadApprovalSeed(string $relativePath): ApprovalSeedRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/approval-seeds/');
        $record = $this->approvalSeedParser->parse($this->read($relativePath));

        $expectedPath = CanonicalPathResolver::approvalSeed(substr($record->approvedIdentityId, strlen('openpgp:')));
        if ($relativePath !== $expectedPath) {
            throw new CanonicalRecordParseException('Approval seed record path must match Approved-Identity-ID.');
        }

        return $record;
    }

    public function loadThreadLabel(string $relativePath): ThreadLabelRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/thread-labels/');
        $record = $this->threadLabelParser->parse($this->read($relativePath));

        $expectedPath = CanonicalPathResolver::threadLabel($record->recordId);
        if ($relativePath !== $expectedPath) {
            throw new CanonicalRecordParseException('Thread-label record path must match Record-ID.');
        }

        return $record;
    }

    private function read(string $relativePath): string
    {
        $path = $this->repositoryRoot . '/' . ltrim($relativePath, '/');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new CanonicalRecordParseException('Unable to read canonical record: ' . $relativePath);
        }

        return $contents;
    }

    private function resolveLegacyPostCreatedAt(string $relativePath): ?string
    {
        $gitCreatedAt = $this->resolveLegacyPostCreatedAtFromGit($relativePath);
        if ($gitCreatedAt !== null) {
            return $gitCreatedAt;
        }

        $path = $this->repositoryRoot . '/' . ltrim($relativePath, '/');
        $mtime = @filemtime($path);
        if ($mtime !== false && $mtime > 0) {
            return gmdate('Y-m-d\TH:i:s\Z', $mtime);
        }

        return null;
    }

    private function resolveLegacyPostCreatedAtFromGit(string $relativePath): ?string
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        $command = sprintf(
            'git -C %s log --diff-filter=A --follow --format=%%aI -- %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($relativePath)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', $output), static fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            return null;
        }

        $value = $lines[array_key_last($lines)];

        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        return $timestamp
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    private function withCreatedAtHeader(string $contents, string $createdAt): string
    {
        $separator = strpos($contents, "\n");
        if ($separator === false) {
            throw new CanonicalRecordParseException('Canonical text record must contain at least one header.');
        }

        return substr($contents, 0, $separator + 1)
            . 'Created-At: ' . $createdAt . "\n"
            . substr($contents, $separator + 1);
    }

    private function assertPathIsWithinFamily(string $relativePath, string $expectedPrefix): void
    {
        if (!str_starts_with($relativePath, $expectedPrefix)) {
            throw new CanonicalRecordParseException('Canonical record path is outside the expected family: ' . $relativePath);
        }
    }
}
