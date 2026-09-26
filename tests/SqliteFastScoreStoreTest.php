<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Scoring\SqliteFastScoreStore;
final class SqliteFastScoreStoreTest
{
    public function testStoresAndSeparatesScoresByContentAndRubricRevision(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $saved = $store->save('post-1', 'content-a', 'rubric-a', ['status' => 'scored', 'probability' => 0.7, 'source' => 'llm', 'signals' => []]);
        assertSame(0.7, $saved['probability']);
        assertSame('llm', $saved['source']);
        assertSame(null, $store->find('post-1', 'content-b', 'rubric-a'));
        assertSame(null, $store->find('post-1', 'content-a', 'rubric-b'));
    }

    public function testUpdatesFailureAndExcludedOutcomes(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $store->save('post-1', 'content-a', 'rubric-a', ['status' => 'provider_error', 'source' => 'none', 'signals' => ['provider_error'], 'failure_message' => 'Unavailable']);
        $saved = $store->save('post-1', 'content-a', 'rubric-a', ['status' => 'excluded', 'source' => 'heuristic', 'signals' => ['empty_post']]);
        assertSame('excluded', $saved['status']);
        assertSame(null, $saved['probability']);
        assertSame(['empty_post'], $saved['signals']);
    }

    public function testPrivateWorkIsClaimedWithoutCreatingHistoricalWork(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $store->save('historical', 'old-content', 'rubric-a', ['status' => 'scored', 'probability' => 0.2, 'source' => 'llm', 'signals' => []]);
        $store->enqueueWork('new-post', 'new-content', 'rubric-a');

        $claimed = $store->claimPendingWork(10);

        assertSame(1, count($claimed));
        assertSame('new-post', $claimed[0]['post_id']);
        assertSame(1, $claimed[0]['attempt_count']);
    }
}
