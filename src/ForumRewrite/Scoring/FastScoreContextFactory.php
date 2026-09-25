<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreContextFactory
{
    private const TARGET_TEXT_LIMIT = 6000;
    private const REPLY_CONTEXT_TEXT_LIMIT = 1000;

    /** @var \Closure(string): (array<string, mixed>|null) */
    private readonly \Closure $fetchPost;

    /** @param \Closure(string): (array<string, mixed>|null) $fetchPost */
    public function __construct(\Closure $fetchPost)
    {
        $this->fetchPost = $fetchPost;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function forPost(array $post): array
    {
        $postId = (string) ($post['post_id'] ?? '');
        $threadId = (string) ($post['thread_id'] ?? $postId);
        $isReply = $postId !== '' && $postId !== $threadId;
        $context = [
            'post_id' => $postId,
            'content_hash' => $this->contentHash($post),
            'post_kind' => $isReply ? 'reply' : 'thread',
            'post_text' => $this->limit($this->postText($post), self::TARGET_TEXT_LIMIT),
        ];

        if (!$isReply) {
            return $context;
        }

        $parentId = trim((string) ($post['parent_id'] ?? ''));
        $parent = $parentId === '' ? null : ($this->fetchPost)($parentId);
        $thread = $threadId === '' ? null : ($this->fetchPost)($threadId);
        $context['reply_context'] = array_filter([
            'parent_text' => $parent === null ? null : $this->limit($this->postText($parent), self::REPLY_CONTEXT_TEXT_LIMIT),
            'thread_root_text' => $thread === null || $threadId === $parentId
                ? null
                : $this->limit($this->postText($thread), self::REPLY_CONTEXT_TEXT_LIMIT),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $context;
    }

    /** @param array<string, mixed> $post */
    private function contentHash(array $post): string
    {
        return hash('sha256', json_encode([
            'post_id' => (string) ($post['post_id'] ?? ''),
            'subject' => (string) ($post['subject'] ?? ''),
            'body' => (string) ($post['body'] ?? ''),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $post */
    private function postText(array $post): string
    {
        $subject = trim((string) ($post['subject'] ?? ''));
        $body = trim((string) ($post['body'] ?? ''));

        return $subject === '' ? $body : trim($subject . "\n\n" . $body);
    }

    private function limit(string $text, int $limit): string
    {
        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit) . "\n[truncated]";
    }
}
