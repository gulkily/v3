<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use InvalidArgumentException;

final class AgentReplyTask
{
    public static function deduplicationKey(string $postId, string $contentHash): string
    {
        $postId = trim($postId);
        $contentHash = trim($contentHash);
        if ($postId === '' || $contentHash === '') {
            throw new InvalidArgumentException('Agent-reply task post ID and content hash are required.');
        }

        return rawurlencode($postId) . '@' . rawurlencode($contentHash);
    }

    /**
     * @return array{post_id:string,content_hash:string}
     */
    public static function targetFromDeduplicationKey(string $key): array
    {
        $parts = explode('@', $key, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid agent-reply task key.');
        }

        $postId = rawurldecode($parts[0]);
        $contentHash = rawurldecode($parts[1]);
        if ($key !== self::deduplicationKey($postId, $contentHash)) {
            throw new InvalidArgumentException('Invalid agent-reply task key.');
        }

        return ['post_id' => $postId, 'content_hash' => $contentHash];
    }
}
