<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Marks which viewer_tags rows the score reducers actually count, so the signals line
 * can name exactly the reactions behind the number. 0 is not counted; CLAIMED is counted
 * in the claimed approved totals; VERIFIED is also counted in the verified totals.
 */
final class SignalEligibility
{
    public const CLAIMED = 1;
    public const VERIFIED = 2;

    /** @param array<string, array<string, array<string, int>>> $byTarget target → identity → tag → level */
    public static function apply(PDO $pdo, string $recordFamily, array $byTarget): void
    {
        $update = $pdo->prepare(
            'UPDATE viewer_tags SET eligible = :level
             WHERE record_family = :family AND target_id = :target AND author_identity_id = :identity AND tag = :tag'
        );
        foreach ($byTarget as $targetId => $byIdentity) {
            foreach ($byIdentity as $identityId => $byTag) {
                foreach ($byTag as $tag => $level) {
                    $update->execute(['level' => $level, 'family' => $recordFamily, 'target' => (string) $targetId, 'identity' => (string) $identityId, 'tag' => (string) $tag]);
                }
            }
        }
    }

    /** The minimum level the current verified-attribution setting counts. */
    public static function threshold(PDO $pdo): int
    {
        return (VerifiedAttribution::storedEnabled($pdo) ?? false) ? self::VERIFIED : self::CLAIMED;
    }
}
