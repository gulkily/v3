<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\PrivateMessagePageController;
use ForumRewrite\Http\PrivateMessageApiController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

final class PrivateMessagePageControllerTest
{
    public function testLegacyMailboxPagesRedirectWithoutDisclosingMessages(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-message-pages-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            $pdo->exec('CREATE TABLE profiles (
                identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
                username_token TEXT NOT NULL, fallback_label TEXT NOT NULL, signer_fingerprint TEXT NOT NULL,
                bootstrap_post_id TEXT NOT NULL, bootstrap_thread_id TEXT NOT NULL, public_key TEXT NOT NULL,
                is_approved INTEGER NOT NULL, approved_by_identity_id TEXT NULL, approved_by_profile_slug TEXT NULL,
                approved_by_label TEXT NULL, post_count INTEGER NOT NULL, thread_count INTEGER NOT NULL
            )');
            $this->addProfile($pdo, 'openpgp:alice', 'alice');
            $this->addProfile($pdo, 'openpgp:ilyag', 'ilyag');
            $this->addProfile($pdo, 'openpgp:mallory', 'mallory');

            $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
            (new PrivateMessageMailboxService($store, $pdo))->send($this->viewer('openpgp:alice', 'alice'), [
                'message_id' => 'message-001',
                'recipient_username_token' => 'ilyag',
                'encrypted_envelope' => "-----BEGIN PGP MESSAGE-----\nCiphertext should not render\n-----END PGP MESSAGE-----\n",
            ]);

            $inbox = $this->renderMailbox($databasePath, $store, $this->viewer('openpgp:ilyag', 'ilyag'), 'inbox');
            $sent = $this->renderMailbox($databasePath, $store, $this->viewer('openpgp:alice', 'alice'), 'sent');
            $thirdParty = $this->renderMailbox($databasePath, $store, $this->viewer('openpgp:mallory', 'mallory'), 'inbox');

            foreach ([$inbox, $sent, $thirdParty] as $html) {
                assertStringContains('href="/messages"', $html);
                assertStringNotContains('message-001', $html);
                assertStringNotContains('Ciphertext should not render', $html);
            }
        } finally {
            @unlink($databasePath);
        }
    }

    public function testLegacyMailboxApiRetainsItsNewestTwentyFiveMessageContract(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-message-pages-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            $pdo->exec('CREATE TABLE profiles (
                identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
                username_token TEXT NOT NULL, fallback_label TEXT NOT NULL, signer_fingerprint TEXT NOT NULL,
                bootstrap_post_id TEXT NOT NULL, bootstrap_thread_id TEXT NOT NULL, public_key TEXT NOT NULL,
                is_approved INTEGER NOT NULL, approved_by_identity_id TEXT NULL, approved_by_profile_slug TEXT NULL,
                approved_by_label TEXT NULL, post_count INTEGER NOT NULL, thread_count INTEGER NOT NULL
            )');
            $this->addProfile($pdo, 'openpgp:alice', 'alice');
            $this->addProfile($pdo, 'openpgp:ilyag', 'ilyag');
            $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
            for ($index = 1; $index <= 26; $index++) {
                $store->storeEnvelope(
                    sprintf('message-%02d', $index),
                    sprintf('2026-10-09T12:%02d:00Z', $index),
                    'alice',
                    'ilyag',
                    'openpgp:alice',
                    'ciphertext-' . $index,
                );
            }

            $viewer = $this->viewer('openpgp:ilyag', 'ilyag');
            $inbox = $this->renderMailbox($databasePath, $store, $viewer, 'inbox');
            $apiInbox = $this->apiMailbox($databasePath, $store, $viewer, 'inbox');

            assertStringContains('href="/messages"', $inbox);
            assertSame(25, count($apiInbox['messages']));
            assertSame('message-26', $apiInbox['messages'][0]['message_id']);
            assertSame('message-02', $apiInbox['messages'][24]['message_id']);
        } finally {
            @unlink($databasePath);
        }
    }

    public function testMessagesListRendersAuthorizedRowsWithoutEnvelopes(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'message-list-');
        try {
            $readPdo = new \PDO('sqlite:' . $databasePath);
            foreach (\ForumRewrite\ReadModel\ReadModelSchema::statements() as $statement) {
                $readPdo->exec($statement);
            }
            $this->addProfile($readPdo, 'alice-key', 'alice');
            $this->addProfile($readPdo, 'bob-key', 'bob');
            $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
            $store->storeEnvelope('list-1', '2026-10-09T12:00:00Z', 'alice', 'bob', 'sender', 'secret envelope');
            $store->storeEnvelope('list-2', '2026-10-09T13:00:00Z', 'bob', 'alice', 'sender', 'secret envelope');
            $viewer = $this->viewer('alice-key', 'alice');
            $html = $this->renderMailbox($databasePath, $store, $viewer, 'conversations');
            assertStringContains('<h1>Messages</h1>', $html);
            assertStringContains('data-role="recipient-form"', $html);
            assertStringContains('<option value="bob">', $html);
            assertStringNotContains('<option value="alice">', $html);
            assertSame(1, substr_count($html, 'data-counterpart="bob"'));
            assertStringContains('href="/messages/conversation/bob"', $html);
            assertStringNotContains('secret envelope', $html);
            $page = $this->apiMailbox($databasePath, $store, $viewer, 'conversations');
            assertSame('list-2', $page['conversations'][0]['message_id']);
            $other = $this->renderMailbox($databasePath, $store, $this->viewer('eve-key', 'eve'), 'conversations');
            assertStringContains('No messages yet', $other);
            assertStringNotContains('list-2', $other);
            $denied = $this->apiMailbox($databasePath, $store, ['identity_id' => 'pending', 'username_token' => 'alice', 'is_approved' => 0], 'conversations');
            assertSame('error', $denied['status']);
            assertSame(false, isset($denied['conversations']));
            $conversation = $this->renderMailbox($databasePath, $store, $viewer, 'conversation');
            assertStringContains('href="/user/bob"', $conversation);
            assertStringContains('Back to Messages', $conversation);
            assertStringContains('data-role="private-message-template"', $conversation);
            preg_match('/data-history-page-cursor="([^"]+)"/', $conversation, $cursorMatch);
            assertSame(true, isset($cursorMatch[1]));
            $snapshot = $store->conversationPageFor('alice', 'bob', html_entity_decode($cursorMatch[1]));
            assertSame(['list-1', 'list-2'], array_column($snapshot['messages'], 'message_id'));
            $history = $this->apiMailbox($databasePath, $store, $viewer, 'conversation', ['username_token' => 'bob', 'cursor' => $cursorMatch[1]]);
            assertSame($snapshot['messages'], $history['messages']);
            assertSame($snapshot['page_cursor'], $history['page_cursor']);
            assertSame(null, $history['next_cursor']);
            $invalid = $this->apiMailbox($databasePath, $store, $viewer, 'conversation', ['username_token' => 'bob', 'cursor' => ['invalid']]);
            assertSame(true, $invalid['restart']);
            assertSame('error', $invalid['status']);
            assertStringContains('data-sender="alice"', $conversation);
            assertStringContains('datetime="2026-10-09T12:00:00Z"', $conversation);
            assertStringContains('/assets/private_message_conversation.', $conversation);
            assertStringNotContains('secret envelope', $conversation);
            $readPdo->exec("UPDATE profiles SET is_approved = 0 WHERE username_token = 'bob'");
            $unavailable = $this->renderMailbox($databasePath, $store, $viewer, 'conversation');
            assertStringContains('Conversation Unavailable', $unavailable);
            assertStringContains('Back to Messages', $unavailable);
            assertStringNotContains('data-private-message-composer', $unavailable);
            $historical = $this->apiMailbox($databasePath, $store, $viewer, 'conversations');
            assertSame('bob', $historical['conversations'][0]['counterpart']);
        } finally {
            @unlink($databasePath);
        }
    }

    /** @param array<string, mixed> $viewer */
    private function renderMailbox(string $databasePath, PrivateMessageStore $store, array $viewer, string $kind): string
    {
        $services = new RouteServices(
            $databasePath,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): array => $viewer,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): array => $viewer,
        );
        $controller = new PrivateMessagePageController($services, static fn (): array => $viewer, static fn (): PrivateMessageStore => $store);

        ob_start();
        $kind === 'conversation' ? $controller->conversation('GET', 'bob') : $controller->$kind('GET');
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    private function apiMailbox(string $databasePath, PrivateMessageStore $store, array $viewer, string $kind, array $query = []): array
    {
        $services = new RouteServices(
            $databasePath,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): array => $viewer,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): array => $viewer,
        );
        $controller = new PrivateMessageApiController($services, static fn (): array => $viewer, static fn (): PrivateMessageStore => $store);

        ob_start();
        $controller->$kind('GET', $query);
        return json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function viewer(string $identityId, string $usernameToken): array
    {
        return ['identity_id' => $identityId, 'username_token' => $usernameToken, 'is_approved' => 1];
    }

    private function addProfile(\PDO $pdo, string $identityId, string $username): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, post_count, thread_count
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key, 1, 0, 0
             )'
        );
        $stmt->execute([
            'identity_id' => $identityId,
            'profile_slug' => str_replace(':', '-', $identityId),
            'username' => $username,
            'username_token' => $username,
            'fallback_label' => $username,
            'signer_fingerprint' => str_repeat('A', 40),
            'bootstrap_post_id' => 'identity-' . $username,
            'bootstrap_thread_id' => 'thread-' . $username,
            'public_key' => 'PUBLIC KEY ' . $username,
        ]);
    }
}
