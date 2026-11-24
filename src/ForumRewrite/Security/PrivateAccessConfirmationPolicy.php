<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\ReadModel\ProfileRepository;
use PDO;
use RuntimeException;

/** Who may confirm which key for private access. The context is also the prepared review. */
final class PrivateAccessConfirmationPolicy
{
    public function __construct(private readonly PDO $pdo, private readonly string $repositoryRoot) {}

    /** @return array{actor_identity_id:string,target_identity_id:string,account:string,authority:string} */
    public function evaluate(string $actorId, string $targetId): array
    {
        if ($actorId === $targetId) {
            throw new RuntimeException('Confirm this key from another device that can read your messages.');
        }
        KeyRetirement::assertActive($this->repositoryRoot, $actorId);
        KeyRetirement::assertActive($this->repositoryRoot, $targetId);
        $actor = ProfileRepository::byIdentityId($this->pdo, $actorId);
        $target = ProfileRepository::byIdentityId($this->pdo, $targetId);
        if ($actor === null || (int) $actor['is_approved'] !== 1) {
            throw new RuntimeException('Only approved keys can confirm another key.');
        }
        if ($target === null || (int) $target['is_approved'] !== 1) {
            throw new RuntimeException('Only an approved key can be confirmed.');
        }
        $access = new PrivateKeyAccess($this->pdo, $this->repositoryRoot);
        if ($access->hasAccess($targetId)) {
            throw new RuntimeException('This key can already read its account\'s messages.');
        }
        $sameAccount = $actor['username_token'] !== '' && $actor['username_token'] === $target['username_token'];
        if ($sameAccount && $access->hasAccess($actorId)) {
            $authority = 'member';
        } elseif (ApprovalPolicy::isActiveOperator($this->repositoryRoot, $actorId)) {
            $authority = 'operator';
        } else {
            throw new RuntimeException('Confirm from a device of this account that can read its messages, or ask an operator.');
        }

        return [
            'actor_identity_id' => $actorId,
            'target_identity_id' => $targetId,
            'account' => (string) $target['username_token'],
            'authority' => $authority,
        ];
    }

    /** @param array<string, string> $context */
    public static function hash(array $context): string
    {
        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }
}
