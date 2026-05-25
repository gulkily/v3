<?php

declare(strict_types=1);

use ForumRewrite\Analysis\SqliteUnicodeRiskStore;

final class UnicodeRiskStoreTest
{
    public function testStoreSavesAndHydratesDeterministicFacts(): void
    {
        $store = new SqliteUnicodeRiskStore(new PDO('sqlite::memory:'));
        $facts = [
            'schema_version' => 1,
            'fields' => [
                'subject' => [
                    'risk_labels' => ['mixed_script'],
                ],
            ],
        ];

        $stored = $store->saveDeterministic('post-1', 'hash-1', 1, $facts);
        $hydrated = $store->find('post-1', 'hash-1');

        assertSame('deterministic_complete', $stored['status']);
        assertSame(['mixed_script'], $hydrated['deterministic_facts']['fields']['subject']['risk_labels']);
        assertSame([], $hydrated['llm_review']);
    }

    public function testStoreUpdatesSamePostAndContentHashIdempotently(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteUnicodeRiskStore($pdo);

        $store->saveDeterministic('post-1', 'hash-1', 1, ['risk' => 'first']);
        $store->saveDeterministic('post-1', 'hash-1', 1, ['risk' => 'second']);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM post_unicode_risks')->fetchColumn();
        $hydrated = $store->find('post-1', 'hash-1');

        assertSame(1, $count);
        assertSame('second', $hydrated['deterministic_facts']['risk']);
    }

    public function testLlmFailurePreservesDeterministicFacts(): void
    {
        $store = new SqliteUnicodeRiskStore(new PDO('sqlite::memory:'));
        $store->saveDeterministic('post-1', 'hash-1', 1, ['risk_labels' => ['directionality_risk']]);

        $failed = $store->saveLlmFailure('post-1', 'hash-1', 'provider unavailable');

        assertSame('llm_failed', $failed['status']);
        assertSame(['directionality_risk'], $failed['deterministic_facts']['risk_labels']);
        assertSame('provider unavailable', $failed['failure_message']);
    }

    public function testLlmReviewIsStoredSeparatelyFromFacts(): void
    {
        $store = new SqliteUnicodeRiskStore(new PDO('sqlite::memory:'));
        $store->saveDeterministic('post-1', 'hash-1', 1, ['risk_labels' => ['mixed_script']]);

        $reviewed = $store->saveLlmReview('post-1', 'hash-1', ['review_priority' => 'low']);

        assertSame('complete', $reviewed['status']);
        assertSame(['mixed_script'], $reviewed['deterministic_facts']['risk_labels']);
        assertSame('low', $reviewed['llm_review']['review_priority']);
    }
}
