<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastScoreResult;

final class FastScoreContextFactoryTest
{
    public function testRootContextContainsOnlyBoundedTargetText(): void
    {
        $factory = new FastScoreContextFactory(static fn (string $postId): ?array => null);
        $context = $factory->forPost([
            'post_id' => 'thread-1',
            'thread_id' => 'thread-1',
            'subject' => 'Subject',
            'body' => str_repeat('a', 6010),
        ]);

        assertSame('thread', $context['post_kind']);
        assertStringContains('Subject', $context['post_text']);
        assertStringContains('[truncated]', $context['post_text']);
        assertSame(false, array_key_exists('reply_context', $context));
    }

    public function testReplyContextContainsOnlyBoundedParentAndRootText(): void
    {
        $posts = [
            'thread-1' => ['post_id' => 'thread-1', 'subject' => 'Root', 'body' => str_repeat('r', 1200)],
            'parent-1' => ['post_id' => 'parent-1', 'body' => str_repeat('p', 1200)],
        ];
        $factory = new FastScoreContextFactory(static fn (string $postId): ?array => $posts[$postId] ?? null);
        $context = $factory->forPost([
            'post_id' => 'reply-1',
            'thread_id' => 'thread-1',
            'parent_id' => 'parent-1',
            'body' => 'Reply text',
        ]);

        assertSame('reply', $context['post_kind']);
        assertSame('Reply text', $context['post_text']);
        assertStringContains('[truncated]', $context['reply_context']['parent_text']);
        assertStringContains('Root', $context['reply_context']['thread_root_text']);
        assertSame(false, array_key_exists('thread_comments', $context));
    }

    public function testScoreResultAlwaysIncludesSourceAndNullableProbability(): void
    {
        assertSame([
            'status' => 'scored',
            'probability' => 0.5,
            'source' => 'llm',
            'signals' => [],
        ], FastScoreResult::scored(0.5, 'llm'));
        assertSame([
            'status' => 'disabled',
            'probability' => null,
            'source' => 'none',
            'signals' => ['fast_scoring_disabled'],
        ], FastScoreResult::notScored('disabled', ['fast_scoring_disabled']));
        assertSame('heuristic', FastScoreResult::excluded(['empty_post'])['source']);
    }
}
