<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

/** The key a direct approval post names; shared by the read model and root suggestions. */
final class ApprovalTarget
{
    /** @param array{board_tags_json:string,body:string} $post */
    public static function fromPost(array $post): ?string
    {
        $boardTags = json_decode($post['board_tags_json'], true);
        if (!is_array($boardTags) || !in_array('identity', $boardTags, true) || !in_array('approval', $boardTags, true)) {
            return null;
        }

        if (preg_match('/^Approve-Identity-ID: (openpgp:[a-f0-9]{40})$/m', $post['body'], $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
