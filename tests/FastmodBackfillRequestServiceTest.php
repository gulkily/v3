<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastmodBackfillRequestService;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastmodBackfillRequestServiceTest
{
    public function testCreatesOnlyTheBoundedCandidateSnapshotAndIsolatesItFromNormalWork(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteFastScoreStore($pdo);
        $request = new FastmodBackfillRequestService($store);
        $audit = ['counts' => ['unrated' => 3], 'candidates' => [
            ['post_id' => 'post-1', 'content_hash' => 'hash-1'],
            ['post_id' => 'post-2', 'content_hash' => 'hash-2'],
            ['post_id' => 'post-3', 'content_hash' => 'hash-3'],
        ]];

        $batch = $request->create($audit, 'rubric-a', 2, 0.05, 0.02);

        assertSame(2, $batch['requested_count']);
        assertSame(2, $batch['queued_count']);
        assertSame(2, (int) $pdo->query('SELECT requested_count FROM fastmod_backfill_batches WHERE id = ' . $batch['id'])->fetchColumn());
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM fastmod_backfill_work WHERE batch_id = ' . $batch['id'])->fetchColumn());
        assertSame([], $store->claimPendingWork(10));
        assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM fast_score_work WHERE state = 'pending'")->fetchColumn());
    }

    public function testRejectsABudgetThatCannotCoverOneEstimatedScore(): void
    {
        $request = new FastmodBackfillRequestService(new SqliteFastScoreStore(new PDO('sqlite::memory:')));

        try {
            $request->create(['counts' => ['unrated' => 1], 'candidates' => [['post_id' => 'post-1', 'content_hash' => 'hash-1']]], 'rubric-a', 1, 0.01, 0.02);
            throw new RuntimeException('Expected backfill budget validation to fail.');
        } catch (InvalidArgumentException $error) {
            assertSame('The maximum cost does not cover one estimated Fastmod score.', $error->getMessage());
        }
    }
}
