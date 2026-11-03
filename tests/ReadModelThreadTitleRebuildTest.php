<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * Schema version 44 regression: a read model stamped with the prior schema identity and
 * lacking threads.subject_author_identity_id / viewer_tags.eligible must be rebuilt by
 * normal application startup before the thread page reads the retitled subject.
 */
final class ReadModelThreadTitleRebuildTest
{
    public function testStaleVersion43ModelRebuildsOnStartupAndServesAuthoredTitle(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
            $replyPath = $fixture->repository . '/records/posts/reply-title-1.txt';
            file_put_contents($replyPath, "Post-ID: reply-title-1\nCreated-At: 2026-04-15T15:59:00Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: root-001\nAuthor-Identity-ID: {$author}\n\ntitle: Renamed by reply\n");
            file_put_contents(
                $fixture->repository . '/records/thread-subjects/thread-subject-20260415160000-aa000001.txt',
                "Record-ID: thread-subject-20260415160000-aa000001\nCreated-At: 2026-04-15T16:00:00Z\nThread-ID: root-001\nOperation: set\nSubject: Renamed by reply\nAuthor-Identity-ID: {$author}\nSource-Post-ID: reply-title-1\nSource-Post-SHA256: " . hash('sha256', (string) file_get_contents($replyPath)) . "\nReason: Title directive in reply\n\n"
            );
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'authored title');

            $livePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $this->seedStaleVersion43Model($livePath);

            $stale = new PDO('sqlite:' . $livePath);
            $stale->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            assertSame(false, $this->columnExists($stale, 'threads', 'subject_author_identity_id'));
            assertSame(false, $this->columnExists($stale, 'viewer_tags', 'eligible'));
            assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($stale)));
            $stale = null;

            $app = new Application(dirname(__DIR__), $fixture->repository, $livePath);
            ob_start();
            try {
                $app->handle('GET', '/threads/root-001');
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            assertStringContains('<h1>Renamed by reply</h1>', $html);

            $rebuilt = new PDO('sqlite:' . $livePath);
            $rebuilt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            assertSame(true, $this->columnExists($rebuilt, 'threads', 'subject_author_identity_id'));
            assertSame(true, $this->columnExists($rebuilt, 'viewer_tags', 'eligible'));
            assertSame($author, $rebuilt->query("SELECT subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
            assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity(ReadModelMetadata::readMetadata($rebuilt)));
            assertSame('schema_identity_mismatch', ReadModelMetadata::readMetadata($rebuilt)['rebuild_reason']);
        } finally {
            $fixture->remove();
        }
    }

    private function seedStaleVersion43Model(string $livePath): void
    {
        $pdo = new PDO('sqlite:' . $livePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('UPDATE threads SET subject = (SELECT subject FROM posts WHERE posts.post_id = threads.root_post_id)');
        $pdo->exec('ALTER TABLE threads DROP COLUMN subject_author_identity_id');
        $pdo->exec('ALTER TABLE viewer_tags DROP COLUMN eligible');
        $stmt = $pdo->prepare('UPDATE metadata SET value = :value WHERE key = :key');
        $stmt->execute(['value' => '43', 'key' => 'schema_version']);
        $stmt->execute(['value' => 'stale-fingerprint-pre-44', 'key' => 'schema_fingerprint']);
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }

        return false;
    }
}
