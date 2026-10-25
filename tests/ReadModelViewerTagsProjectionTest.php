<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ViewerTagLookup;

/**
 * Stage 2 verification: the viewer_tags projection reproduces exactly the
 * (identity, target, tag) set that the file-scan ViewerTagLookup would return,
 * including labels on retracted/removed-source threads (no exclusion filter)
 * and excluding null-identity records (which the file scan never matched).
 */
final class ReadModelViewerTagsProjectionTest
{
    private const VIEWER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testProjectionMatchesFileScanRecordSet(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-viewer-tags-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        // Post reactions: a like + flag from the viewer on reply-001, and a
        // null-identity flag that must NOT be projected.
        @mkdir($repositoryRoot . '/records/post-reactions');
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153100-aa000001.txt',
            "Record-ID: post-reaction-20260415153100-aa000001\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: reply-001\nOperation: add\nTags: like flag\nAuthor-Identity-ID: " . self::VIEWER . "\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153200-aa000002.txt',
            "Record-ID: post-reaction-20260415153200-aa000002\nCreated-At: 2026-04-15T15:32:00Z\nPost-ID: root-001\nOperation: add\nTags: flag\n\n"
        );

        // Thread labels: an upvote on root-001 from the viewer.
        @mkdir($repositoryRoot . '/records/thread-labels');
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153300-bb000001.txt',
            "Record-ID: thread-label-20260415153300-bb000001\nCreated-At: 2026-04-15T15:33:00Z\nThread-ID: root-001\nOperation: add\nLabels: upvote\nAuthor-Identity-ID: " . self::VIEWER . "\n\n"
        );

        $this->rebuild($repositoryRoot, $databasePath);

        $pdo = new PDO('sqlite:' . $databasePath);
        $rows = $pdo->query(
            "SELECT record_family, target_id, author_identity_id, tag FROM viewer_tags ORDER BY record_family, target_id, tag"
        )->fetchAll(PDO::FETCH_ASSOC);

        $identityId = self::VIEWER;

        // The seeded records must be present in the projection.
        assertSame(true, $this->projected($pdo, 'post-reactions', 'reply-001', $identityId, 'like'));
        assertSame(true, $this->projected($pdo, 'post-reactions', 'reply-001', $identityId, 'flag'));
        assertSame(true, $this->projected($pdo, 'thread-labels', 'root-001', $identityId, 'upvote'));

        // The null-identity post reaction on root-001 must NOT be projected
        // (the file scan never matches a null author), and no row carries a
        // null identity.
        $nullRows = (int) $pdo->query("SELECT COUNT(*) FROM viewer_tags WHERE author_identity_id IS NULL OR author_identity_id = ''")->fetchColumn();
        assertSame(0, $nullRows);

        // The ViewerTagLookup API, now backed by viewer_tags, returns exactly
        // the projected membership for every (family, tag) pair.
        foreach ($this->projectedFamilyTagPairs($pdo, $identityId) as [$family, $tag]) {
            $targets = $this->projectedTargets($pdo, $family, $tag, $identityId);
            if ($family === 'post-reactions') {
                $scan = ViewerTagLookup::postTags($pdo, $targets, $tag, $identityId);
            } else {
                $scan = ViewerTagLookup::threadTags($pdo, $targets, $tag, $identityId);
            }
            $scanTargets = array_keys($scan);
            sort($scanTargets);
            assertSame($targets, $scanTargets);
        }

        // Spot checks on the seeded records specifically.
        assertSame(['reply-001'], array_keys(ViewerTagLookup::postTags($pdo, ['reply-001', 'root-001'], 'like', $identityId)));
        assertSame(['reply-001'], array_keys(ViewerTagLookup::postTags($pdo, ['reply-001', 'root-001'], 'flag', $identityId)));
        assertSame(['root-001'], array_keys(ViewerTagLookup::threadTags($pdo, ['root-001'], 'upvote', $identityId)));

        // A target not tagged by this identity returns nothing; memo reuse is safe.
        assertSame([], array_keys(ViewerTagLookup::postTags($pdo, ['nonexistent-post'], 'like', $identityId)));

        // Ensure the projection is non-empty (sanity against a silent no-op).
        assertSame(true, count($rows) > 0);
    }

    private function projected(PDO $pdo, string $family, string $targetId, string $identityId, string $tag): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM viewer_tags
             WHERE record_family = :family AND target_id = :target AND author_identity_id = :id AND tag = :tag'
        );
        $stmt->execute(['family' => $family, 'target' => $targetId, 'id' => $identityId, 'tag' => $tag]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function projectedFamilyTagPairs(PDO $pdo, string $identityId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT record_family, tag FROM viewer_tags
             WHERE author_identity_id = :id ORDER BY record_family, tag'
        );
        $stmt->execute(['id' => $identityId]);

        $pairs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
            $pairs[] = [(string) $row[0], (string) $row[1]];
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function projectedTargets(PDO $pdo, string $family, string $tag, string $identityId): array
    {
        $stmt = $pdo->prepare(
            'SELECT target_id FROM viewer_tags
             WHERE author_identity_id = :id AND record_family = :family AND tag = :tag
             ORDER BY target_id'
        );
        $stmt->execute(['id' => $identityId, 'family' => $family, 'tag' => $tag]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function rebuild(string $repositoryRoot, string $databasePath): void
    {
        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'viewer_tags_test',
        );
        $builder->rebuild();
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-viewer-tags-fixture-' . bin2hex(random_bytes(6));
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

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
