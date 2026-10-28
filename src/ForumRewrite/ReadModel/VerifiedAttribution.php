<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelOwnership;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\Security\AttributionCutoff;
use ForumRewrite\Security\ReactionSignature;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;

/**
 * The read model keeps two sets of approved-member totals: claimed (every vote naming an
 * approved member, as before) and verified (unsigned votes count only before the cutoff).
 * The effective columns copy one set, chosen by the verified attribution flag, so turning
 * the flag on or off needs no rebuild. Posts likewise carry attribution_verified; with the
 * flag on, an unverified post loses its profile link and stops counting toward profile counts.
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

    /** Labels written in a post's body follow the post: they count when the post would. */
    public static function countsThreadLabel(string $repositoryRoot, ?string $cutoffAt, ThreadLabelRecord $record): bool
    {
        if ($record->authorIdentityId === null) {
            return true;
        }
        if ($cutoffAt !== null && $record->sourcePostId !== null
            && LabelOwnership::inspect($record, $repositoryRoot)['status'] === 'proven') {
            foreach (CanonicalPathResolver::postCandidates($record->sourcePostId) as $postPath) {
                if (is_file($repositoryRoot . '/' . $postPath)) {
                    return self::counts($repositoryRoot, $cutoffAt, $postPath, $record->authorIdentityId, $record->createdAt);
                }
            }
        }

        return self::counts($repositoryRoot, $cutoffAt, CanonicalPathResolver::threadLabel($record->recordId), $record->authorIdentityId, $record->createdAt);
    }

    /** Whether a post counts as its named author's; removed posts keep counting as before. */
    public static function countsPost(string $repositoryRoot, ?string $cutoffAt, string $sourcePath, ?string $authorIdentityId, string $createdAt): bool
    {
        return $authorIdentityId === null || !str_starts_with($sourcePath, 'records/posts/')
            || self::counts($repositoryRoot, $cutoffAt, $sourcePath, $authorIdentityId, $createdAt);
    }

    /**
     * Marks activity rows for posts, labels and reactions naming an identity. Post rows follow
     * the post; label and reaction rows follow their own record. Optionally limited to a thread.
     */
    public static function markActivity(PDO $pdo, string $repositoryRoot, ?string $cutoffAt, bool $enabled, ?string $threadId = null): void
    {
        $scope = $threadId === null ? '' : ' AND thread_id = :thread_id';
        $bind = $threadId === null ? [] : ['thread_id' => $threadId];
        $pdo->prepare(
            "UPDATE activity
             SET attribution_verified = COALESCE((SELECT posts.attribution_verified FROM posts WHERE posts.post_id = activity.post_id), 1)
             WHERE source_path LIKE 'records/posts/%'" . $scope
        )->execute($bind);

        $rows = $pdo->prepare(
            "SELECT id, record_family, source_path, author_identity_id FROM activity
             WHERE record_family IN ('thread_label', 'post_reaction') AND author_identity_id IS NOT NULL" . $scope
        );
        $rows->execute($bind);
        $mark = $pdo->prepare('UPDATE activity SET attribution_verified = ? WHERE id = ?');
        $repository = new CanonicalRecordRepository($repositoryRoot);
        foreach ($rows->fetchAll() as $row) {
            $path = (string) $row['source_path'];
            try {
                $verified = $row['record_family'] === 'thread_label'
                    ? self::countsThreadLabel($repositoryRoot, $cutoffAt, $repository->loadThreadLabel($path))
                    : self::counts($repositoryRoot, $cutoffAt, $path, (string) $row['author_identity_id'], $repository->loadPostReaction($path)->createdAt);
            } catch (CanonicalRecordParseException) {
                $verified = true;
            }
            $mark->execute([$verified ? 1 : 0, (int) $row['id']]);
        }
        $pdo->prepare(
            'UPDATE activity SET attribution_counted = ' . ($enabled ? 'attribution_verified' : '1') . ' WHERE 1 = 1' . $scope
        )->execute($bind);
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
        $pdo->exec(
            "UPDATE posts
             SET attribution_counted = " . ($enabled ? 'attribution_verified' : '1') . ",
                 author_profile_slug = CASE WHEN " . ($enabled ? 'attribution_verified = 0' : '0') . " THEN NULL
                     ELSE (SELECT profile_slug FROM profiles WHERE profiles.identity_id = posts.author_identity_id) END
             WHERE author_identity_id IS NOT NULL"
        );
        $pdo->exec("UPDATE profiles SET post_count = {$set}_post_count, thread_count = {$set}_thread_count");
        $pdo->exec('UPDATE activity SET attribution_counted = ' . ($enabled ? 'attribution_verified' : '1'));
        $pdo->prepare('INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)')->execute([self::METADATA_ENABLED, $enabled ? '1' : '0']);
    }

    /** Effective profile link for a post naming a profile. */
    public static function effectiveProfileSlug(?string $profileSlug, bool $verified, bool $enabled): ?string
    {
        return $enabled && !$verified ? null : $profileSlug;
    }
}
