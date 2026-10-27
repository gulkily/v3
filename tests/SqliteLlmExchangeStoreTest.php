<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\SqliteLlmExchangeStore;

final class SqliteLlmExchangeStoreTest
{
    public function testStoreListsFindsAndHydratesExchanges(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo);
        $firstId = $recorder->record(
            ['call_type' => 'post_analysis', 'post_id' => 'post-1'],
            ['messages' => [['role' => 'user', 'content' => 'Prompt 1']]],
            ['body' => 'Response 1'],
            'completed',
            1.5
        );
        $secondId = $recorder->record(
            ['call_type' => 'agent_reply_generation', 'post_id' => 'post-2'],
            ['messages' => [['role' => 'user', 'content' => 'Prompt 2']]],
            ['body' => 'Response 2'],
            'provider_error',
            2.5
        );

        $store = new SqliteLlmExchangeStore($pdo);
        $recent = $store->recent();
        $first = $store->find((int) $firstId);
        $postRows = $store->forPost('post-1');

        assertSame(2, count($recent));
        assertSame((int) $secondId, $recent[0]['id']);
        assertSame('Prompt 1', $first['request']['messages'][0]['content']);
        assertSame('Response 1', $first['response']['body']);
        assertSame('post-1', $postRows[0]['related_post_id']);
    }
    public function testGuardFiltersHiddenRowsBeforeLimitsAndVisibleOffsets(): void
    {
        $root = sys_get_temp_dir() . '/exchange-visible-' . bin2hex(random_bytes(6));
        mkdir($root . '/records', 0700, true);
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo);
        $safe = [];
        for ($i = 0; $i < 3; ++$i) $safe[] = $recorder->record(['post_id' => 'target', 'exchange_dependencies' =>
            \ForumRewrite\Llm\ExchangeDependencies::manifest(['target'])], ['payload' => ['safe' => $i]], [], 'completed', 1);
        for ($i = 0; $i < 205; ++$i) $recorder->record(['post_id' => 'target'], ['hidden' => 'prose'], [], 'completed', 1);
        for ($id = 4; $id <= 208; ++$id) \ForumRewrite\Retraction\ExchangeScrubber::scrub($pdo, $id, []);
        $pdo->exec("UPDATE llm_exchanges SET occurred_at = '2026-10-14T12:00:00Z'");
        $store = new SqliteLlmExchangeStore($pdo, new \ForumRewrite\Llm\ExchangeVisibility($root));
        assertSame([$safe[1], $safe[0]], array_column($store->recent(2, 1), 'id'));
        assertSame([$safe[2], $safe[1]], array_column($store->forPost('target', 2), 'id'));
        assertSame(null, $store->find(208));
        assertSame(208, (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
    }

    public function testExistingStoreAllowsDelayAndReflectsCompletedScrubbing(): void
    {
        $root = sys_get_temp_dir() . '/exchange-race-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nProse\n");
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo);
        $store = new SqliteLlmExchangeStore($pdo, new \ForumRewrite\Llm\ExchangeVisibility($root));
        $context = ['post_id' => 'target', 'exchange_dependencies' => \ForumRewrite\Llm\ExchangeDependencies::manifest(['target'])];
        $first = $recorder->record($context, ['body' => 'prose'], [], 'completed', 1);
        assertSame($first, $store->find($first)['id']);
        mkdir($root . '/records/post-retractions');
        $path = $root . '/records/post-retractions/post-retraction-target.txt';
        file_put_contents($path, "Record-ID: post-retraction-target\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: target\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        $late = $recorder->record($context, ['body' => 'prose'], ['body' => 'late prose'], 'completed', 1);
        $failed = $recorder->record($context, [], [], 'provider_error', 1, ['message' => 'error prose']);
        foreach ([$first, $late, $failed] as $id) assertSame($id, $store->find($id)['id']);
        foreach ([$first, $late, $failed] as $id) \ForumRewrite\Retraction\ExchangeScrubber::scrub($pdo, $id, ['target']);
        foreach ([$first, $late, $failed] as $id) assertSame(null, $store->find($id));
        assertSame([], $store->recent());
        assertSame([], $store->forPost('target'));
        file_put_contents($path, 'malformed');
        assertSame(null, $store->find($first));
        assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
    }

}
