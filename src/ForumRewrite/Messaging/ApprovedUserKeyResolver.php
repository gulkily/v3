<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use ForumRewrite\ReadModel\ProfileRepository;
use PDO;

final class ApprovedUserKeyResolver
{
    public function __construct(private readonly ?string $repositoryRoot = null) {}
    /**
     * The public `/user/<username>` contract defines a composite user as all
     * approved profiles sharing the normalized username token. Private
     * messaging encrypts to every public key in that approved profile set that has private
     * access (Security\PrivateKeyAccess) when FORUM_PRIVATE_KEY_ACCESS_ENFORCED is on.
     *
     * @return list<array{identity_id:string,profile_slug:string,public_key:string}>
     */
    public function keysForUsernameToken(PDO $pdo, string $usernameToken, bool $historical = false): array
    {
        $usernameToken = strtolower(trim($usernameToken));
        if ($usernameToken === '') {
            return [];
        }

        // New messages go only to keys with private access; historical lookups verify old signatures.
        $access = !$historical && \ForumRewrite\Security\PrivateKeyAccess::enforced($this->repositoryRoot)
            ? new \ForumRewrite\Security\PrivateKeyAccess($pdo, $this->repositoryRoot) : null;
        $keys = [];
        foreach (ProfileRepository::byUsernameToken($pdo, $usernameToken) as $profile) {
            if ($this->repositoryRoot !== null) $profile = \ForumRewrite\Security\KeyRetirement::profile($this->repositoryRoot, $profile);
            if ((int) ($profile['is_approved'] ?? 0) !== 1 && !($historical && !empty($profile['retired_at']) && (int)($profile['was_approved'] ?? 0)===1)) {
                continue;
            }

            if ($access !== null && !$access->hasAccess((string) $profile['identity_id'])) {
                continue;
            }

            $publicKey = (string) ($profile['public_key'] ?? '');
            if (trim($publicKey) === '') {
                continue;
            }

            $keys[$publicKey] = [
                'identity_id' => (string) $profile['identity_id'],
                'profile_slug' => (string) $profile['profile_slug'],
                'public_key' => $publicKey,
            ];
        }

        return array_values($keys);
    }
}
