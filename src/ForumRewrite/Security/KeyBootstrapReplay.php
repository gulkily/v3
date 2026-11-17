<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

/**
 * Approvals that a signed-in session vouched for (see docs/specs/key_bootstrap_record_v1.md).
 *
 * A bootstrap record is a reply on the new key's bootstrap thread, signed by the new
 * key, naming the key whose session vouched for it. On its own it grants nothing: a
 * `session` acceptance receipt, written by the server when it checked the session,
 * names that key as the authorizer. Full and incremental rebuilds share this replay,
 * and the builders still require the authorizer to be approved at that point of the
 * replay. Retracting the record removes the grant on the next rebuild.
 */
final class KeyBootstrapReplay
{
    public const TAG = 'key-bootstrap';
    public const FROM_HEADER = 'Key-Bootstrap-From-Identity-ID';

    /** True for the posts this replay reads; the builders give them a source path. */
    public static function isBootstrapRecord(string $boardTagsJson): bool
    {
        $tags = json_decode($boardTagsJson, true);
        return is_array($tags) && in_array('identity', $tags, true) && in_array(self::TAG, $tags, true);
    }

    /** The key a bootstrap record says vouched for it, or null when the body names none. */
    public static function fromIdentityId(string $body): ?string
    {
        return preg_match('/^' . self::FROM_HEADER . ': (openpgp:[a-f0-9]{40})$/m', $body, $match) === 1 ? $match[1] : null;
    }

    /**
     * @param iterable<array<string, mixed>> $posts
     * @return list<array{target_identity_id:string,approver_identity_id:string,thread_id:string,parent_id:?string,post_id:string,source_path:?string}>
     */
    public static function candidates(string $root, iterable $posts): array
    {
        $candidates = [];
        foreach ($posts as $post) {
            if (!self::isBootstrapRecord((string) ($post['board_tags_json'] ?? '')) || !isset($post['source_path'])) {
                continue;
            }
            // Invalid evidence stops the rebuild, as it does for other receipts; a missing receipt grants nothing.
            $receipt = ApprovalAcceptance::read($root, (string) $post['source_path']);
            if ($receipt === null || $receipt['kind'] !== 'session') {
                continue;
            }
            $candidates[] = [
                'target_identity_id' => (string) $post['author_identity_id'],
                'approver_identity_id' => (string) $receipt['context']['actor_identity_id'],
                'thread_id' => (string) $post['thread_id'],
                'parent_id' => $post['parent_id'] !== null ? (string) $post['parent_id'] : null,
                'post_id' => (string) $post['post_id'],
                'source_path' => (string) $post['source_path'],
            ];
        }
        return $candidates;
    }
}
