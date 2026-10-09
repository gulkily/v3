<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use ForumRewrite\ReadModel\ProfileRepository;
use PDO;

final class ApprovedUserKeyResolver
{
    /**
     * The public `/user/<username>` contract defines a composite user as all
     * approved profiles sharing the normalized username token. Private
     * messaging encrypts to every public key in that approved profile set.
     *
     * @return list<array{identity_id:string,profile_slug:string,public_key:string}>
     */
    public function keysForUsernameToken(PDO $pdo, string $usernameToken): array
    {
        $usernameToken = strtolower(trim($usernameToken));
        if ($usernameToken === '') {
            return [];
        }

        $keys = [];
        foreach (ProfileRepository::byUsernameToken($pdo, $usernameToken) as $profile) {
            if ((int) ($profile['is_approved'] ?? 0) !== 1) {
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
