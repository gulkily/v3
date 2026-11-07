<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Http\InboxPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

final class InboxPageTest
{
    private InboxMarkStore $marks;
    private string $now = '2026-11-07T12:00:00Z';

    public function testInboxIsNotFoundWhileTheFlagIsOffAndAsksSignedOutViewersToSignIn(): void
    {
        $this->withRepository(function (string $repositoryRoot, string $databasePath): void {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $this->withFlag('false', function () use ($application): void {
                assertStringContains('Not Found', $this->render($application, '/unread'));
                assertStringContains('Not Found', $this->render($application, '/inbox'));
            });
            $this->withFlag('true', function () use ($application): void {
                $page = $this->render($application, '/unread/');
                assertStringContains('Unread is for approved members', $page);
                assertStringContains('Unread opens once a member approves you.', $page);
                assertStringContains('Inbox is now Unread.', $this->render($application, '/inbox'), 'The old address redirects.');
                assertStringNotContains('id="inbox-messages"', $page);
            });
        });
    }

    public function testStartupRebuildsAVersion50ReadModelWithTheParentIndex(): void
    {
        $this->withRepository(function (string $repositoryRoot, string $databasePath): void {
            $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath);
            $this->render($application, '/recent/');
            $pdo = new \PDO('sqlite:' . $databasePath);
            $pdo->exec('DROP INDEX posts_parent_idx');
            $pdo->exec("UPDATE metadata SET value='50' WHERE key='schema_version'");
            $pdo = null;

            $this->render(new Application(dirname(__DIR__), $repositoryRoot, $databasePath), '/recent/');
            $pdo = new \PDO('sqlite:' . $databasePath);
            assertSame(\ForumRewrite\ReadModel\ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame('posts_parent_idx', $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='posts_parent_idx'")->fetchColumn());
            $plan = implode(' ', $pdo->query("EXPLAIN QUERY PLAN SELECT post_id FROM posts WHERE parent_id = 'x'")->fetchAll(\PDO::FETCH_COLUMN, 3));
            assertStringContains('posts_parent_idx', $plan);
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
            assertStringContains('data-approved-identity="openpgp:ilyag"', $html, 'The page names the approved key so the browser can remember it.');
            assertStringContains('href="/messages/conversation/alice">alice</a>', $html);
            assertStringContains('2 new', $html);
            assertTrue(strpos($html, 'data-counterpart="alice"') < strpos($html, 'data-counterpart="bob"'), 'The newest arrival comes first.');
            assertStringNotContains('Ciphertext', $html);

            $empty = $this->renderInbox($databasePath, $this->viewer('openpgp:alice', 'alice'), static fn (): PrivateMessageStore => $store);
            assertStringContains('No unread messages', $empty);
            assertStringNotContains('All messages', $empty);
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
            assertStringContains('<a href="/unread">Retry</a>', $failed);

            $disabled = $this->renderInbox($databasePath, $viewer, null);
            assertStringNotContains('id="inbox-messages"', $disabled);
            assertStringNotContains('Couldn’t check messages.', $disabled);
        });
    }

    public function testRepliesShowUntilCaughtUpAndCaughtUpRefusesOtherSites(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = $this->viewer('openpgp:ilyag', 'ilyag');
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            $empty = $this->renderInbox($databasePath, $viewer, null);
            assertStringContains('No new replies', $empty);
            assertStringNotContains('Caught up', $empty);

            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $this->post($pdo, 'my-reply', '2026-11-07T12:02:00Z', 'openpgp:ilyag', 'alice-reply');
            $html = $this->renderInbox($databasePath, $viewer, null);
            assertStringContains('href="/threads/my-thread#post-alice-reply"', $html);
            assertStringNotContains('post-my-reply', $html);
            assertStringContains('<input type="hidden" name="post_ids[]" value="alice-reply">', $html);
            assertStringContains('>Caught up</button>', $html);
            assertStringContains('href="/threads/my-thread#post-alice-reply"', $this->renderInbox($databasePath, $viewer, null), 'Opening Inbox marks nothing.');

            $refused = $this->renderInbox($databasePath, $viewer, null, 'POST', ['section' => 'replies', 'post_ids' => ['alice-reply']], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
            assertStringContains('Couldn’t save. Nothing was marked read.', $refused);
            assertStringContains('>Retry</button>', $refused);
            assertStringContains('post-alice-reply', $this->renderInbox($databasePath, $viewer, null));

            $this->renderInbox($databasePath, $viewer, null, 'POST', ['section' => 'replies', 'post_ids' => ['alice-reply']], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            assertStringContains('No new replies', $this->renderInbox($databasePath, $viewer, null));
        });
    }

    public function testNewPostsFollowRepliesWithTheirOwnMark(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = $this->viewer('openpgp:ilyag', 'ilyag');
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            $this->renderInbox($databasePath, $viewer, null);
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $this->post($pdo, 'bob-thread', '2026-11-07T12:02:00Z', 'openpgp:bob');
            $this->post($pdo, 'my-new-thread', '2026-11-07T12:03:00Z', 'openpgp:ilyag');

            $html = $this->renderInbox($databasePath, $viewer, null);
            assertTrue(strpos($html, 'id="inbox-replies"') < strpos($html, 'id="inbox-new"'), 'Replies come before New posts.');
            $newSection = substr($html, (int) strpos($html, 'id="inbox-new"'));
            assertStringContains('post-bob-thread', $newSection);
            assertStringNotContains('post-alice-reply', $newSection, 'A reply to me is listed only under Replies.');
            assertStringNotContains('post-my-new-thread', $html);

            $this->renderInbox($databasePath, $viewer, null, 'POST', ['section' => 'new', 'post_ids' => ['bob-thread']], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            $after = $this->renderInbox($databasePath, $viewer, null);
            assertStringContains('No new posts', $after);
            assertStringContains('post-alice-reply', $after, 'Catching up on New posts leaves Replies alone.');
        });
    }

