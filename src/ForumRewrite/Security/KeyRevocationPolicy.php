<?php

declare(strict_types=1);
namespace ForumRewrite\Security;

use ForumRewrite\ReadModel\ProfileRepository;
use PDO;
use RuntimeException;

final class KeyRevocationPolicy
{
    public static function context(PDO $pdo, string $root, string $actor, string $target): array
    {
        $operator = ProfileRepository::byIdentityId($pdo, $actor);
        $profile = ProfileRepository::byIdentityId($pdo, $target);
        if (!$operator || (int) $operator['is_approved'] !== 1 || !ApprovalPolicy::isOperator($root, $actor)) {
            throw new RuntimeException('Sign in with an approved root operator to revoke account keys.');
        }
        if (!$profile || (int) $profile['is_approved'] !== 1 || (int) $profile['is_revoked'] === 1) throw new RuntimeException('Key is not currently approved or is already revoked.');
        if ($actor === $target || ApprovalPolicy::isOperator($root, $target)) throw new RuntimeException('Root-approved keys require separate administrative recovery.');
        if (preg_match('/^[a-z0-9._-]{1,80}$/D', $profile['username_token']) !== 1) throw new RuntimeException('Key requires a canonical account claim before revocation.');
        $q = $pdo->prepare('SELECT identity_id, is_approved, is_revoked FROM profiles WHERE username_token = ? ORDER BY identity_id');
        $q->execute([$profile['username_token']]); $keys = $q->fetchAll(PDO::FETCH_ASSOC);
        return ['actor_identity_id' => $actor, 'target_identity_id' => $target, 'username' => $profile['username_token'],
            'profile_slug' => $profile['profile_slug'], 'keys' => $keys, 'active_key_count' => array_sum(array_column($keys, 'is_approved'))];
    }

    public static function digest(array $context): string { return 'sha256:' . hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)); }
}
