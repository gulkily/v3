<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ImportedQuoteSeedScoringTest
{
    private const APPROVED_IDENTITY = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testRebuildSeedsScoreAndVoteCountFromImportedPostWithNoReactions(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $this->writeImportedPost($repositoryRoot, 'root-import-001', 842, 1200);

        $pdo = $this->rebuild($repositoryRoot, 'imported_seed_no_reactions');
        $this->assertThreadScore($pdo, 'root-import-001', 842, 1200);
    }

    public function testRebuildAddsOrdinaryVoteOnTopOfImportedSeed(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $this->writeImportedPost($repositoryRoot, 'root-import-002', 842, 1200);
        $this->writeUpvoteLabel($repositoryRoot, 'root-import-002', 'thread-label-20260415154000-aa11bb22');

        $pdo = $this->rebuild($repositoryRoot, 'imported_seed_with_vote');
        $this->assertThreadScore($pdo, 'root-import-002', 843, 1201);
    }

    public function testRepeatedRebuildDoesNotDoubleCountTheImportedSeed(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $this->writeImportedPost($repositoryRoot, 'root-import-003', 842, 1200);
        $this->writeUpvoteLabel($repositoryRoot, 'root-import-003', 'thread-label-20260415154100-aa11bb23');

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-imported-seed-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);
        $this->rebuildInto($repositoryRoot, $databasePath, 'imported_seed_rebuild_once');
        $pdo = $this->rebuildInto($repositoryRoot, $databasePath, 'imported_seed_rebuild_twice');

        $this->assertThreadScore($pdo, 'root-import-003', 843, 1201);
    }

    public function testPostWithoutAnImportedSeedIsUnaffected(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();

        $pdo = $this->rebuild($repositoryRoot, 'no_seed_regression');
        $this->assertThreadScore($pdo, 'root-001', 0, 0);
    }

    private function writeImportedPost(string $repositoryRoot, string $postId, int $scoreSeed, int $voteCountSeed): void
    {
        file_put_contents(
            $repositoryRoot . '/records/posts/' . $postId . '.txt',
            "Post-ID: {$postId}\n"
            . "Created-At: 2003-06-13T10:47:35Z\n"
            . "Board-Tags: general\n"
            . "Imported-Score-Seed: {$scoreSeed}\n"
            . "Imported-Vote-Count-Seed: {$voteCountSeed}\n"
            . "\nImported historical quote body.\n"
        );
    }

    private function writeUpvoteLabel(string $repositoryRoot, string $threadId, string $recordId): void
    {
        file_put_contents(
            $repositoryRoot . '/records/thread-labels/' . $recordId . '.txt',
            "Record-ID: {$recordId}\n"
            . "Created-At: 2026-04-15T15:40:00Z\n"
            . "Thread-ID: {$threadId}\n"
            . "Operation: add\n"
            . "Labels: upvote\n"
            . "Author-Identity-ID: " . self::APPROVED_IDENTITY . "\n\n"
        );
    }

    private function rebuild(string $repositoryRoot, string $label): PDO
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-imported-seed-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        return $this->rebuildInto($repositoryRoot, $databasePath, $label);
    }

    private function rebuildInto(string $repositoryRoot, string $databasePath, string $label): PDO
    {
        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            $label,
        );
        $builder->rebuild();

        return new PDO('sqlite:' . $databasePath);
    }

    private function assertThreadScore(PDO $pdo, string $threadId, int $expectedScore, int $expectedVoteCount): void
    {
        $stmt = $pdo->prepare('SELECT score_total, vote_count FROM threads WHERE root_post_id = :root_post_id');
        $stmt->execute(['root_post_id' => $threadId]);
        $row = $stmt->fetch();

        assertSame((string) $expectedScore, (string) $row['score_total']);
        assertSame((string) $expectedVoteCount, (string) $row['vote_count']);
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-imported-seed-fixture-' . bin2hex(random_bytes(6));
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
