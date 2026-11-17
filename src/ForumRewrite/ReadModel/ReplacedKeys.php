<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Security\KeyBootstrapReplay;
use PDO;

/**
 * Keys a session replaced: the lost key whose signed-in session vouched for a new key
 * (docs/specs/key_bootstrap_record_v1.md). The lost key stays approved, because a backup
 * may still use it elsewhere; it is "replaced" while the new key's session-vouched
 * approval stands and until someone retires the lost key.
 *
 * Read from existing columns (bootstrap posts and profile approvals), so no schema change.
 */
final class ReplacedKeys
{
    /**
     * Replaced keys, newest replacement first; one entry per lost key.
     *
     * @return list<array{identity_id:string,profile_slug:string,username:string,username_token:string,replacement_identity_id:string,replacement_profile_slug:string}>
     */
    public static function all(PDO $pdo, ?string $usernameToken = null): array
    {
        $posts = $pdo->query("SELECT author_identity_id, body FROM posts
            WHERE board_tags_json LIKE '%\"" . KeyBootstrapReplay::TAG . "\"%' ORDER BY sequence_number DESC")->fetchAll(PDO::FETCH_ASSOC);
        $entries = [];
        foreach ($posts as $post) {
            $lostId = KeyBootstrapReplay::fromIdentityId((string) $post['body']);
            if ($lostId === null || isset($entries[$lostId])) {
                continue;
            }
            $replacement = ProfileRepository::byIdentityId($pdo, (string) $post['author_identity_id']);
            $lost = ProfileRepository::byIdentityId($pdo, $lostId);
            // Only a standing, session-vouched approval replaces; a retired lost key has left the queue.
            if ($replacement === null || $lost === null || (int) $replacement['is_approved'] !== 1
                || (string) ($replacement['approved_by_identity_id'] ?? '') !== $lostId
                || !empty($replacement['retired_at']) || !empty($lost['retired_at'])) {
                continue;
            }
            if ($usernameToken !== null && (string) $lost['username_token'] !== $usernameToken) {
                continue;
            }
            $entries[$lostId] = [
                'identity_id' => $lostId,
                'profile_slug' => (string) $lost['profile_slug'],
                'username' => (string) $lost['username'],
                'username_token' => (string) $lost['username_token'],
                'replacement_identity_id' => (string) $replacement['identity_id'],
                'replacement_profile_slug' => (string) $replacement['profile_slug'],
            ];
        }
        return array_values($entries);
    }

    /** The entry for one key, or null when it is not replaced. @return array<string, string>|null */
    public static function forIdentity(PDO $pdo, string $identityId): ?array
    {
        foreach (self::all($pdo) as $entry) {
            if ($entry['identity_id'] === $identityId) {
                return $entry;
            }
        }
        return null;
    }
}
