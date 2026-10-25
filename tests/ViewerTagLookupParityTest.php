<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ViewerTagLookup;

/**
 * Stage 4: prove the SQL-backed ViewerTagLookup returns byte-identical results
 * to the former file-scan implementation across post/thread families, several
 * tags, and the no-reaction / anonymous cases — and that it issues at most one
 * query per (record family, identity) per connection (the memo collapses the
 * qdb per-tag loop and repeated call sites).
 *
 * The former glob+parse algorithm is reproduced here verbatim as an oracle so
 * the comparison does not depend on code that this cycle removed.
 */
final class ViewerTagLookupParityTest
{
    private const VIEWER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const OTHER = 'openpgp:1111111111111111111111111111111111111111';

    public function testSqlLookupMatchesFileScanOracleAndMemoizesQueries(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-viewer-parity-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        @mkdir($repositoryRoot . '/records/post-reactions');
        @mkdir($repositoryRoot . '/records/thread-labels');
        // Viewer: like+flag reply-001, flag root-001.
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153100-aa000001.txt',
            "Record-ID: post-reaction-20260415153100-aa000001\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: reply-001\nOperation: add\nTags: like flag\nAuthor-Identity-ID: " . self::VIEWER . "\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153200-aa000002.txt',
            "Record-ID: post-reaction-20260415153200-aa000002\nCreated-At: 2026-04-15T15:32:00Z\nPost-ID: root-001\nOperation: add\nTags: flag\nAuthor-Identity-ID: " . self::VIEWER . "\n\n"
        );
        // Another identity likes reply-001 (must never leak into viewer results).
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153300-aa000003.txt',
            "Record-ID: post-reaction-20260415153300-aa000003\nCreated-At: 2026-04-15T15:33:00Z\nPost-ID: reply-001\nOperation: add\nTags: like\nAuthor-Identity-ID: " . self::OTHER . "\n\n"
        );
        // Viewer: upvote + downvote root-001.
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153400-bb000001.txt',
            "Record-ID: thread-label-20260415153400-bb000001\nCreated-At: 2026-04-15T15:34:00Z\nThread-ID: root-001\nOperation: add\nLabels: upvote downvote\nAuthor-Identity-ID: " . self::VIEWER . "\n\n"
        );

        $this->rebuild($repositoryRoot, $databasePath);

        $postIds = ['reply-001', 'root-001'];
        $threadIds = ['root-001'];

        foreach ([self::VIEWER, self::OTHER, 'openpgp:deadbeefdeadbeefdeadbeefdeadbeefdeadbeef'] as $identity) {
            foreach (['like', 'flag'] as $tag) {
                $pdo = new PDO('sqlite:' . $databasePath);
                $sql = ViewerTagLookup::postTags($pdo, $postIds, $tag, $identity);
                $oracle = $this->oraclePostTags($repositoryRoot, $postIds, $tag, $identity);
                assertSame($oracle, $sql, "postTags mismatch for {$identity}/{$tag}");
            }
            foreach (['upvote', 'downvote', 'like'] as $tag) {
                $pdo = new PDO('sqlite:' . $databasePath);
                $sql = ViewerTagLookup::threadTags($pdo, $threadIds, $tag, $identity);
                $oracle = $this->oracleThreadTags($repositoryRoot, $threadIds, $tag, $identity);
                assertSame($oracle, $sql, "threadTags mismatch for {$identity}/{$tag}");
            }
        }

        // Empty target set short-circuits to [] for both families.
        $pdo = new PDO('sqlite:' . $databasePath);
        assertSame([], ViewerTagLookup::postTags($pdo, [], 'like', self::VIEWER));
        assertSame([], ViewerTagLookup::threadTags($pdo, [], 'upvote', self::VIEWER));

        // Memo: repeated lookups for the same (family, identity) across many tags
        // issue exactly one viewer_tags query per family (mirrors the qdb per-tag
        // loop + multiple call sites within one request/connection).
        $counting = new CountingPdo('sqlite:' . $databasePath);
        ViewerTagLookup::threadTags($counting, $threadIds, 'upvote', self::VIEWER);
        ViewerTagLookup::threadTags($counting, $threadIds, 'downvote', self::VIEWER);
        ViewerTagLookup::threadTags($counting, $threadIds, 'like', self::VIEWER);
        ViewerTagLookup::postTags($counting, $postIds, 'flag', self::VIEWER);
        ViewerTagLookup::postTags($counting, $postIds, 'like', self::VIEWER);
        // One query for thread-labels + one for post-reactions = 2 total.
        assertSame(2, $counting->viewerTagsQueryCount());
    }

    /**
     * Oracle: the exact pre-change glob+parse logic for postTags.
     *
     * @param array<int, mixed> $postIds
     * @return array<string, true>
     */
    private function oraclePostTags(string $repositoryRoot, array $postIds, string $tag, string $identityId): array
    {
        $postLookup = array_fill_keys(array_map(static fn (mixed $v): string => (string) $v, $postIds), true);
        if ($postLookup === []) {
            return [];
        }
        $repository = new CanonicalRecordRepository($repositoryRoot);
        $tagged = [];
        foreach (glob($repositoryRoot . '/records/post-reactions/*.txt') ?: [] as $path) {
            try { $record = $repository->loadPostReaction('records/post-reactions/' . basename($path)); } catch (CanonicalRecordParseException) { continue; }
            if (!isset($postLookup[$record->postId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }
            if (in_array($tag, $record->tags, true)) {
                $tagged[$record->postId] = true;
            }
        }

        return $tagged;
    }

    /**
     * Oracle: the exact pre-change glob+parse logic for threadTags.
     *
     * @param array<int, mixed> $threadIds
     * @return array<string, true>
     */
    private function oracleThreadTags(string $repositoryRoot, array $threadIds, string $tag, string $identityId): array
    {
        $threadLookup = array_fill_keys(array_map(static fn (mixed $v): string => (string) $v, $threadIds), true);
        if ($threadLookup === []) {
            return [];
        }
        $repository = new CanonicalRecordRepository($repositoryRoot);
        $tagged = [];
        foreach (glob($repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            try { $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path)); } catch (CanonicalRecordParseException) { continue; }
            if (!isset($threadLookup[$record->threadId]) || $record->authorIdentityId !== $identityId) {
                continue;
            }
            if (in_array($tag, $record->labels, true)) {
                $tagged[$record->threadId] = true;
            }
        }

        return $tagged;
    }

    private function rebuild(string $repositoryRoot, string $databasePath): void
    {
        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'viewer_tag_parity_test',
        );
        $builder->rebuild();
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-viewer-parity-fixture-' . bin2hex(random_bytes(6));
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

/**
 * A PDO that counts prepared statements touching the viewer_tags table, so the
 * memo's "one query per (family, identity)" behavior can be asserted.
 */
final class CountingPdo extends PDO
{
    private int $viewerTagsQueries = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (stripos($query, 'viewer_tags') !== false) {
            $this->viewerTagsQueries++;
        }

        return parent::prepare($query, $options);
    }

    public function viewerTagsQueryCount(): int
    {
        return $this->viewerTagsQueries;
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
