<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\PostRecord;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;

/**
 * Who may retitle a thread through a `title:` reply line: the thread root's verified
 * author or a current operator, and any approved member when the second flag is on.
 * Decided at write time against the read model; a `false` means the reply still
 * posts as text and nothing else happens.
 */
final class TitleDirectiveAuthority
{
    public static function allows(string $repositoryRoot, PDO $pdo, PostRecord $root, string $identityId, FeatureFlagEvaluator $flags): bool
    {
        if (!$flags->isEnabled(FeatureFlagRegistry::TITLE_DIRECTIVE_ENABLED)) {
            return false;
        }
        if ($root->authorIdentityId === $identityId && self::rootAttributionVerified($pdo, $root->postId)) {
            return true;
        }
        if (RetractionAuthority::currentOperator($repositoryRoot, $identityId)) {
            return true;
        }

        return $flags->isEnabled(FeatureFlagRegistry::TITLE_DIRECTIVE_APPROVED_MEMBERS) && self::isApproved($pdo, $identityId);
    }

    private static function rootAttributionVerified(PDO $pdo, string $postId): bool
    {
        $stmt = $pdo->prepare('SELECT attribution_verified FROM posts WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $value !== false && (int) $value === 1;
    }

    private static function isApproved(PDO $pdo, string $identityId): bool
    {
        $stmt = $pdo->prepare('SELECT is_approved FROM profiles WHERE identity_id = :identity_id');
        $stmt->execute(['identity_id' => $identityId]);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $value !== false && (int) $value === 1;
    }
}
