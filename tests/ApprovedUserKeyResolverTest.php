<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\ApprovedUserKeyResolver;

final class ApprovedUserKeyResolverTest
{
    public function testReturnsEveryApprovedProfileKeyForAUsername(): void
    {
        $pdo = $this->profilesDatabase();
        $this->addProfile($pdo, 'openpgp:alpha', 'openpgp-alpha', 'ilyag', 'PUBLIC KEY ALPHA', 1);
        $this->addProfile($pdo, 'openpgp:beta', 'openpgp-beta', 'ilyag', 'PUBLIC KEY BETA', 1);
        $this->addProfile($pdo, 'openpgp:pending', 'openpgp-pending', 'ilyag', 'PUBLIC KEY PENDING', 0);

        $keys = (new ApprovedUserKeyResolver())->keysForUsernameToken($pdo, 'ILyAg');

        assertSame([
            [
                'identity_id' => 'openpgp:alpha',
                'profile_slug' => 'openpgp-alpha',
                'public_key' => 'PUBLIC KEY ALPHA',
            ],
            [
                'identity_id' => 'openpgp:beta',
                'profile_slug' => 'openpgp-beta',
                'public_key' => 'PUBLIC KEY BETA',
            ],
        ], $keys);
    }

    public function testDoesNotTreatOnlyPendingProfilesAsRecipientKeys(): void
    {
        $pdo = $this->profilesDatabase();
        $this->addProfile($pdo, 'openpgp:pending', 'openpgp-pending', 'ilyag', 'PUBLIC KEY PENDING', 0);

        assertSame([], (new ApprovedUserKeyResolver())->keysForUsernameToken($pdo, 'ilyag'));
    }

    public function testDeduplicatesTheSamePublicKey(): void
    {
        $pdo = $this->profilesDatabase();
        $this->addProfile($pdo, 'openpgp:alpha', 'openpgp-alpha', 'ilyag', 'PUBLIC KEY ALPHA', 1);
        $this->addProfile($pdo, 'openpgp:beta', 'openpgp-beta', 'ilyag', 'PUBLIC KEY ALPHA', 1);

        assertSame(1, count((new ApprovedUserKeyResolver())->keysForUsernameToken($pdo, 'ilyag')));
    }

    private function profilesDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE profiles (
            identity_id TEXT PRIMARY KEY,
            profile_slug TEXT NOT NULL,
            username TEXT NOT NULL,
            username_token TEXT NOT NULL,
            fallback_label TEXT NOT NULL,
            signer_fingerprint TEXT NOT NULL,
            bootstrap_post_id TEXT NOT NULL,
            bootstrap_thread_id TEXT NOT NULL,
            public_key TEXT NOT NULL,
            is_approved INTEGER NOT NULL,
            approved_by_identity_id TEXT NULL,
            approved_by_profile_slug TEXT NULL,
            approved_by_label TEXT NULL,
            post_count INTEGER NOT NULL,
            thread_count INTEGER NOT NULL
        )');

        return $pdo;
    }

    private function addProfile(PDO $pdo, string $identityId, string $profileSlug, string $username, string $publicKey, int $approved): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, post_count, thread_count
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key, :is_approved, 0, 0
             )'
        );
        $stmt->execute([
            'identity_id' => $identityId,
            'profile_slug' => $profileSlug,
            'username' => $username,
            'username_token' => strtolower($username),
            'fallback_label' => $username,
            'signer_fingerprint' => strtoupper(substr(str_repeat('a', 40), 0, 40)),
            'bootstrap_post_id' => 'identity-' . $profileSlug,
            'bootstrap_thread_id' => 'thread-' . $profileSlug,
            'public_key' => $publicKey,
            'is_approved' => $approved,
        ]);
    }
}
