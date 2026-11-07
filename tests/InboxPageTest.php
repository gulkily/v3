<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Http\InboxPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

final class InboxPageTest
{
    public function testInboxIsNotFoundWhileTheFlagIsOffAndAsksSignedOutViewersToSignIn(): void
    {
        $this->withRepository(function (string $repositoryRoot, string $databasePath): void {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $this->withFlag('false', function () use ($application): void {
                assertStringContains('Not Found', $this->render($application, '/inbox'));
            });
            $this->withFlag('true', function () use ($application): void {
                $page = $this->render($application, '/inbox/');
                assertStringContains('Inbox is for approved members', $page);
                assertStringContains('Inbox opens once a member approves you.', $page);
                assertStringNotContains('id="inbox-messages"', $page);
            });
        });
    }

    public function testInboxListsUnreadConversationsNewestFirst(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
            $service = new PrivateMessageMailboxService($store, $pdo);
            $service->send($this->viewer('openpgp:alice', 'alice'), $this->message('message-001', 'ilyag'));
            $service->send($this->viewer('openpgp:bob', 'bob'), $this->message('message-002', 'ilyag'));
            $service->send($this->viewer('openpgp:alice', 'alice'), $this->message('message-003', 'ilyag'));
            $service->send($this->viewer('openpgp:ilyag', 'ilyag'), $this->message('message-004', 'bob'));

            $html = $this->renderInbox($databasePath, $this->viewer('openpgp:ilyag', 'ilyag'), static fn (): PrivateMessageStore => $store);
            assertStringContains('id="inbox-messages"', $html);
            assertStringContains('href="/messages/conversation/alice">alice</a>', $html);
            assertStringContains('2 new', $html);
            assertTrue(strpos($html, 'data-counterpart="alice"') < strpos($html, 'data-counterpart="bob"'), 'The newest arrival comes first.');
            assertStringNotContains('Ciphertext', $html);

            $empty = $this->renderInbox($databasePath, $this->viewer('openpgp:alice', 'alice'), static fn (): PrivateMessageStore => $store);
            assertStringContains('No unread messages', $empty);
            assertStringContains('href="/messages">All messages</a>', $empty);
        });
    }

    public function testInboxSurvivesUnavailableOrDisabledMessages(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = $this->viewer('openpgp:ilyag', 'ilyag');
            $failed = $this->renderInbox($databasePath, $viewer, static function (): PrivateMessageStore {
                throw new RuntimeException('Private message database directory is not writable.');
            });
            assertStringContains('Couldn’t check messages.', $failed);
            assertStringContains('<a href="/inbox">Retry</a>', $failed);

            $disabled = $this->renderInbox($databasePath, $viewer, null);
            assertStringNotContains('id="inbox-messages"', $disabled);
            assertStringNotContains('Couldn’t check messages.', $disabled);
        });
    }

    public function testUnapprovedViewerGetsTheAccessPage(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = ['identity_id' => 'openpgp:waiting', 'username_token' => 'waiting', 'is_approved' => 0];
            $html = $this->renderInbox($databasePath, $viewer, null);
            assertStringContains('Inbox is for approved members', $html);
            assertStringContains('data-viewer-identity-id="openpgp:waiting"', $html);
        });
    }

    /** @param (\Closure(): PrivateMessageStore)|null $store @param array<string, mixed> $viewer */
    private function renderInbox(string $databasePath, array $viewer, ?\Closure $store): string
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
        ob_start();
        (new InboxPageController($services, static fn (): array => $viewer, $store))->show('GET');
        return (string) ob_get_clean();
    }

    /** @param callable(\PDO, string): void $test */
    private function withProfiles(callable $test): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-inbox-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            $pdo->exec('CREATE TABLE profiles (
                identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
                username_token TEXT NOT NULL, fallback_label TEXT NOT NULL, signer_fingerprint TEXT NOT NULL,
                bootstrap_post_id TEXT NOT NULL, bootstrap_thread_id TEXT NOT NULL, public_key TEXT NOT NULL,
                is_approved INTEGER NOT NULL, approved_by_identity_id TEXT NULL, approved_by_profile_slug TEXT NULL,
                approved_by_label TEXT NULL, post_count INTEGER NOT NULL, thread_count INTEGER NOT NULL
            )');
            foreach (['alice', 'bob', 'ilyag'] as $username) {
                $pdo->prepare('INSERT INTO profiles VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NULL, NULL, 0, 0)')->execute([
                    'openpgp:' . $username, 'openpgp-' . $username, $username, $username, $username, str_repeat('A', 40),
                    'identity-' . $username, 'thread-' . $username, 'PUBLIC KEY ' . $username,
                ]);
            }
            $test($pdo, $databasePath);
        } finally {
            @unlink($databasePath);
        }
    }

    /** @param callable(string, string): void $test */
    private function withRepository(callable $test): void
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-inbox-' . bin2hex(random_bytes(6));
        $repositoryRoot = $root . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($repositoryRoot));
            exec('cd ' . escapeshellarg($repositoryRoot) . ' && git init -q && git -c user.name=t -c user.email=t@example.invalid add . && git -c user.name=t -c user.email=t@example.invalid commit -qm init');
            $test($repositoryRoot, $root . '/read-model.sqlite3');
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    /** @param callable(): void $test */
    private function withFlag(string $value, callable $test): void
    {
        $previous = getenv(FeatureFlagRegistry::INBOX_ENABLED);
        putenv(FeatureFlagRegistry::INBOX_ENABLED . '=' . $value);
        try {
            $test();
        } finally {
            putenv($previous === false ? FeatureFlagRegistry::INBOX_ENABLED : FeatureFlagRegistry::INBOX_ENABLED . '=' . $previous);
        }
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);
        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function viewer(string $identityId, string $usernameToken): array
    {
        return ['identity_id' => $identityId, 'username_token' => $usernameToken, 'is_approved' => 1];
    }

    /** @return array<string, string> */
    private function message(string $messageId, string $recipient): array
    {
        return [
            'message_id' => $messageId,
            'recipient_username_token' => $recipient,
            'encrypted_envelope' => "-----BEGIN PGP MESSAGE-----\nCiphertext\n-----END PGP MESSAGE-----\n",
        ];
    }
}
