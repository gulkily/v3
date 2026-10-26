<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * Schema version 30 integration regression: a read model stamped with a prior schema
 * identity and lacking the viewer_tags table must be rebuilt by normal
 * application startup before the thread page's viewer-tag lookups run. Mirrors
 * the corrected ReadModelHotIndexRebuildTest (startup-driven, not a manual
 * rebuild).
 */
final class ReadModelViewerTagsRebuildTest
{
    public function testStaleModelWithoutViewerTagsRebuildsOnStartupBeforeServing(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $livePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();

            // Simulate the main-branch version 29 model: drop viewer_tags and stamp a prior identity.
            $this->seedStaleModelWithoutViewerTags($livePath);

            $stale = new PDO('sqlite:' . $livePath);
            $stale->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            assertSame(false, $this->tableExists($stale, 'viewer_tags'), 'viewer_tags should be absent on the stale model');
            assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($stale)));

            // Only schema mismatch should trigger recovery: repo identity unchanged.
            $metadata = ReadModelMetadata::readMetadata($stale);
            assertSame($fixture->repository, $metadata['repository_root']);
            assertSame(ReadModelMetadata::repositoryHead($fixture->repository), $metadata['repository_head']);
            $stale->exec("UPDATE posts SET body = 'stale reply before startup' WHERE post_id = 'reply-001'");
            $stale = null;

            $app = new Application(dirname(__DIR__), $fixture->repository, $livePath);
            ob_start();
            try {
                $app->handle('GET', '/threads/root-001');
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            assertStringContains('Hello world', $html);
            assertStringContains('Reply body.', $html);
            assertStringNotContains('stale reply before startup', $html);

            $rebuilt = new PDO('sqlite:' . $livePath);
            $rebuilt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            assertSame(true, $this->tableExists($rebuilt, 'viewer_tags'), 'viewer_tags should be restored after rebuild');
            assertSame(true, $this->indexExists($rebuilt, 'viewer_tags_identity_idx'), 'viewer_tags index should be restored');
            assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($rebuilt)));
            assertSame('schema_identity_mismatch', ReadModelMetadata::readMetadata($rebuilt)['rebuild_reason']);
        } finally {
            $fixture->remove();
        }
    }

    private function seedStaleModelWithoutViewerTags(string $livePath): void
    {
        $pdo = new PDO('sqlite:' . $livePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DROP INDEX IF EXISTS viewer_tags_identity_idx');
        $pdo->exec('DROP TABLE IF EXISTS viewer_tags');
        $stmt = $pdo->prepare('UPDATE metadata SET value = :value WHERE key = :key');
        $stmt->execute(['value' => '29', 'key' => 'schema_version']);
        $stmt->execute(['value' => 'stale-fingerprint-pre-30', 'key' => 'schema_fingerprint']);
    }

    private function tableExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $name]);

        return $stmt->fetchColumn() !== false;
    }

    private function indexExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = :name");
        $stmt->execute(['name' => $name]);

        return $stmt->fetchColumn() !== false;
    }
}
