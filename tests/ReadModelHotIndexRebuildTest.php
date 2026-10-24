<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ThreadRepository;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * Schema version 26 regression: a read model stamped with a prior schema
 * identity (older version/fingerprint and missing hot-path indexes) must be
 * detected as incompatible by the same gate Application::ensureReadModel()
 * uses, and a normal rebuild must recreate the indexes before the indexed
 * queries run.
 */
final class ReadModelHotIndexRebuildTest
{
    private const HOT_INDEXES = [
        'posts_thread_sequence_idx',
        'posts_author_identity_idx',
        'threads_last_activity_idx',
    ];

    public function testRebuildCreatesHotPathIndexes(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $livePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();

            $pdo = new PDO('sqlite:' . $livePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            foreach (self::HOT_INDEXES as $index) {
                assertSame(true, $this->indexExists($pdo, $index), "expected index {$index} after rebuild");
            }

            // The freshly built model carries the current schema identity.
            assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($pdo)));
        } finally {
            $fixture->remove();
        }
    }

    public function testStaleSchemaIdentityIsDetectedAndRebuildRestoresIndexes(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $livePath = $fixture->root . '/model.sqlite';
            $builder = new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository));
            $builder->rebuild();

            // Simulate an old-shaped model from before schema version 26:
            // drop the hot-path indexes and stamp a prior schema identity.
            $this->seedStaleSchemaIdentity($livePath);

            $stale = new PDO('sqlite:' . $livePath);
            $stale->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach (self::HOT_INDEXES as $index) {
                assertSame(false, $this->indexExists($stale, $index), "index {$index} should be absent on the stale model");
            }

            // This is the exact gate Application::ensureReadModel() keys on to
            // set rebuildReason = 'schema_identity_mismatch'.
            assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($stale)));
            $stale = null;

            // A normal rebuild (what startup performs on mismatch) restores the
            // indexes and the current identity before any indexed query runs.
            $builder->rebuild();

            $rebuilt = new PDO('sqlite:' . $livePath);
            $rebuilt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach (self::HOT_INDEXES as $index) {
                assertSame(true, $this->indexExists($rebuilt, $index), "index {$index} should be restored after rebuild");
            }
            assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($rebuilt)));

            // The indexed reply query still runs and returns correct rows:
            // every row is a non-root reply of the requested thread.
            $replies = ThreadRepository::replyPostsByThreadId($rebuilt, 'root-001');
            foreach ($replies as $row) {
                assertSame('root-001', (string) $row['thread_id']);
                assertSame(true, (string) $row['post_id'] !== 'root-001');
            }
        } finally {
            $fixture->remove();
        }
    }

    private function seedStaleSchemaIdentity(string $livePath): void
    {
        $pdo = new PDO('sqlite:' . $livePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (self::HOT_INDEXES as $index) {
            $pdo->exec('DROP INDEX IF EXISTS ' . $index);
        }
        // A plausible pre-26 identity: any value that differs from the current
        // expected identity makes hasExpectedSchemaIdentity() return false.
        $stmt = $pdo->prepare('UPDATE metadata SET value = :value WHERE key = :key');
        $stmt->execute(['value' => '25', 'key' => 'schema_version']);
        $stmt->execute(['value' => 'stale-fingerprint-pre-26', 'key' => 'schema_fingerprint']);
    }

    private function indexExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :name");
        $stmt->execute(['name' => $name]);

        return $stmt->fetchColumn() !== false;
    }
}
