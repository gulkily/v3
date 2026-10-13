<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\ApprovalSeedRecordParser;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;
use RuntimeException;

final class ApprovalPolicy
{
    public function __construct(private readonly PDO $pdo, private readonly string $repositoryRoot) {}

    public static function isOperator(string $repositoryRoot, string $identityId): bool
    {
        if (preg_match('/^openpgp:([a-f0-9]{40})$/D', $identityId, $match) !== 1) {
            return false;
        }
        $path = $repositoryRoot . '/' . CanonicalPathResolver::approvalSeed($match[1]);
        if (!is_file($path) || is_link($path)) {
            return false;
        }
        try {
            return (new ApprovalSeedRecordParser())->parse((string) file_get_contents($path))->approvedIdentityId === $identityId;
        } catch (\Throwable) {
            return false;
        }
    }

    /** This is also the prepared authorization context; do not include presentation-only state. */
    public function evaluate(string $actorId, string $targetId): array
    {
        $actor = ProfileRepository::byIdentityId($this->pdo, $actorId);
        $target = ProfileRepository::byIdentityId($this->pdo, $targetId);
        if ($actor === null || (int) $actor['is_approved'] !== 1) {
            throw new RuntimeException('Only approved users can approve other users.');
        }
        if ($target === null) {
            throw new RuntimeException('Approval target profile not found.');
        }
        if ($actorId === $targetId) {
            throw new RuntimeException('Self-approval is not allowed.');
        }
        if ((int) $target['is_approved'] === 1) {
            throw new RuntimeException('User is already approved.');
        }
        if ($target['username_token'] === '' || $actor['username_token'] === '') {
            throw new RuntimeException('Approval requires a canonical username.');
        }
        $query = $this->pdo->prepare('SELECT COUNT(*) FROM profiles WHERE username_token = ? AND is_approved = 1');
        $query->execute([$target['username_token']]);
        $count = (int) $query->fetchColumn();
        $operator = self::isOperator($this->repositoryRoot, $actorId);
        $sameUsername = $actor['username_token'] === $target['username_token'];
        // Always re-read the live setting, including after a prepared request waited for the write lock.
        $enabled = FeatureFlagEvaluator::forRepository($this->repositoryRoot)
            ->isEnabled(FeatureFlagRegistry::ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL);
        return [
            'actor_identity_id' => $actorId,
            'actor_username' => (string) $actor['username_token'],
            'target_identity_id' => $targetId,
            'target_username' => (string) $target['username_token'],
            'target_profile_slug' => (string) $target['profile_slug'],
            'target_thread_id' => (string) $target['bootstrap_thread_id'],
            'target_post_id' => (string) $target['bootstrap_post_id'],
            'existing_key_count' => $count,
            'classification' => $count === 0 ? 'new_account' : ($sameUsername ? 'own_account_key' : 'cross_account_key'),
            'actor_is_operator' => $operator,
            'cross_account_allowed' => $enabled,
            'allowed' => $operator || $enabled || $count === 0 || $sameUsername,
        ];
    }

    public static function assertAllowed(array $context): void
    {
        if (!$context['allowed']) {
            throw new RuntimeException('This username already has an approved key. An additional key needs approval from the same username or a root-approved operator.');
        }
    }
}
