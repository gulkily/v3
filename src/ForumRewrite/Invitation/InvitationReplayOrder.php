<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

/** Preserve protocol dependencies when records share a second-resolution timestamp. */
final class InvitationReplayOrder
{
    public static function key(string $createdAt, string $body, string $postId): array
    {
        preg_match('/^Invitation-Action: (issue|revoke|redeem)$/m', $body, $match);
        $rank = match ($match[1] ?? '') {
            'issue' => 0,
            'revoke' => 1,
            'redeem' => 2,
            default => 0,
        };
        return [$createdAt, $rank, $postId];
    }

    public static function sortPosts(array $posts): array
    {
        usort($posts, static fn (array $a, array $b): int =>
            self::key($a['created_at'], $a['body'], $a['post_id'])
            <=> self::key($b['created_at'], $b['body'], $b['post_id']));
        return $posts;
    }
}
