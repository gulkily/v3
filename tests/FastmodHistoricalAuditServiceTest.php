<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastmodHistoricalAuditService;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastmodHistoricalAuditServiceTest
{
    public function testClassifiesCurrentContentWithoutWritingWork(): void
    {
        $readPdo = new PDO('sqlite::memory:');
        $readPdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, parent_id TEXT NULL, subject TEXT NULL, body TEXT NOT NULL)');
        foreach ([['scored', 'Scored'], ['excluded', ''], ['pending', 'Pending'], ['failed', 'Failed'], ['unrated', 'Unrated']] as [$postId, $body]) {
            $stmt = $readPdo->prepare('INSERT INTO posts (post_id, thread_id, parent_id, subject, body) VALUES (:post_id, :thread_id, NULL, :subject, :body)');
            $stmt->execute(['post_id' => $postId, 'thread_id' => $postId, 'subject' => '', 'body' => $body]);
        }
        $scorePdo = new PDO('sqlite::memory:');
        $store = new SqliteFastScoreStore($scorePdo);
        $contextFactory = new FastScoreContextFactory(static fn (string $_): ?array => null);
        $post = static fn (string $postId, string $body): array => ['post_id' => $postId, 'thread_id' => $postId, 'parent_id' => null, 'subject' => '', 'body' => $body];
        $store->save('scored', $contextFactory->contentHashForPost($post('scored', 'Scored')), 'rubric-a', ['status' => 'scored', 'probability' => 0.2, 'source' => 'llm', 'signals' => []]);
        $store->save('excluded', $contextFactory->contentHashForPost($post('excluded', '')), 'rubric-a', ['status' => 'excluded', 'probability' => null, 'source' => 'heuristic', 'signals' => []]);
        $pendingHash = $contextFactory->contentHashForPost($post('pending', 'Pending'));
        $failedHash = $contextFactory->contentHashForPost($post('failed', 'Failed'));
        $store->enqueueWork('pending', $pendingHash, 'rubric-a');
        $store->enqueueWork('failed', $failedHash, 'rubric-a');
        $store->completeWork(['post_id' => 'failed', 'content_hash' => $failedHash, 'rubric_revision' => 'rubric-a', 'attempt_count' => 3], ['status' => 'provider_error', 'probability' => null, 'source' => 'none', 'signals' => []]);
        $workCountBefore = (int) $scorePdo->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn();
        $scoreCountBefore = (int) $scorePdo->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn();

        $report = (new FastmodHistoricalAuditService($readPdo, $store, 'rubric-a'))->audit();

        assertSame(['total' => 5, 'scored' => 1, 'excluded' => 1, 'pending' => 1, 'failed' => 1, 'unrated' => 1], $report['counts']);
        assertSame(['unrated'], array_column($report['candidates'], 'post_id'));
        assertSame($workCountBefore, (int) $scorePdo->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn());
        assertSame($scoreCountBefore, (int) $scorePdo->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn());
    }
}
