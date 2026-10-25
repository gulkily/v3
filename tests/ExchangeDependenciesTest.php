<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\ExchangeDependencies;
use ForumRewrite\Llm\ExchangeRetractionState;

final class ExchangeDependenciesTest
{
    public function testCompleteManifestsRequireThePrimaryPostAndValidIds(): void
    {
        $row = ['related_post_id' => 'target', 'context' => ['exchange_dependencies' => ExchangeDependencies::manifest(['target', 'parent', 'other-thread'])]];
        assertSame(['other-thread', 'parent', 'target'], ExchangeDependencies::classify($row));
        foreach ([['version' => 2, 'complete' => true, 'post_ids' => ['target']], ExchangeDependencies::manifest(['parent']),
            ExchangeDependencies::manifest(['target'], false), ['version' => 1, 'complete' => true, 'post_ids' => ['target', '../bad']]] as $manifest) {
            $row['context']['exchange_dependencies'] = $manifest;
            assertSame(null, ExchangeDependencies::classify($row));
        }
        assertSame(null, ExchangeDependencies::classify(['related_post_id' => 'target', 'context' => 'malformed']));
    }

    public function testHistoricalRootScoresAreAllowlistedButUnknownContextIsNot(): void
    {
        $input = ['post_id' => 'root', 'content_hash' => 'hash', 'post_kind' => 'thread', 'post_text' => 'Root prose'];
        $system = trim(file_get_contents(__DIR__ . '/../prompts/fast_post_scoring_system.txt'));
        $row = ['call_type' => 'fast_post_score', 'related_post_id' => 'root', 'request' => ['payload' => ['messages' => [
            ['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($input)],
        ]]]];
        assertSame(['root'], ExchangeDependencies::classify($row));
        $row['request']['payload'] = ['system' => $system, 'messages' => [['role' => 'user', 'content' => json_encode($input)]]];
        assertSame(['root'], ExchangeDependencies::classify($row));
        $input['reply_context'] = ['parent_text' => 'unidentified parent'];
        $row['request']['payload']['messages'][0]['content'] = json_encode($input);
        assertSame(null, ExchangeDependencies::classify($row));
        foreach (['post_analysis', 'agent_response_task', 'unknown'] as $type) {
            $row['call_type'] = $type;
            assertSame(null, ExchangeDependencies::classify($row));
        }
        $row['call_type'] = 'fast_post_score';
        $row['request']['payload']['messages'] = 'malformed';
        assertSame(null, ExchangeDependencies::classify($row));
    }

    public function testAnalysisPropagatesSummaryOriginsAndWithholdsUnknownSummaries(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach (\ForumRewrite\ReadModel\ReadModelSchema::statements() as $statement) $pdo->exec($statement);
        $posts = [
            'root' => ['post_id' => 'root', 'thread_id' => 'root', 'parent_id' => null, 'body' => 'Root words'],
            'reply' => ['post_id' => 'reply', 'thread_id' => 'root', 'parent_id' => 'root', 'body' => 'Reply words'],
        ];
        $service = new \ForumRewrite\Agent\PostWorkflowService(static fn () => $pdo, dirname(__DIR__), '', '', null, null,
            new \ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator(), static fn () => throw new RuntimeException('unused writer'),
            static fn ($id) => $posts[$id] ?? null, static fn () => array_values($posts), static fn () => null);
        $initial = $service->postAnalysisContext($posts['reply']);
        assertSame(true, $initial['exchange_dependencies']['complete']);
        assertSame(['reply', 'root'], $initial['exchange_dependencies']['post_ids']);
        $root = $service->postAnalysisContext($posts['root'], false);
        $store = new \ForumRewrite\Analysis\SqlitePostAnalysisStore($pdo);
        $store->saveComplete('root', $root['content_hash'], ['post_summary' => 'Summary from other input', 'raw_response' => []]);
        assertSame(false, $service->postAnalysisContext($posts['reply'])['exchange_dependencies']['complete']);
        $store->saveComplete('root', $root['content_hash'], ['post_summary' => 'Summary from other input', 'raw_response' => [
            '_forum_exchange_dependencies' => ExchangeDependencies::manifest(['root', 'cross-thread-source']),
        ]]);
        $known = $service->postAnalysisContext($posts['reply']);
        assertSame(true, $known['exchange_dependencies']['complete']);
        assertSame(['cross-thread-source', 'reply', 'root'], $known['exchange_dependencies']['post_ids']);
        assertSame(false, array_key_exists('_exchange_dependencies', $known['thread_comments'][0]));
    }

    public function testCleanupUsesAcceptedTargetsWithoutLegacyPublicationGates(): void
    {
        $root = sys_get_temp_dir() . '/exchange-state-' . bin2hex(random_bytes(6));
        try { ExchangeRetractionState::read($root); throw new LogicException('Missing repository accepted'); }
        catch (RuntimeException $error) { assertStringContains('unavailable', $error->getMessage()); }
        mkdir($root . '/records/post-retractions', 0700, true);
        $path = $root . '/records/post-retractions/post-retraction-target.txt';
        file_put_contents($path, 'malformed legacy evidence');
        assertSame([], ExchangeRetractionState::read($root));
        $author = 'openpgp:' . str_repeat('a', 40);
        $source = "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: $author\n\nProse\n";
        $bytes = "Record-ID: post-retraction-target\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: target\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        $record = \ForumRewrite\Canonical\AcceptedRetraction::prepare((new \ForumRewrite\Canonical\PostRecordParser())->parse($source), (new \ForumRewrite\Canonical\PostRetractionRecordParser())->parse($bytes), $bytes, '', 'author');
        file_put_contents($root . '/' . \ForumRewrite\Canonical\AcceptedRetraction::path('target'), \ForumRewrite\Canonical\AcceptedRetraction::encode($record));
        assertSame(['target' => true], ExchangeRetractionState::read($root));
        // No original post or Git history is necessary after acceptance.
    }
}
