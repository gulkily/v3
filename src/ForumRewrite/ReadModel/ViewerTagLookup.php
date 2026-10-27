<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Bulk lookups of which posts/threads a given identity has tagged
 * (liked/flagged posts, liked/labeled/voted threads).
 *
 * Backed by the `viewer_tags` read-model table (schema version 27): a single
 * indexed query per (record family, identity) loads that identity's full
 * tag->targets map, which is then filtered in memory for each requested tag
 * and target set. The map is memoized per (connection, family, identity) for
 * the life of the PDO handle, so repeated lookups within one request — other
 * tags, other call sites, the qdb per-tag loop — reuse it instead of
 * re-querying. Each request runs in its own PHP process with its own PDO, so
 * the static memo is naturally request-scoped (mirroring ReactionSignature /
 * RetractionAuthority in Security/).
 *
 * Results are identical to the former file-scan implementation: the projection
 * mirrors the same record set (no inactive/publication exclusion) and stores
 * the author identity verbatim, so lookups match under either openpgp-/openpgp:
 * notation exactly as the glob did.
 */
final class ViewerTagLookup
{
    /**
     * Memo: [connectionId][record_family][author_identity_id] =>
     *   [tag => [target_id => true]]
     *
     * @var array<int, array<string, array<string, array<string, array<string, true>>>>>
     */
    private static array $memo = [];

    /**
     * Stable per-connection ids. A WeakMap entry disappears when the PDO is
     * freed, so ids are never reused across distinct live connections — unlike
     * spl_object_id(), which can recycle an id after an object is destroyed.
     *
     * @var \WeakMap<PDO, int>|null
     */
    private static ?\WeakMap $connectionIds = null;

    private static int $nextConnectionId = 1;

    /**
     * @param array<int, mixed> $postIds
     * @return array<string, true>
     */
    public static function postTags(PDO $pdo, array $postIds, string $tag, string $identityId): array
    {
        return self::tagsForTargets($pdo, 'post-reactions', $postIds, $tag, $identityId);
    }

    /**
     * @param array<int, mixed> $threadIds
     * @return array<string, true>
     */
    public static function threadTags(PDO $pdo, array $threadIds, string $tag, string $identityId): array
    {
        return self::tagsForTargets($pdo, 'thread-labels', $threadIds, $tag, $identityId);
    }

    /**
     * Clear the per-connection memo. Not required under the per-request-process
     * model, but available for long-lived processes and tests.
     */
    public static function resetMemo(): void
    {
        self::$memo = [];
        self::$connectionIds = null;
        self::$nextConnectionId = 1;
    }

    /**
     * @param array<int, mixed> $targetIds
     * @return array<string, true>
     */
    private static function tagsForTargets(PDO $pdo, string $recordFamily, array $targetIds, string $tag, string $identityId): array
    {
        if ($targetIds === []) {
            return [];
        }

        $tagMap = self::identityTagMap($pdo, $recordFamily, $identityId);
        $taggedTargets = $tagMap[$tag] ?? [];
        if ($taggedTargets === []) {
            return [];
        }

        $result = [];
        foreach ($targetIds as $value) {
            $targetId = (string) $value;
            if (isset($taggedTargets[$targetId])) {
                $result[$targetId] = true;
            }
        }

        return $result;
    }

    /**
     * Load (once per connection+family+identity) the identity's full
     * tag -> {target_id: true} map from the indexed viewer_tags table.
     *
     * @return array<string, array<string, true>>
     */
    private static function identityTagMap(PDO $pdo, string $recordFamily, string $identityId): array
    {
        $connectionId = self::connectionId($pdo);
        if (isset(self::$memo[$connectionId][$recordFamily][$identityId])) {
            return self::$memo[$connectionId][$recordFamily][$identityId];
        }

        $statement = $pdo->prepare(
            'SELECT tag, target_id FROM viewer_tags
             WHERE author_identity_id = :identity_id AND record_family = :record_family'
        );
        $statement->execute([
            'identity_id' => $identityId,
            'record_family' => $recordFamily,
        ]);

        $tagMap = [];
        foreach ($statement->fetchAll(PDO::FETCH_NUM) as [$tag, $targetId]) {
            $tagMap[(string) $tag][(string) $targetId] = true;
        }

        self::$memo[$connectionId][$recordFamily][$identityId] = $tagMap;

        return $tagMap;
    }

    private static function connectionId(PDO $pdo): int
    {
        self::$connectionIds ??= new \WeakMap();
        if (!isset(self::$connectionIds[$pdo])) {
            self::$connectionIds[$pdo] = self::$nextConnectionId++;
        }

        return self::$connectionIds[$pdo];
    }
}
