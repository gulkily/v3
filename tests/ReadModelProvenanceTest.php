<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReadModelProvenanceTest
{
    public function testLabelProvenanceMatchesPerPathHistoryAcrossMergeAndMissingPaths(): void
    {
        $fixture = new RebuildHistoryFixture();
        try {
            $fixture->git('checkout', '-qb', 'side');
            $fixture->write('thread-labels', 50);
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'side label');
            $fixture->git('checkout', '-q', 'main');
            $fixture->write('thread-labels', 51);
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'main label');
            $fixture->git('merge', '--no-ff', '-qm', 'merge labels', 'side');
            $fixture->write('thread-labels', 99); // Untracked provenance stays null.
            $this->assertProvenance($fixture, ['thread-labels']);
        } finally {
            $fixture->remove();
        }
    }

    public function testMergeResolutionKeepsItsOwnSourceCommit(): void
    {
        $fixture = new RebuildHistoryFixture(1);
        try {
            $path = $fixture->repository . '/records/thread-labels/thread-label-fixture-0.txt';
            $base = file_get_contents($path);
            $fixture->git('checkout', '-qb', 'side');
            file_put_contents($path, str_replace('Labels: fixture-0', 'Labels: side', $base));
            $fixture->git('commit', '-qam', 'side edit');
            $fixture->git('checkout', '-q', 'main');
            file_put_contents($path, str_replace('Labels: fixture-0', 'Labels: main', $base));
            $fixture->git('commit', '-qam', 'main edit');
            try {
                $fixture->git('merge', '--no-commit', 'side');
            } catch (RuntimeException) {
                // Resolve both parents to a third label value.
            }
            file_put_contents($path, str_replace('Labels: fixture-0', 'Labels: resolved', $base));
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'resolution');
            $this->assertProvenance($fixture, ['thread-labels']);
        } finally {
            $fixture->remove();
        }
    }

    public function testLabelHistoryProcessCountDoesNotGrowWithRecords(): void
    {
        foreach ([1, 110] as $count) {
            $fixture = new RebuildHistoryFixture($count);
            $trace = $fixture->root . '/git.trace';
            $oldTrace = getenv('GIT_TRACE');
            try {
                putenv('GIT_TRACE=' . $trace);
                $this->build($fixture);
                putenv($oldTrace === false ? 'GIT_TRACE' : 'GIT_TRACE=' . $oldTrace);
                $lines = file($trace) ?: [];
                $calls = array_filter($lines, static fn (string $line): bool => str_contains($line, 'built-in: git log') && str_contains($line, 'records/thread-labels'));
                assertSame(1, count($calls));
            } finally {
                putenv($oldTrace === false ? 'GIT_TRACE' : 'GIT_TRACE=' . $oldTrace);
                $fixture->remove();
            }
        }
    }

    public function testBrokenGitHistoryRetainsNullLabelProvenance(): void
    {
        $fixture = new RebuildHistoryFixture(1);
        try {
            file_put_contents($fixture->repository . '/.git/HEAD', 'invalid head');
            $this->assertProvenance($fixture, ['thread-labels'], true);
        } finally {
            $fixture->remove();
        }
    }

    private function build(RebuildHistoryFixture $fixture): PDO
    {
        $path = $fixture->root . '/model.sqlite';
        (new ReadModelBuilder($fixture->repository, $path, new CanonicalRecordRepository($fixture->repository)))->rebuild();
        return new PDO('sqlite:' . $path);
    }

    private function assertProvenance(RebuildHistoryFixture $fixture, array $families, bool $broken = false): void
    {
        $pdo = $this->build($fixture);
        $checked = 0;
        foreach ($pdo->query('SELECT source_path, source_commit_sha FROM activity')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach ($families as $family) {
                if (!str_starts_with((string) $row['source_path'], 'records/' . $family . '/')) {
                    continue;
                }
                $expected = $broken ? '' : $fixture->git('log', '-1', '--format=%H', '--', $row['source_path']);
                assertSame($expected === '' ? null : $expected, $row['source_commit_sha']);
                $checked++;
            }
        }
        assertSame(true, $checked > 0);
    }
}
