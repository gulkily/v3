<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageMailboxServiceTest
{
    public function testApprovedUsersCanSendAndReadOnlyTheirCompositeMailboxes(): void
    {
        $readPdo = $this->profilesDatabase();
        $this->addProfile($readPdo, 'openpgp:alice', 'openpgp-alice', 'alice', 'PUBLIC KEY ALICE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-one', 'openpgp-ilyag-one', 'ilyag', 'PUBLIC KEY ILYAG ONE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-two', 'openpgp-ilyag-two', 'ilyag', 'PUBLIC KEY ILYAG TWO', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-pending', 'openpgp-ilyag-pending', 'ilyag', 'PUBLIC KEY ILYAG PENDING', 0);
        $this->addProfile($readPdo, 'openpgp:mallory', 'openpgp-mallory', 'mallory', 'PUBLIC KEY MALLORY', 1);

        $service = new PrivateMessageMailboxService(
            new PrivateMessageStore(new \PDO('sqlite::memory:')),
            $readPdo,
            new ApprovedUserKeyResolver(),
        );
        $result = $service->send($this->viewer('openpgp:alice', 'alice'), [
            'message_id' => 'message-001',
            'recipient_username_token' => 'ilyag',
            'encrypted_envelope' => $this->envelope(),
        ]);

        assertSame('message-001', $result['message_id']);
        assertSame('ilyag', $result['recipient_username_token']);
        assertSame('message-001', $service->inbox($this->viewer('openpgp:ilyag-two', 'ilyag'))[0]['message_id']);
        assertSame('message-001', $service->sent($this->viewer('openpgp:alice', 'alice'))[0]['message_id']);
        assertSame([], $service->inbox($this->viewer('openpgp:mallory', 'mallory')));
        assertSame([], $service->sent($this->viewer('openpgp:mallory', 'mallory')));
    }

    public function testRejectsUnauthenticatedUnapprovedAndMalformedRequests(): void
    {
        $readPdo = $this->profilesDatabase();
        $this->addProfile($readPdo, 'openpgp:alice', 'openpgp-alice', 'alice', 'PUBLIC KEY ALICE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag', 'openpgp-ilyag', 'ilyag', 'PUBLIC KEY ILYAG', 1);
        $service = new PrivateMessageMailboxService(new PrivateMessageStore(new \PDO('sqlite::memory:')), $readPdo);

        assertThrowsPrivateMessage(
            fn (): array => $service->inbox([]),
            \RuntimeException::class,
            'An approved authenticated identity is required.',
        );
        assertThrowsPrivateMessage(
            fn (): array => $service->send($this->viewer('openpgp:alice', 'alice'), [
                'message_id' => 'message-001',
                'recipient_username_token' => 'ilyag',
                'encrypted_envelope' => 'plaintext',
            ]),
            \InvalidArgumentException::class,
            'Encrypted message envelope is invalid.',
        );
        assertThrowsPrivateMessage(
            fn (): array => $service->send($this->viewer('openpgp:alice', 'alice'), [
                'message_id' => 'message-001',
                'recipient_username_token' => 'missing',
                'encrypted_envelope' => $this->envelope(),
            ]),
            \InvalidArgumentException::class,
            'Recipient has no approved profile keys.',
        );
    }

    public function testReturnsEveryApprovedProfileKeyForAnAuthenticatedViewer(): void
    {
        $readPdo = $this->profilesDatabase();
        $this->addProfile($readPdo, 'openpgp:alice', 'openpgp-alice', 'alice', 'PUBLIC KEY ALICE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-one', 'openpgp-ilyag-one', 'ilyag', 'PUBLIC KEY ILYAG ONE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-two', 'openpgp-ilyag-two', 'ilyag', 'PUBLIC KEY ILYAG TWO', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag-pending', 'openpgp-ilyag-pending', 'ilyag', 'PUBLIC KEY ILYAG PENDING', 0);
        $service = new PrivateMessageMailboxService(new PrivateMessageStore(new \PDO('sqlite::memory:')), $readPdo);

        assertSame(
            ['PUBLIC KEY ILYAG ONE', 'PUBLIC KEY ILYAG TWO'],
            array_column($service->recipientKeys($this->viewer('openpgp:alice', 'alice'), 'ilyag'), 'public_key'),
        );
    }

    public function testLimitsServiceMailboxesToTwentyFiveMessages(): void
    {
        $readPdo = $this->profilesDatabase();
        $this->addProfile($readPdo, 'openpgp:alice', 'openpgp-alice', 'alice', 'PUBLIC KEY ALICE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag', 'openpgp-ilyag', 'ilyag', 'PUBLIC KEY ILYAG', 1);
        $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
        for ($index = 1; $index <= 26; $index++) {
            $store->storeEnvelope(
                sprintf('message-%02d', $index),
                sprintf('2026-10-09T12:%02d:00Z', $index),
                'alice',
                'ilyag',
                'openpgp:alice',
                $this->envelope(),
            );
        }
        $service = new PrivateMessageMailboxService($store, $readPdo);

        assertSame(25, count($service->inbox($this->viewer('openpgp:ilyag', 'ilyag'))));
        assertSame(25, count($service->sent($this->viewer('openpgp:alice', 'alice'))));
    }

    /** @return array<string, mixed> */
    private function viewer(string $identityId, string $usernameToken): array
    {
        return ['identity_id' => $identityId, 'username_token' => $usernameToken, 'is_approved' => 1];
    }

    private function envelope(): string
    {
        return "-----BEGIN PGP MESSAGE-----\nCiphertext\n-----END PGP MESSAGE-----\n";
    }

    private function profilesDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE profiles (
            identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
            username_token TEXT NOT NULL, fallback_label TEXT NOT NULL, signer_fingerprint TEXT NOT NULL,
            bootstrap_post_id TEXT NOT NULL, bootstrap_thread_id TEXT NOT NULL, public_key TEXT NOT NULL,
            is_approved INTEGER NOT NULL, approved_by_identity_id TEXT NULL, approved_by_profile_slug TEXT NULL,
            approved_by_label TEXT NULL, post_count INTEGER NOT NULL, thread_count INTEGER NOT NULL
        )');
        return $pdo;
    }

    private function addProfile(\PDO $pdo, string $identityId, string $profileSlug, string $username, string $publicKey, int $approved): void
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
            'signer_fingerprint' => str_repeat('A', 40),
            'bootstrap_post_id' => 'identity-' . $profileSlug,
            'bootstrap_thread_id' => 'thread-' . $profileSlug,
            'public_key' => $publicKey,
            'is_approved' => $approved,
        ]);
    }
}

if (!function_exists('assertThrowsPrivateMessage')) {
    function assertThrowsPrivateMessage(callable $callback, string $class, string $message): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            assertSame($class, $exception::class);
            assertSame($message, $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected exception was not thrown.');
    }
}
