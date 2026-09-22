<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Profile lookups by slug, identity ID, or username token - pure PDO
 * queries against the `profiles` table with no other dependencies, used
 * across nearly every route group (board, activity, profiles, users, lobby,
 * account, compose, ...). First piece of the shared query service called
 * for in docs/plans/codebase_cleanup_audit_findings_v1.md's Phase 2 slice 3
 * discussion: the remaining un-extracted route groups (/tags, /profiles,
 * /users) turned out to be entangled with content-visibility queries, not
 * just framework plumbing like /about and /instance/backup/downloads were -
 * pulling the query layer out first makes those slices cheap again.
 *
 * Deliberately does not include viewer-identity resolution
 * (Application::authenticatedViewerProfile()/resolveViewerProfileFromIdentityHint())
 * even though those also do profile lookups - those read $_SESSION and
 * feature-flag state, so they're request-scoped, not a pure data-access
 * concern, and stay on Application.
 */
final class ProfileRepository
{
    private const COLUMNS = 'identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count';

    /**
     * @return array<string, mixed>|null
     */
    public static function bySlug(PDO $pdo, string $slug): ?array
    {
        $stmt = $pdo->prepare('SELECT ' . self::COLUMNS . ' FROM profiles WHERE profile_slug = :profile_slug');
        $stmt->execute(['profile_slug' => $slug]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function byIdentityId(PDO $pdo, string $identityId): ?array
    {
        $stmt = $pdo->prepare('SELECT ' . self::COLUMNS . ' FROM profiles WHERE identity_id = :identity_id');
        $stmt->execute(['identity_id' => $identityId]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byUsernameToken(PDO $pdo, string $usernameToken): array
    {
        $stmt = $pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM profiles WHERE username_token = :username_token
             ORDER BY is_approved DESC, profile_slug ASC'
        );
        $stmt->execute(['username_token' => $usernameToken]);

        return $stmt->fetchAll();
    }
}
