<?php
declare(strict_types=1);
namespace ForumRewrite\Security;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\FeatureFlags\{FeatureFlagEvaluator, FeatureFlagRegistry};
use PDO;
use RuntimeException;

/** Evaluated against a freshly rebuilt projection under the canonical write lock. */
final class KeyRetirementPolicy
{
    public function __construct(private readonly PDO $pdo, private readonly string $root) {}
    private function active(string $id): array
    {
        KeyRetirement::assertActive($this->root, $id);
        $profile = ProfileRepository::byIdentityId($this->pdo, $id);
        if ($profile === null || (int)$profile['is_approved'] !== 1) throw new RuntimeException('Retirement requires active approved keys.');
        return $profile;
    }
    public static function unchanged(array $profile): bool
    {
        return (string)($profile['username_token'] ?? '') !== ''
            && ($profile['username_token'] ?? '') === ($profile['bootstrap_username_token'] ?? null)
            && (int)($profile['has_name_history'] ?? 1) === 0;
    }
    private static function snapshot(array $profile): array
    {
        return array_intersect_key($profile, array_flip(['identity_id','username_token','bootstrap_username_token','has_name_history','is_approved','approved_by_identity_id']));
    }
    public function evaluate(string $actorId, string $targetId, string $replacementId = ''): array
    {
        if ($actorId === $targetId) throw new RuntimeException('Use another approved key to retire this key.');
        $actor = $this->active($actorId); $target = $this->active($targetId);
        $operator = ApprovalPolicy::isActiveOperator($this->root,$actorId);
        $rename = FeatureFlagEvaluator::forRepository($this->root)->isEnabled(FeatureFlagRegistry::USERNAME_CHANGES_ENABLED);
        if (!$operator && ($rename || !self::unchanged($actor) || !self::unchanged($target) || $actor['username_token'] !== $target['username_token'])) {
            throw new RuntimeException('Member retirement requires unchanged account keys with username changes disabled. Ask a root-approved operator for help.');
        }
        $replacement = null;
        foreach (ProfileRepository::byUsernameToken($this->pdo,$target['username_token']) as $candidate) {
            if ($candidate['identity_id'] === $targetId || (int)$candidate['is_approved'] !== 1 || !self::unchanged($candidate)) continue;
            if ($replacementId !== '' && $candidate['identity_id'] !== $replacementId) continue;
            if (KeyRetirement::read($this->root,$candidate['identity_id']) !== null) continue;
            $replacement = $candidate; break;
        }
        if ($replacement === null) throw new RuntimeException('Approve an unchanged replacement key for this account before retiring this key.');
        return ['actor'=>self::snapshot($actor),'target'=>self::snapshot($target),'replacement'=>self::snapshot($replacement),
            'authority'=>$operator?'operator':'member','rename_enabled'=>$rename];
    }
    public static function hash(array $context): string
    {
        return hash('sha256',json_encode($context,JSON_THROW_ON_ERROR));
    }
    public static function review(array $context): array
    {
        return ['account'=>$context['target']['username_token'],'target_identity_id'=>$context['target']['identity_id'],
            'replacement_identity_id'=>$context['replacement']['identity_id'],'authority'=>$context['authority'],
            'warning'=>'Permanent: this key will lose account access. Past posts and signatures remain.'];
    }
}
