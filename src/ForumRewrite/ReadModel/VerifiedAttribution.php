<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Security\AttributionCutoff;
use ForumRewrite\Security\ReactionSignature;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;

/**
 * The read model keeps two sets of approved-member totals: claimed (every vote naming an
 * approved member, as before) and verified (unsigned votes count only before the cutoff).
 * The effective columns copy one set, chosen by the verified attribution flag, so turning
 * the flag on or off needs no rebuild.
 */
final class VerifiedAttribution
{
    public const METADATA_ENABLED = 'verified_attribution_enabled';
    public const METADATA_CUTOFF = 'attribution_cutoff_at';

    public static function enabled(string $repositoryRoot): bool
    {
        return FeatureFlagEvaluator::forApplication($repositoryRoot, dirname(__DIR__, 3))
            ->isEnabled(FeatureFlagRegistry::VERIFIED_ATTRIBUTION);
    }

    /**
     * Whether a record naming an approved member counts toward verified totals: signed by
     * that member, or created (server Created-At, never Action-At) before the cutoff.
     */
    public static function counts(string $repositoryRoot, ?string $cutoffAt, string $relativePath, string $authorIdentityId, string $createdAt): bool
    {
        if ($cutoffAt === null) {
            return true;
        }
        $created = strtotime($createdAt);
        if ($created === false || $created < (int) strtotime($cutoffAt)) {
            return true;
        }
        $contents = @file_get_contents($repositoryRoot . '/' . $relativePath);

        return is_string($contents)
            && ReactionSignature::status($repositoryRoot, $relativePath, $contents, $authorIdentityId) === 'verified';
    }

    public static function storedCutoff(PDO $pdo): ?string
    {
        $stmt = $pdo->prepare('SELECT value FROM metadata WHERE key = ?');
        $stmt->execute([self::METADATA_CUTOFF]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function storedEnabled(PDO $pdo): ?bool
    {
        $stmt = $pdo->prepare('SELECT value FROM metadata WHERE key = ?');
        $stmt->execute([self::METADATA_ENABLED]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value === '1' : null;
    }

    public static function resolveCutoff(string $repositoryRoot): ?string
    {
        return AttributionCutoff::resolve($repositoryRoot)['cutoff_at'] ?? null;
    }

    /** Records the cutoff the totals were derived with and copies the totals chosen by the current flag into the effective columns. */
    public static function sync(PDO $pdo, string $repositoryRoot, ?string $cutoffAt): void
    {
        $pdo->prepare('INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)')->execute([self::METADATA_CUTOFF, $cutoffAt ?? '']);
        self::apply($pdo, self::enabled($repositoryRoot));
    }

    public static function apply(PDO $pdo, bool $enabled): void
    {
        $set = $enabled ? 'verified' : 'claimed';
        $pdo->exec("UPDATE threads SET score_total = {$set}_score_total");
        $pdo->exec(
            "UPDATE posts
             SET post_score_total = {$set}_post_score_total,
                 approved_flag_count = {$set}_approved_flag_count,
                 is_hidden = CASE WHEN {$set}_approved_flag_count > 0 AND author_label = 'reply-agent' THEN 1 ELSE 0 END,
                 hidden_reason = CASE WHEN {$set}_approved_flag_count > 0 AND author_label = 'reply-agent' THEN 'approved_flagged_reply_agent' ELSE NULL END"
        );
        $pdo->prepare('INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)')->execute([self::METADATA_ENABLED, $enabled ? '1' : '0']);
    }
}
