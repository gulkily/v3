<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\TaskQueue\ReadModelRebuildTaskHandler;

/** Queued rebuilds build beside the live file and never drop a write that lands mid-build. */
final class ReadModelRebuildTaskHandlerTest
{
    public function testWriteDuringBuildIsPresentAfterTheSwap(): void
    {
        $this->withFixture(function (RebuildHistoryFixture $fixture, string $db): void {
            $messages = $this->runHandler($fixture, $db, commitsDuringBuilds: 1);

            assertSame(1, $this->count($messages, 'building again'));
            assertSame(0, $this->count($messages, 'building under the write lock'));
            $this->assertCurrent($fixture, $db);
        });
    }

    public function testBuildsUnderTheLockAfterRepeatedMisses(): void
    {
        $this->withFixture(function (RebuildHistoryFixture $fixture, string $db): void {
            $messages = $this->runHandler($fixture, $db, commitsDuringBuilds: ReadModelRebuildTaskHandler::UNLOCKED_ATTEMPTS);

            assertSame(ReadModelRebuildTaskHandler::UNLOCKED_ATTEMPTS, $this->count($messages, 'building again'));
            assertSame(1, $this->count($messages, 'building under the write lock'));
            $this->assertCurrent($fixture, $db);
        });
    }

    public function testReadersKeepTheOldFileWhileTheBuildRuns(): void
    {
        $this->withFixture(function (RebuildHistoryFixture $fixture, string $db): void {
            $reader = new PDO('sqlite:' . $db);
            $headBefore = $reader->query("SELECT value FROM metadata WHERE key = 'repository_head'")->fetchColumn();
            $sawOldDuringBuild = false;
            $handler = new ReadModelRebuildTaskHandler($fixture->repository, $db, null,
                function (int $taskId, string $message) use ($db, &$sawOldDuringBuild, $headBefore): void {
                    if (str_starts_with($message, 'Read model: validating candidate...')) {
                        $live = (new ReadModelConnection($db, 0))->open();
                        $sawOldDuringBuild = $live->query("SELECT value FROM metadata WHERE key = 'repository_head'")->fetchColumn() === $headBefore;
                    }
                });
            $fixture->write('thread-labels', 200);
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'before queued rebuild');

            $handler(1);

            assertTrue($sawOldDuringBuild, 'a reader during the build reads the old file without waiting');
            $this->assertCurrent($fixture, $db);
        });
    }

    /** @return list<string> */
    private function runHandler(RebuildHistoryFixture $fixture, string $db, int $commitsDuringBuilds): array
    {
        $messages = [];
        $commits = 0;
        $handler = new ReadModelRebuildTaskHandler($fixture->repository, $db, null,
            function (int $taskId, string $message) use ($fixture, &$messages, &$commits, $commitsDuringBuilds): void {
                $messages[] = $message;
                // Validation runs after the build reads every record: commit just before it.
                if (str_starts_with($message, 'Read model: validating candidate...') && $commits < $commitsDuringBuilds) {
                    $fixture->write('thread-labels', 100 + $commits);
                    $fixture->git('add', '.');
                    $fixture->git('commit', '-qm', 'landed mid-build ' . $commits);
                    $commits++;
                }
            });
        $handler(1);
        assertSame($commitsDuringBuilds, $commits);

        return $messages;
    }

    private function assertCurrent(RebuildHistoryFixture $fixture, string $db): void
    {
        $pdo = (new ReadModelConnection($db))->open();
        assertSame($fixture->git('rev-parse', 'HEAD'), $pdo->query("SELECT value FROM metadata WHERE key = 'repository_head'")->fetchColumn());
        assertSame([], glob(dirname($db) . '/.' . basename($db) . '.candidate-*') ?: []);
    }

    /** @param list<string> $messages */
    private function count(array $messages, string $needle): int
    {
        return count(array_filter($messages, static fn (string $m): bool => str_contains($m, $needle)));
    }

    private function withFixture(callable $test): void
    {
        $fixture = new RebuildHistoryFixture(labels: 2);
        $db = $fixture->root . '/state/read.sqlite3';
        mkdir(dirname($db), 0777, true);
        try {
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $test($fixture, $db);
        } finally {
            $fixture->remove();
        }
    }
}
