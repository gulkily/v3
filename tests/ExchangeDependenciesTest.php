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

    public function testUnavailableOrMissingTargetStateIsNeverEmptyState(): void
    {
        $root = sys_get_temp_dir() . '/exchange-state-' . bin2hex(random_bytes(6));
        assertSame(null, ExchangeRetractionState::read($root));
        mkdir($root . '/records/post-retractions', 0700, true);
        assertSame([], ExchangeRetractionState::read($root));
        $path = $root . '/records/post-retractions/post-retraction-target.txt';
        file_put_contents($path, 'malformed');
        assertSame(null, ExchangeRetractionState::read($root));
        file_put_contents($path, "Record-ID: post-retraction-target\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: target\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        assertSame(null, ExchangeRetractionState::read($root));
        mkdir($root . '/records/posts');
        file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nProse\n");
        assertSame(['target' => true], ExchangeRetractionState::read($root));
        file_put_contents($path . '.asc', 'forged');
        assertSame(null, ExchangeRetractionState::read($root));
    }
}