    public function testCountAddsUnreadConversationsAndRepliesButNotNewPosts(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = $this->viewer('openpgp:ilyag', 'ilyag');
            $store = new PrivateMessageStore(new \PDO('sqlite::memory:'));
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            assertSame(0, $this->count($databasePath, $viewer, static fn (): PrivateMessageStore => $store)['unread_count']);

            (new PrivateMessageMailboxService($store, $pdo))->send($this->viewer('openpgp:alice', 'alice'), $this->message('message-001', 'ilyag'));
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $this->post($pdo, 'bob-thread', '2026-11-07T12:02:00Z', 'openpgp:bob');
            $state = $this->count($databasePath, $viewer, static fn (): PrivateMessageStore => $store);
            assertSame(['ok', 2, 1, 1, true], [$state['status'], $state['unread_count'], $state['replies'], $state['conversations'], $state['messages_available']]);
            assertSame(2, $this->count($databasePath, $viewer, static fn (): PrivateMessageStore => $store)['unread_count'], 'Reading the count marks nothing.');

            $this->renderInbox($databasePath, $viewer, null, 'POST', ['section' => 'replies', 'post_ids' => ['alice-reply']], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
            assertSame(1, $this->count($databasePath, $viewer, static fn (): PrivateMessageStore => $store)['unread_count']);
            assertSame(0, $this->count($databasePath, $viewer, null)['unread_count'], 'Without private messages the count is replies only.');
            $failed = $this->count($databasePath, $viewer, static function (): PrivateMessageStore { throw new RuntimeException('down'); });
            assertSame([0, false], [$failed['unread_count'], $failed['messages_available']]);

            $guest = $this->count($databasePath, ['identity_id' => 'openpgp:waiting', 'username_token' => 'waiting', 'is_approved' => 0], null);
            assertSame('error', $guest['status']);
        });
    }

    public function testUnapprovedViewerGetsTheAccessPage(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $databasePath): void {
            $viewer = ['identity_id' => 'openpgp:waiting', 'username_token' => 'waiting', 'is_approved' => 0];
            $html = $this->renderInbox($databasePath, $viewer, null);
            assertStringContains('Unread is for approved members', $html);
            assertStringContains('data-viewer-identity-id="openpgp:waiting"', $html);
        });
    }

    /** @param (\Closure(): PrivateMessageStore)|null $store @param array<string, mixed> $viewer */
    /** @param array<string, mixed> $input @param array<string, string> $headers */
    private function renderInbox(string $databasePath, array $viewer, ?\Closure $store, string $method = 'GET', array $input = [], array $headers = []): string
    {
        $server = $_SERVER;
        foreach ($headers as $name => $value) $_SERVER[$name] = $value;
        $_POST = $input;
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
        (new InboxPageController($services, static fn (): array => $viewer, $store, fn (): InboxMarkStore => $this->marks))->show($method, $input);
        $_SERVER = $server;
        $_POST = [];
        return (string) ob_get_clean();
    }

    /** @param (\Closure(): PrivateMessageStore)|null $store @param array<string, mixed> $viewer @return array<string, mixed> */
    private function count(string $databasePath, array $viewer, ?\Closure $store): array
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
        (new InboxPageController($services, static fn (): array => $viewer, $store, fn (): InboxMarkStore => $this->marks))->unread('GET');
        return json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function post(\PDO $pdo, string $postId, string $createdAt, string $author, ?string $parentId = null): void
    {
        $threadId = $parentId === null ? $postId : (string) $pdo->query('SELECT thread_id FROM posts WHERE post_id = ' . $pdo->quote($parentId))->fetchColumn();
        $pdo->prepare("INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, author_identity_id, sequence_number)
            VALUES (?, ?, ?, ?, ?, ?, '[\"general\"]', ?, 0)")
            ->execute([$postId, $createdAt, $threadId, $parentId, $parentId === null ? 'Subject ' . $postId : null, 'Body ' . $postId, $author]);
    }

    /** @param callable(\PDO, string): void $test */
    private function withProfiles(callable $test): void
    {
        $this->now = '2026-11-07T12:00:00Z';
        $this->marks = new InboxMarkStore(new \PDO('sqlite::memory:'), fn (): string => $this->now);
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-inbox-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            foreach (\ForumRewrite\ReadModel\ReadModelSchema::statements() as $statement) $pdo->exec($statement);
            foreach (['alice', 'bob', 'ilyag'] as $username) {
                $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                    bootstrap_post_id, bootstrap_thread_id, public_key, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute([
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
