<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\ApprovalSeedRecordParser;
use ForumRewrite\Canonical\IdentityBootstrapRecordParser;
use ForumRewrite\Canonical\InstancePublicRecordParser;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\PublicKeyRecordParser;

require __DIR__ . '/../autoload.php';

final class CanonicalRecordParsersTest
{
    private string $fixtureRoot;

    public function __construct()
    {
        $this->fixtureRoot = __DIR__ . '/fixtures/parity_minimal_v1/records';
    }

    public function testParsesRootPostFixture(): void
    {
        $record = (new PostRecordParser())->parse($this->readFixture('posts/root-001.txt'));

        assertSame('root-001', $record->postId);
        assertSame('2026-04-10T12:00:00Z', $record->createdAt);
        assertSame(['general', 'meta'], $record->boardTags);
        assertNullValue($record->threadId);
        assertNullValue($record->parentId);
        assertSame('Hello world', $record->subject);
        assertTrue($record->isRoot());
        assertSame("First line preview.\nSecond line body.\n", $record->body);
    }

    public function testParsesReplyFixture(): void
    {
        $record = (new PostRecordParser())->parse($this->readFixture('posts/reply-001.txt'));

        assertSame('reply-001', $record->postId);
        assertSame('2026-04-10T12:05:00Z', $record->createdAt);
        assertSame(['general'], $record->boardTags);
        assertSame('root-001', $record->threadId);
        assertSame('root-001', $record->parentId);
        assertNullValue($record->authorIdentityId);
        assertTrue($record->isReply());
    }

