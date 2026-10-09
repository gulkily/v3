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
