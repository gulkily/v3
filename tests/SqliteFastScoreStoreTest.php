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

    public function testStoreUsesOnlySafeFailureMessages(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $saved = $store->save('post-1', 'content-a', 'rubric-a', [
            'status' => 'provider_error',
            'source' => 'none',
            'signals' => ['provider_error'],
            'failure_code' => 'provider_authentication_failed',
            'failure_message' => 'Authorization: Bearer secret-value',
        ]);

        assertSame('provider_authentication_failed', $saved['failure_code']);
        assertSame('Provider authentication failed.', $saved['failure_message']);
        assertSame('provider_authentication_failed', $store->lastFailure()['failure_code']);
    }

    public function testOperatorRetryResetsOnlyTheNamedWorkItem(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $store->enqueueWork('post-1', 'content-a', 'rubric-a');
        $work = $store->claimPendingWork(1)[0];
        $store->completeWork($work, ['status' => 'config_missing', 'source' => 'none', 'signals' => []]);

        $retried = $store->retryWork('post-1', 'content-a', 'rubric-a');
        $invalidated = $store->invalidateWork('post-1', 'content-a', 'rubric-a');

        assertSame('pending', $retried['state']);
        assertSame(0, $retried['attempt_count']);
        assertSame('invalidated', $invalidated['state']);
    }

    public function testRecentWorkIncludesThePrivateScoreProbability(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $store->enqueueWork('post-1', 'content-a', 'rubric-a');
        $store->save('post-1', 'content-a', 'rubric-a', ['status' => 'scored', 'probability' => 0.0, 'source' => 'llm', 'signals' => []]);

        $recent = $store->recentWork();

        assertSame(0.0, $recent[0]['probability']);
        assertSame('llm', $recent[0]['source']);
    }

    public function testLatestScoredResultMatchesTheCurrentPostContent(): void
    {
        $store = new SqliteFastScoreStore(new PDO('sqlite::memory:'));
        $store->save('post-1', 'old-content', 'rubric-a', ['status' => 'scored', 'probability' => 0.1, 'source' => 'llm', 'signals' => []]);
        $store->save('post-1', 'current-content', 'rubric-a', ['status' => 'scored', 'probability' => 0.6, 'source' => 'llm', 'signals' => []]);
        $store->save('post-1', 'current-content', 'rubric-b', ['status' => 'scored', 'probability' => 0.8, 'source' => 'llm', 'signals' => []]);

        $current = $store->latestScoredForPostContent('post-1', 'current-content');

        assertSame(0.8, $current['probability']);
        assertSame(null, $store->latestScoredForPostContent('post-1', 'missing-content'));
    }
}