    public function testParsesAuthoredReplyHeader(): void
    {
        $contents = "Post-ID: reply-002\nCreated-At: 2026-04-10T12:06:00Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: root-001\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\nBody.\n";

        $record = (new PostRecordParser())->parse($contents);

        assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $record->authorIdentityId);
    }

    public function testRejectsReplyWithTypedRootHeader(): void
    {
        $contents = "Post-ID: reply-002\nCreated-At: 2026-04-10T12:06:00Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: root-001\nThread-Type: task\n\nBody.\n";

        assertThrows(
            static fn () => (new PostRecordParser())->parse($contents),
            'Replies must not include Thread-Type.'
        );
    }

    public function testParsesTypedTaskRoot(): void
    {
        $contents = "Post-ID: T01\nCreated-At: 2026-04-10T12:07:00Z\nBoard-Tags: planning\nSubject: Publish planning files\nThread-Type: task\nTask-Status: proposed\nTask-Presentability-Impact: 0.94\nTask-Implementation-Difficulty: 0.34\nTask-Depends-On: root-001 root-002\nTask-Sources: todo.txt; docs/plans/plan.md\n\nExpose raw planning files.\n";

        $record = (new PostRecordParser())->parse($contents);

        assertSame('task', $record->threadType);
        assertSame('proposed', $record->taskStatus);
        assertSame(0.94, $record->taskPresentabilityImpact);
        assertSame(0.34, $record->taskImplementationDifficulty);
        assertSame(['root-001', 'root-002'], $record->taskDependsOn);
        assertSame(['todo.txt', 'docs/plans/plan.md'], $record->taskSources);
    }

    public function testRejectsInvalidCreatedAtHeader(): void
    {
        $contents = "Post-ID: root-002\nCreated-At: 2026-04-10 12:00:00\nBoard-Tags: general\n\nBody.\n";

        assertThrows(
            static fn () => (new PostRecordParser())->parse($contents),
            'Created-At must use RFC 3339 UTC format like 2026-04-13T12:34:56Z.'
        );
    }

    public function testParsesIdentityFixture(): void
    {
        $record = (new IdentityBootstrapRecordParser())->parse(
            $this->readFixture('identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt')
        );

        assertSame('identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $record->postId);
        assertSame('guest', $record->username);
        assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $record->identityId);
        assertSame('openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $record->identitySlug());
        assertSame('root-001', $record->bootstrapByThread);
    }

    public function testRejectsIdentityWithMismatchedDerivedIds(): void
    {
        $contents = "Post-ID: identity-openpgp-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\nBoard-Tags: identity\nSubject: identity bootstrap\nIdentity-ID: openpgp:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\nSigner-Fingerprint: AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\nBootstrap-By-Post: root-001\nBootstrap-By-Thread: root-001\n\n-----BEGIN PGP PUBLIC KEY BLOCK-----\n-----END PGP PUBLIC KEY BLOCK-----\n";

        assertThrows(
            static fn () => (new IdentityBootstrapRecordParser())->parse($contents),
            'Identity-ID must be derived from Signer-Fingerprint.'
        );
    }

    public function testParsesPublicKeyFixture(): void
    {
        $record = (new PublicKeyRecordParser())->parse(
            $this->readFixture('public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc'),
            '0168FF20EB09C3EA6193BD3C92A73AA7D20A0954'
        );

        assertSame('0168FF20EB09C3EA6193BD3C92A73AA7D20A0954', $record->fingerprint);
        assertTrue(str_contains($record->armoredKey, 'BEGIN PGP PUBLIC KEY BLOCK'));
    }

    public function testParsesApprovalSeedFixture(): void
    {
        $record = (new ApprovalSeedRecordParser())->parse(
            $this->readFixture('approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt')
        );

        assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $record->approvedIdentityId);
        assertSame('initial approved fixture user', $record->seedReason);
    }

    public function testParsesInstanceFixture(): void
    {
        $record = (new InstancePublicRecordParser())->parse($this->readFixture('instance/public.txt'));

        assertSame('zenmemes', $record->headers['Instance-Name']);
        assertSame("zenmemes summary.\n", $record->body);
    }

    public function testRepositoryLoadsFixtureRecordsByFamily(): void
    {
        $repository = new CanonicalRecordRepository(__DIR__ . '/fixtures/parity_minimal_v1');

        $post = $repository->loadPost('records/posts/root-001.txt');
        $identity = $repository->loadIdentity('records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt');
        $publicKey = $repository->loadPublicKey('records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc');
        $approvalSeed = $repository->loadApprovalSeed('records/approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt');
        $instance = $repository->loadInstancePublic('records/instance/public.txt');

        assertSame('root-001', $post->postId);
        assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $identity->identityId);
        assertSame('0168FF20EB09C3EA6193BD3C92A73AA7D20A0954', $publicKey->fingerprint);
        assertSame('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $approvalSeed->approvedIdentityId);
        assertSame('zenmemes', $instance->headers['Instance-Name']);
    }

    public function testRepositoryAcceptsLegacyLowercasePublicKeyPath(): void
    {
        $tempRoot = $this->createTempFixtureRoot();
        $source = $tempRoot . '/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc';
        $target = $tempRoot . '/records/public-keys/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.asc';
        rename($source, $target);

        $repository = new CanonicalRecordRepository($tempRoot);

        $record = $repository->loadPublicKey('records/public-keys/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.asc');

        assertSame('0168FF20EB09C3EA6193BD3C92A73AA7D20A0954', $record->fingerprint);
    }

    public function testRepositoryRejectsPostPathMismatch(): void
    {
        $tempRoot = $this->createTempFixtureRoot();
        rename(
            $tempRoot . '/records/posts/root-001.txt',
            $tempRoot . '/records/posts/not-the-post-id.txt'
        );
        $repository = new CanonicalRecordRepository($tempRoot);

        assertThrows(
            static fn () => $repository->loadPost('records/posts/not-the-post-id.txt'),
            'Post record path must match Post-ID.'
        );
    }

    public function testCanonicalPathResolverMatchesSpecs(): void
    {
        assertSame('records/posts/root-001.txt', CanonicalPathResolver::post('root-001'));
        assertSame(
            'records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt',
            CanonicalPathResolver::identity('0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
        );
        assertSame(
            'records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc',
            CanonicalPathResolver::publicKey('0168FF20EB09C3EA6193BD3C92A73AA7D20A0954')
        );
        assertSame(
            'records/approval-seeds/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt',
            CanonicalPathResolver::approvalSeed('0168ff20eb09c3ea6193bd3c92a73aa7d20a0954')
        );
        assertSame('records/instance/public.txt', CanonicalPathResolver::instancePublic());
    }

    private function readFixture(string $relativePath): string
    {
        $path = $this->fixtureRoot . '/' . $relativePath;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read fixture: ' . $path);
        }

        return $contents;
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-tests-' . bin2hex(random_bytes(6));
        mkdir($tempRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $tempRoot);

        return $tempRoot;
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
}

function assertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting that values are identical.');
    }
}

function assertNullValue(mixed $actual): void
{
    if ($actual !== null) {
        throw new RuntimeException('Failed asserting that value is null.');
    }
}

function assertTrue(bool $actual): void
{
    if ($actual !== true) {
        throw new RuntimeException('Failed asserting that value is true.');
    }
}

function assertThrows(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (CanonicalRecordParseException $exception) {
        if ($exception->getMessage() !== $expectedMessage) {
            throw new RuntimeException('Unexpected exception message: ' . $exception->getMessage());
        }

        return;
    }

    throw new RuntimeException('Expected CanonicalRecordParseException was not thrown.');
}
