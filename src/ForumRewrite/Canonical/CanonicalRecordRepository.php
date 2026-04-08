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
        private readonly InstancePublicRecordParser $instanceParser = new InstancePublicRecordParser(),
    ) {
    }

    public function loadPost(string $relativePath): PostRecord
    {
        $this->assertPathIsWithinFamily($relativePath, 'records/posts/');
        $record = $this->postParser->parse($this->read($relativePath));

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

    private function read(string $relativePath): string
    {
        $path = $this->repositoryRoot . '/' . ltrim($relativePath, '/');
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new CanonicalRecordParseException('Unable to read canonical record: ' . $relativePath);
        }

        return $contents;
    }

    private function assertPathIsWithinFamily(string $relativePath, string $expectedPrefix): void
    {
        if (!str_starts_with($relativePath, $expectedPrefix)) {
            throw new CanonicalRecordParseException('Canonical record path is outside the expected family: ' . $relativePath);
        }
    }
}
