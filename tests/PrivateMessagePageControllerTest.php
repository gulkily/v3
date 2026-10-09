<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\PrivateMessagePageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

final class PrivateMessagePageControllerTest
{
    public function testEachMailboxShowsOnlyItsOwnMessageMetadata(): void
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

            assertStringContains('<h1>Inbox</h1>', $inbox);
            assertStringContains('<strong>From:</strong> <a href="/user/alice">alice</a>', $inbox);
            assertStringContains('data-private-message-id="message-001"', $inbox);
            assertStringContains('data-action="read-private-message"', $inbox);
            assertStringContains('/assets/private_message_reader.', $inbox);
            assertStringNotContains('Ciphertext should not render', $inbox);
            assertStringContains('<h1>Sent Messages</h1>', $sent);
            assertStringContains('<strong>To:</strong> <a href="/user/ilyag">ilyag</a>', $sent);
            assertStringNotContains('Ciphertext should not render', $sent);
            assertStringContains('No private messages received.', $thirdParty);
            assertStringNotContains('message-001', $thirdParty);
            assertStringNotContains('alice', $thirdParty);
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
        $kind === 'inbox' ? $controller->inbox('GET') : $controller->sent('GET');
        return (string) ob_get_clean();
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
