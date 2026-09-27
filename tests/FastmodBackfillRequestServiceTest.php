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

    public function testRetryCannotExceedTheReservedBackfillBudget(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteFastScoreStore($pdo);
        $batch = $store->createBackfillBatch('rubric-a', 1, 0.015, 0.01, [['post_id' => 'post-1', 'content_hash' => 'hash-1']]);
        $claimed = $store->claimPendingBackfillWork(1);
        assertSame(true, $store->reserveBackfillProviderAttempt($claimed[0]));
        $store->completeWork($claimed[0], ['status' => 'provider_error', 'probability' => null, 'source' => 'none', 'signals' => ['provider_error']]);
        $store->retryWork('post-1', 'hash-1', 'rubric-a');

        $retry = $store->claimPendingBackfillWork(1);
        assertSame(1, count($retry));
        assertSame(false, $store->reserveBackfillProviderAttempt($retry[0]));
        $store->releaseClaimedWork($retry[0]);
        assertSame('budget_exhausted', $pdo->query('SELECT status FROM fastmod_backfill_batches WHERE id = ' . $batch['id'])->fetchColumn());
        assertSame(0.01, (float) $pdo->query('SELECT reserved_cost_usd FROM fastmod_backfill_batches WHERE id = ' . $batch['id'])->fetchColumn());
    }

    public function testClaimingBackfillWorkDoesNotReserveCostBeforeAProviderAttempt(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteFastScoreStore($pdo);
        $store->createBackfillBatch('rubric-a', 1, 1.0, 0.01, [['post_id' => 'post-1', 'content_hash' => 'hash-1']]);

        $claimed = $store->claimPendingBackfillWork(1);

        assertSame(1, count($claimed));
        assertSame(0.0, (float) $pdo->query('SELECT reserved_cost_usd FROM fastmod_backfill_batches')->fetchColumn());
    }
}
