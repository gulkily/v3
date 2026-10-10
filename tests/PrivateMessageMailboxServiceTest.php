<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageMailboxServiceTest
{
    public function testUnreadProgressIsSharedAcrossApprovedIdentitiesOnly(): void
    {
        $profiles = $this->profilesDatabase();
        $this->addProfile($profiles, 'bob-key', 'bob-key', 'bob', 'KEY', 1);
        $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
        $service = new PrivateMessageMailboxService($store, $profiles);
        $store->storeEnvelope('received', '2026-10-10', 'bob', 'alice', 'bob-key', 'cipher');
        $first = $this->viewer('alice-one', 'alice');
        $second = $this->viewer('alice-two', 'alice');
        $page = $service->conversationPage($first, 'bob');
        assertSame(1, $service->unreadState($second)['unread_count']);
        assertSame(0, $service->acknowledge($second, 'bob', $page['read_token'])['unread_count']);
        assertSame(0, $service->unreadState($first)['unread_count']);
        assertThrowsPrivateMessage(fn () => $service->unreadState(array_replace($first, ['is_approved' => 0])), \RuntimeException::class, 'An approved authenticated identity is required.');
        $profiles->exec('UPDATE profiles SET is_approved = 0');
        assertThrowsPrivateMessage(fn () => $service->acknowledge($first, 'bob', $page['read_token']), \InvalidArgumentException::class, 'Recipient has no approved profile keys.');
    }

    public function testRetryReturnsOriginalAcceptanceAndRejectsConflicts(): void
    {
        $profiles = $this->profilesDatabase();
        $this->addProfile($profiles, 'openpgp:ilyag', 'ilyag', 'ilyag', 'KEY', 1);
        $pdo = new \PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        $service = new PrivateMessageMailboxService($store, $profiles);
        $viewer = $this->viewer('openpgp:alice', 'alice');
        $input = ['message_id' => 'retry', 'recipient_username_token' => 'ilyag', 'encrypted_envelope' => $this->envelope()];
        $first = $service->send($viewer, $input);
        $profiles->exec('UPDATE profiles SET is_approved = 0');
        assertSame($first, $service->send($viewer, $input));
        foreach ([
            [$viewer, array_replace($input, ['encrypted_envelope' => str_replace('Ciphertext', 'Different', $this->envelope())])],
            [$viewer, array_replace($input, ['recipient_username_token' => 'other'])],
            [$this->viewer('openpgp:other', 'alice'), $input],
            [$this->viewer('openpgp:mallory', 'mallory'), $input],
        ] as [$actor, $conflict]) {
            assertThrowsPrivateMessage(fn () => $service->send($actor, $conflict), \InvalidArgumentException::class, 'This send attempt conflicts with an existing message.');
        }
        $attempt = $pdo->query('SELECT * FROM private_messages')->fetch(\PDO::FETCH_ASSOC);
        $attempt['created_at'] = '2099-01-01T00:00:00Z';
        assertSame($first, $store->acceptEnvelope($attempt));
        assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM private_messages')->fetchColumn());
    }

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

    public function testConversationReturnsNewestTwoWayMessagesInChronologicalOrder(): void
    {
        $readPdo = $this->profilesDatabase();
        $this->addProfile($readPdo, 'openpgp:alice', 'openpgp-alice', 'alice', 'PUBLIC KEY ALICE', 1);
        $this->addProfile($readPdo, 'openpgp:ilyag', 'openpgp-ilyag', 'ilyag', 'PUBLIC KEY ILYAG', 1);
        $this->addProfile($readPdo, 'openpgp:mallory', 'openpgp-mallory', 'mallory', 'PUBLIC KEY MALLORY', 1);
        $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
        for ($index = 1; $index <= 27; $index++) {
            $store->storeEnvelope(sprintf('message-%02d', $index), sprintf('2026-10-09T12:%02d:00Z', $index), $index % 2 === 0 ? 'alice' : 'ilyag', $index % 2 === 0 ? 'ilyag' : 'alice', 'openpgp:alice', $this->envelope());
        }
        $store->storeEnvelope('mallory-message', '2026-10-09T13:00:00Z', 'mallory', 'alice', 'openpgp:mallory', $this->envelope());
        $service = new PrivateMessageMailboxService($store, $readPdo);

        $messages = $service->conversation($this->viewer('openpgp:alice', 'alice'), 'ilyag');

        assertSame(25, count($messages));
        assertSame('message-03', $messages[0]['message_id']);
        assertSame('message-27', $messages[24]['message_id']);
        assertSame(false, in_array('mallory-message', array_column($messages, 'message_id'), true));
        assertThrowsPrivateMessage(fn (): array => $service->conversation($this->viewer('openpgp:alice', 'alice'), 'alice'), \InvalidArgumentException::class, 'A conversation counterpart must be another user.');
    }

    public function testConversationListRequiresApprovalAndRetainsUnavailableCounterparts(): void
    {
        $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
        $store->storeEnvelope('historical', '2026-10-09T12:00:00Z', 'alice', 'unavailable', 'sender', 'encrypted');
        $service = new PrivateMessageMailboxService($store, $this->profilesDatabase());
        assertSame('unavailable', $service->conversations($this->viewer('openpgp:alice', 'alice'))['conversations'][0]['counterpart']);
        assertSame([], $service->conversations($this->viewer('openpgp:mallory', 'mallory'))['conversations']);
        foreach ([[], ['identity_id' => 'pending', 'username_token' => 'alice', 'is_approved' => 0]] as $viewer) {
            assertThrowsPrivateMessage(fn (): array => $service->conversations($viewer), \RuntimeException::class, 'An approved authenticated identity is required.');
        }
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
