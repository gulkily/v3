<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReadModelThreadSubjectsTest
{
    public function testRebuildSetsBlankSubjectFromEarliestThreadSubjectRecordAndSkipsInvalidThreadTargets(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        $this->writeNoSubjectRootPost($repositoryRoot, 'root-no-subject');

        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415153200-ab12cd36.txt',
            "Record-ID: thread-subject-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nThread-ID: root-no-subject\nOperation: set\nSubject: Second Fetched Title\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415153100-ab12cd35.txt',
            "Record-ID: thread-subject-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-no-subject\nOperation: set\nSubject: First Fetched Title\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415153300-ab12cd37.txt',
            "Record-ID: thread-subject-20260415153300-ab12cd37\nCreated-At: 2026-04-15T15:33:00Z\nThread-ID: not-a-real-thread\nOperation: set\nSubject: Orphan Title\n\n"
        );

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_subject_test',
        );
        $builder->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $threadSubject = $pdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-no-subject'")->fetchColumn();
        $postSubject = $pdo->query("SELECT subject FROM posts WHERE post_id = 'root-no-subject'")->fetchColumn();
        $invalidCount = $pdo->query("SELECT value FROM metadata WHERE key = 'thread_subject_invalid_count'")->fetchColumn();

        assertSame('First Fetched Title', $threadSubject);
        assertSame('First Fetched Title', $postSubject);
        assertSame('1', $invalidCount);
    }

    public function testRebuildNeverOverwritesExistingHumanSubject(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-existing-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415153100-ab12cd35.txt',
            "Record-ID: thread-subject-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-001\nOperation: set\nSubject: Should Never Appear\n\n"
        );

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_subject_overwrite_test',
        );
        $builder->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $threadSubject = $pdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();

        assertSame('Hello world', $threadSubject);
    }

    public function testIncrementalThreadSubjectWriteMatchesFreshRebuild(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $this->writeNoSubjectRootPost($repositoryRoot, 'root-no-subject');

        $incrementalDatabasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-incremental-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($incrementalDatabasePath);
        (new ReadModelBuilder(
            $repositoryRoot,
            $incrementalDatabasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_subject_incremental_baseline',
        ))->rebuild();

        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415153100-ab12cd35.txt',
            "Record-ID: thread-subject-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-no-subject\nOperation: set\nSubject: Fetched Title\n\n"
        );

        (new IncrementalReadModelUpdater($incrementalDatabasePath, $repositoryRoot))
            ->applyThreadSubjectWrite('root-no-subject', 'deadbeef');

        $rebuiltDatabasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-rebuilt-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($rebuiltDatabasePath);
        (new ReadModelBuilder(
            $repositoryRoot,
            $rebuiltDatabasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_subject_incremental_comparison',
        ))->rebuild();

        $incrementalPdo = new PDO('sqlite:' . $incrementalDatabasePath);
        $rebuiltPdo = new PDO('sqlite:' . $rebuiltDatabasePath);

        $incrementalThreadSubject = $incrementalPdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-no-subject'")->fetchColumn();
        $rebuiltThreadSubject = $rebuiltPdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-no-subject'")->fetchColumn();
        $incrementalPostSubject = $incrementalPdo->query("SELECT subject FROM posts WHERE post_id = 'root-no-subject'")->fetchColumn();
        $rebuiltPostSubject = $rebuiltPdo->query("SELECT subject FROM posts WHERE post_id = 'root-no-subject'")->fetchColumn();

        assertSame('Fetched Title', $rebuiltThreadSubject);
        assertSame($rebuiltThreadSubject, $incrementalThreadSubject);
        assertSame($rebuiltPostSubject, $incrementalPostSubject);
    }

    public function testLatestActiveAuthoredRecordOverridesRootSubjectAndStoresAuthor(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-authored-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $this->writeAuthoredReply($repositoryRoot, 'reply-title-1', 'root-001', $author, "title: First rename\n");
        $this->writeAuthoredReply($repositoryRoot, 'reply-title-2', 'root-001', $author, "title: Second rename\n");
        $this->writeAuthoredReply($repositoryRoot, 'reply-title-3', 'root-001', $author, "title: Tampered later\n");
        $this->writeAuthoredSubjectRecord($repositoryRoot, 'thread-subject-20260415160000-aa000001', '2026-04-15T16:00:00Z', 'root-001', 'First rename', $author, 'reply-title-1');
        $this->writeAuthoredSubjectRecord($repositoryRoot, 'thread-subject-20260415160100-aa000002', '2026-04-15T16:01:00Z', 'root-001', 'Second rename', $author, 'reply-title-2');
        // Later record whose source reply changed after the record was written: inactive.
        $this->writeAuthoredSubjectRecord($repositoryRoot, 'thread-subject-20260415160200-aa000003', '2026-04-15T16:02:00Z', 'root-001', 'Tampered later', $author, 'reply-title-3', str_repeat('00', 32));
        // System record beside an existing root subject: still never overrides.
        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/thread-subject-20260415160300-aa000004.txt',
            "Record-ID: thread-subject-20260415160300-aa000004\nCreated-At: 2026-04-15T16:03:00Z\nThread-ID: root-001\nOperation: set\nSubject: Fetched much later\n\n"
        );

        (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'thread_subject_authored_test'))->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $row = $pdo->query("SELECT subject, subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetch(PDO::FETCH_ASSOC);
        assertSame('Second rename', $row['subject']);
        assertSame($author, $row['subject_author_identity_id']);
        assertSame('Second rename', $pdo->query("SELECT subject FROM posts WHERE post_id = 'root-001'")->fetchColumn());
    }

    public function testAuthoredRecordWithMissingSourceIsIgnoredAndRootSubjectStands(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-missing-source-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $this->writeAuthoredSubjectRecord($repositoryRoot, 'thread-subject-20260415160000-aa000001', '2026-04-15T16:00:00Z', 'root-001', 'Orphaned rename', $author, 'reply-gone', str_repeat('ab', 32));

        (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot), 'thread_subject_missing_source_test'))->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $row = $pdo->query("SELECT subject, subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetch(PDO::FETCH_ASSOC);
        assertSame('Hello world', $row['subject']);
        assertSame(null, $row['subject_author_identity_id']);
    }

    public function testIncrementalAuthoredWriteAndWithdrawalMatchFreshRebuild(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $author = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $incrementalDatabasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-authored-incremental-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($incrementalDatabasePath);
        $this->writeAuthoredReply($repositoryRoot, 'reply-title-1', 'root-001', $author, "title: Renamed once\n");
        (new ReadModelBuilder($repositoryRoot, $incrementalDatabasePath, new CanonicalRecordRepository($repositoryRoot), 'authored_incremental_baseline'))->rebuild();

        $this->writeAuthoredSubjectRecord($repositoryRoot, 'thread-subject-20260415160000-aa000001', '2026-04-15T16:00:00Z', 'root-001', 'Renamed once', $author, 'reply-title-1');
        $updater = new IncrementalReadModelUpdater($incrementalDatabasePath, $repositoryRoot);
        $updater->applyThreadSubjectWrite('root-001', 'deadbeef');
        $incrementalPdo = new PDO('sqlite:' . $incrementalDatabasePath);
        assertSame('Renamed once', $incrementalPdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
        assertSame($author, $incrementalPdo->query("SELECT subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());

        // Withdrawal: the source reply disappears, so the retitle no longer applies and the root subject returns.
        unlink($repositoryRoot . '/records/posts/reply-title-1.txt');
        $updater->applyThreadSubjectWrite('root-001', 'deadbeef');
        $rebuiltDatabasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subjects-authored-rebuilt-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($rebuiltDatabasePath);
        (new ReadModelBuilder($repositoryRoot, $rebuiltDatabasePath, new CanonicalRecordRepository($repositoryRoot), 'authored_incremental_comparison'))->rebuild();
        $rebuiltPdo = new PDO('sqlite:' . $rebuiltDatabasePath);
        foreach (['subject', 'subject_author_identity_id'] as $column) {
            $rebuilt = $rebuiltPdo->query("SELECT {$column} FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();
            assertSame($rebuilt, $incrementalPdo->query("SELECT {$column} FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
        }
        assertSame('Hello world', $incrementalPdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
        assertSame('Hello world', $incrementalPdo->query("SELECT subject FROM posts WHERE post_id = 'root-001'")->fetchColumn());
    }

    private function writeAuthoredReply(string $repositoryRoot, string $postId, string $threadId, string $author, string $body): void
    {
        file_put_contents(
            $repositoryRoot . '/records/posts/' . $postId . '.txt',
            "Post-ID: {$postId}\nCreated-At: 2026-04-15T15:59:00Z\nBoard-Tags: general\nThread-ID: {$threadId}\nParent-ID: {$threadId}\nAuthor-Identity-ID: {$author}\n\n" . $body
        );
    }

    private function writeAuthoredSubjectRecord(string $repositoryRoot, string $recordId, string $createdAt, string $threadId, string $subject, string $author, string $sourcePostId, ?string $sourceSha = null): void
    {
        $sourceSha ??= hash('sha256', (string) file_get_contents($repositoryRoot . '/records/posts/' . $sourcePostId . '.txt'));
        file_put_contents(
            $repositoryRoot . '/records/thread-subjects/' . $recordId . '.txt',
            "Record-ID: {$recordId}\nCreated-At: {$createdAt}\nThread-ID: {$threadId}\nOperation: set\nSubject: {$subject}\nAuthor-Identity-ID: {$author}\nSource-Post-ID: {$sourcePostId}\nSource-Post-SHA256: {$sourceSha}\nReason: Title directive in reply\n\n"
        );
    }

    private function writeNoSubjectRootPost(string $repositoryRoot, string $postId): void
    {
        file_put_contents(
            $repositoryRoot . '/records/posts/' . $postId . '.txt',
            "Post-ID: {$postId}\nCreated-At: 2026-04-11T12:00:00Z\nBoard-Tags: general\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n"
        );
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-thread-subject-fixture-' . bin2hex(random_bytes(6));
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
