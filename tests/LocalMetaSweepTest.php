<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/LocalMetaTrainingTest.php';

use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\LocalMetaFiles;
use ForumRewrite\Scoring\LocalMetaModel;
use ForumRewrite\Scoring\LocalMetaScores;
use ForumRewrite\Scoring\LocalMetaSweep;
use ForumRewrite\Scoring\LocalMetaTraining;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

final class LocalMetaSweepTest
{
    private static function model(): LocalMetaModel
    {
        [$read, $scores] = LocalMetaTrainingTest::fixture();
        return (new LocalMetaTraining($read, $scores))->run(false, '2026-11-14T00:00:00Z')['model'];
    }

    public function testScoresEveryVisibleRootOnceAndRescoresEditedText(): void
    {
        [$read, $scorePdo] = LocalMetaTrainingTest::fixture();
        $model = self::model();
        $store = new LocalMetaScores($scorePdo);
        $llmRows = (int) $scorePdo->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn();
        $roots = (int) $read->query('SELECT COUNT(*) FROM posts WHERE post_id = thread_id')->fetchColumn();

        $first = (new LocalMetaSweep($read, $store, $model))->run();
        assertSame($roots, $first['scored']);
        assertFalse($first['remaining']);
        $again = (new LocalMetaSweep($read, $store, $model))->run();
        assertSame(0, $again['scored']);
        assertSame($roots, $again['skipped']);

        $read->exec("UPDATE posts SET body = 'Edited: the reply button is broken' WHERE post_id = 'untagged'");
        assertSame(1, (new LocalMetaSweep($read, $store, $model))->run()['scored']);
        assertSame($llmRows, (int) $scorePdo->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn());
        assertSame($roots + 1, $store->countForBuild($model->buildId()));

        $hash = (new FastScoreContextFactory(static fn (string $_): ?array => null))->contentHashForPost(['post_id' => 'russian', 'subject' => 'Баг: кнопка не работает', 'body' => 'Пожалуйста, исправьте.']);
        $russian = $store->latestForPostContent('russian', $hash);
        assertSame(0.0, $russian['probability']);
        assertSame([LocalMetaModel::CYRILLIC_SIGNAL], $russian['signals']);
    }

    public function testLimitLeavesWorkForTheNextRun(): void
    {
        [$read, $scorePdo] = LocalMetaTrainingTest::fixture();
        $sweep = new LocalMetaSweep($read, new LocalMetaScores($scorePdo), self::model());
        $first = $sweep->run(10);
        assertSame(10, $first['scored']);
        assertTrue($first['remaining']);
        do { $result = $sweep->run(10); } while ($result['remaining']);
        assertSame(0, $sweep->run(10)['scored']);
    }

    public function testWorkerRequeuesUntilDoneAndReportsMissingModel(): void
    {
        $queue = new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        [$read, $scorePdo] = LocalMetaTrainingTest::fixture();
        $sweep = new LocalMetaSweep($read, new LocalMetaScores($scorePdo), self::model());
        $first = $queue->enqueue(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP, LocalMetaSweep::DEDUPLICATION_KEY);
        $repeat = $queue->enqueue(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP, LocalMetaSweep::DEDUPLICATION_KEY);
        assertSame($first['id'], $repeat['id']);
        $worker = new TaskQueueWorker($queue, static function (): void {}, runLocalScoreSweep: static fn (): array => $sweep->run(30));
        $continued = 0;
        for ($run = 0; $run < 5 && $queue->findById((int) $first['id'])['status'] === 'queued'; $run++) {
            $continued += $worker->run(1)['continued'];
        }
        assertTrue($continued >= 1);
        assertSame('completed', $queue->findById((int) $first['id'])['status']);

        $missing = $queue->enqueue(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP, LocalMetaSweep::DEDUPLICATION_KEY);
        (new TaskQueueWorker($queue, static function (): void {}, runLocalScoreSweep: static fn (): ?array => null))->run(1);
        $task = $queue->findById((int) $missing['id']);
        assertSame('failed', $task['status']);
        assertStringContains('Retrain', (string) $task['failure_message']);
    }

    public function testForProjectNeedsATrainedModel(): void
    {
        $dir = sys_get_temp_dir() . '/local-meta-sweep-' . bin2hex(random_bytes(5));
        mkdir($dir);
        try {
            [$read] = LocalMetaTrainingTest::fixture();
            assertSame(null, LocalMetaSweep::forProject($dir, $read, $dir . '/scores.sqlite3'));
            (new LocalMetaFiles($dir))->saveModel(self::model());
            assertTrue(LocalMetaSweep::forProject($dir, $read, $dir . '/scores.sqlite3') instanceof LocalMetaSweep);
            assertSame(null, LocalMetaScores::readOnly($dir . '/missing.sqlite3'));
        } finally {
            foreach (array_merge(glob($dir . '/state/private/*') ?: [], glob($dir . '/*.sqlite3') ?: []) as $file) unlink($file);
            @rmdir($dir . '/state/private'); @rmdir($dir . '/state'); rmdir($dir);
        }
    }

    public function testTwoThousandRootsScoreQuickly(): void
    {
        $roots = [];
        for ($i = 0; $i < 2000; $i++) $roots[] = ["p$i", "Post $i about the theme button", str_repeat("Some words about life and the site, number $i. ", 20), []];
        $read = LocalMetaTrainingTest::readModel($roots);
        $started = microtime(true);
        $result = (new LocalMetaSweep($read, new LocalMetaScores(new PDO('sqlite::memory:')), self::model()))->run();
        assertSame(2000, $result['scored']);
        assertTrue(microtime(true) - $started < 10.0);
    }
}
