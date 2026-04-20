<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReadModelThreadLabelsTest
{
    public function testRebuildMaterializesThreadLabelsAndSkipsInvalidThreadTargets(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-labels-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153100-ab12cd35.txt',
            "Record-ID: thread-label-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-001\nOperation: add\nLabels: answered bug\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153200-ab12cd36.txt',
            "Record-ID: thread-label-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nThread-ID: reply-001\nOperation: add\nLabels: not-a-root\n\n"
        );

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_label_test',
        );
        $builder->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $threadLabels = $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();
        $invalidCount = $pdo->query("SELECT value FROM metadata WHERE key = 'thread_label_invalid_count'")->fetchColumn();

        assertSame('["answered","bug","needs-review"]', $threadLabels);
        assertSame('1', $invalidCount);
    }

    public function testRebuildDerivesScoreFromApprovedScoredTagsWithoutDoubleCounting(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-label-scores-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153100-ab12cd35.txt',
            "Record-ID: thread-label-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: root-001\nOperation: add\nLabels: like like\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153200-ab12cd36.txt',
            "Record-ID: thread-label-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nThread-ID: root-001\nOperation: add\nLabels: like flag\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/thread-label-20260415153300-ab12cd37.txt',
            "Record-ID: thread-label-20260415153300-ab12cd37\nCreated-At: 2026-04-15T15:33:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\n\n"
        );

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'thread_label_score_test',
        );
        $builder->rebuild();

        $pdo = new PDO('sqlite:' . $databasePath);
        $scoreTotal = $pdo->query("SELECT score_total FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();
        $threadLabels = $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();

        assertSame('-99', (string) $scoreTotal);
        assertSame('["bug","flag","like","needs-review"]', $threadLabels);
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-thread-label-fixture-' . bin2hex(random_bytes(6));
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
