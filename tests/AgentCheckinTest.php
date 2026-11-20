<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Http\AgentCheckinController;
use ForumRewrite\Http\InboxPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Tools\MarkedPostsQuery;
use ForumRewrite\Write\LocalWriteService;

/** GET /api/checkin (agent_checkin): what needs an agent's attention, each section ending with the next request. */
final class AgentCheckinTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private InboxMarkStore $marks;

    public function testPublicSectionsListOpenIssueItemsAndNewestThreads(): void
    {
        $this->withSite(function (Application $app, LocalWriteService $writer): void {
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED]);
            $writer->applyPostTag(['post_id' => 'reply-002', 'tag' => 'claimed', 'author_identity_id' => self::APPROVED]);
            $writer->applyPostTag(['post_id' => 'reply-closed', 'tag' => 'closed', 'author_identity_id' => self::APPROVED]);
            [$status, $text] = $this->get($app, '/api/checkin');
            assertSame(200, $status);
            assertStringContains("Check-in: ", $text);
            assertStringContains("Viewer: anonymous\nSign-in: replies to you need a signed-in approved key. With a key, run: python3 quill.py --site http://forum.example checkin\n", $text);
            assertStringNotContains('Replies to you', $text);

            $open = $this->section($text, 'Open #issue items');
            assertStringContains('Open #issue items: 3', $open);
            assertStringContains('reply-003 in "Hello world": Third reply, still open.', $open);
            assertStringContains('reply-002 in "Hello world" (claimed by ', $open);
            assertStringContains('root-001 opening post of "Hello world"', $open);
            assertStringContains('  Read: GET /api/get_post?post_id=reply-003', $open);
            assertStringContains('Next: claim one before working on it: POST /api/apply_post_tag', $open);
            foreach (['reply-001', 'reply-closed', 'reply-meta'] as $absent) {
                assertStringNotContains($absent, $open);
            }
            // Newest first: reply-003 is the latest open post.
            assertTrue(strpos($open, 'reply-003') < strpos($open, 'reply-002'));

            $threads = $this->section($text, 'Newest threads');
            assertStringContains('Newest threads: 3', $threads);
            assertStringContains('thread-meta "A meta thread" (1 reply)', $threads);
            assertStringContains('  Read: GET /api/get_thread?thread_id=root-001', $threads);
            assertStringContains('Next: reply with python3 quill.py --site ', $threads);
            assertTrue(strpos($threads, 'thread-meta') < strpos($threads, 'thread-zenmemes-rules'));
            assertTrue(strpos($threads, 'thread-zenmemes-rules') < strpos($threads, 'root-001'));

            // Every link is short and readable: no cursors or encoded state.
            preg_match_all('/^  Read: (.*)$/m', $text, $reads);
            assertTrue(count($reads[1]) === 6, $text);
            foreach ($reads[1] as $read) {
                assertTrue(preg_match('#^GET /api/get_(post|thread)\?(post|thread)_id=[A-Za-z0-9-]+$#', $read) === 1, $read);
            }
            assertStringNotContains('cursor', $text);
            assertStringNotContains('position=', $text);
        });
    }

    public function testApprovedViewerGetsRepliesToThemFirst(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $database): void {
            $viewer = $this->profile($pdo, 'openpgp:ilyag');
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $this->post($pdo, 'my-reply', '2026-11-07T12:02:00Z', 'openpgp:ilyag', 'alice-reply');
            $text = $this->checkin($database, $viewer);
            assertStringContains("Viewer: ilyag (approved)\n\n", $text);
            $replies = $this->section($text, 'Replies to you');
            assertStringContains("Replies to you: 1\n- alice-reply by alice in \"Subject my-thread\": Body alice-reply\n  Read: GET /api/get_post?post_id=alice-reply\n", $replies);
            assertStringContains('Next: reply with python3 quill.py --site http://forum.example reply --thread THREAD_ID --body-file reply.txt', $replies);
            assertStringNotContains('my-reply', $replies);
            assertTrue(strpos($text, 'Replies to you') < strpos($text, 'Newest threads'));
            assertStringNotContains('Unread conversations', $text, 'Messages are off in this fixture.');
            assertStringContains('alice-reply', $this->checkin($database, $viewer), 'Reading the check-in marks nothing.');
        });
    }

    public function testRepliesAreCappedButCounted(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $database): void {
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            for ($i = 1; $i <= 12; $i++) {
                $this->post($pdo, sprintf('reply-%02d', $i), sprintf('2026-11-07T12:%02d:00Z', $i), 'openpgp:alice', 'my-thread');
            }
            $replies = $this->section($this->checkin($database, $this->profile($pdo, 'openpgp:ilyag')), 'Replies to you');
            assertStringContains('Replies to you: 10 of 12', $replies);
            assertSame(10, substr_count($replies, '  Read: '));
        });
    }

    public function testUnapprovedViewerIsToldHowToGetApproved(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $database): void {
            $pdo->exec("UPDATE profiles SET is_approved = 0 WHERE identity_id = 'openpgp:bob'");
            $this->post($pdo, 'bob-thread', '2026-11-07T11:00:00Z', 'openpgp:bob');
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'bob-thread');
            $text = $this->checkin($database, $this->profile($pdo, 'openpgp:bob'));
            assertStringContains("Viewer: bob (openpgp:bob, not approved)\nApproval: replies to you need an approved key. Ask an approved member to approve openpgp:bob; see /llms.txt, \"Approving keys\".\n", $text);
            assertStringNotContains('Replies to you', $text);
            assertStringContains('Newest threads: 1', $text);
        });
    }

    public function testUnreadOffStillShowsPublicSections(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $database): void {
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $text = $this->checkin($database, $this->profile($pdo, 'openpgp:ilyag'), inbox: false);
            assertStringContains("Viewer: ilyag (approved)\nUnread: disabled on this server, so replies to you are not shown.\n", $text);
            assertStringNotContains('Replies to you:', $text);
        });
    }

    public function testJsonCarriesTheSameSections(): void
    {
        $this->withProfiles(function (\PDO $pdo, string $database): void {
            $this->post($pdo, 'my-thread', '2026-11-07T11:00:00Z', 'openpgp:ilyag');
            $this->post($pdo, 'alice-reply', '2026-11-07T12:01:00Z', 'openpgp:alice', 'my-thread');
            $data = json_decode($this->checkin($database, $this->profile($pdo, 'openpgp:ilyag'), ['format' => 'json']), true, flags: JSON_THROW_ON_ERROR);
            assertSame('ok', $data['status']);
            assertSame('http://forum.example', $data['site']);
            assertSame(['status' => 'approved', 'username' => 'ilyag', 'identity_id' => 'openpgp:ilyag'], $data['viewer']);
            assertSame([], $data['notices']);
            assertSame(['replies_to_you', 'open_items', 'newest_threads'], array_column($data['sections'], 'key'));
            assertSame(['id' => 'alice-reply', 'thread_id' => 'my-thread', 'line' => 'alice-reply by alice in "Subject my-thread": Body alice-reply', 'read' => 'GET /api/get_post?post_id=alice-reply'], $data['sections'][0]['items'][0]);
            assertSame('', $data['sections'][1]['next'], 'An empty section names no next request.');
            $anonymous = json_decode($this->checkin($database, null, ['format' => 'json']), true, flags: JSON_THROW_ON_ERROR);
            assertSame('anonymous', $anonymous['viewer']['status']);
            assertSame(['open_items', 'newest_threads'], array_column($anonymous['sections'], 'key'));
            assertStringContains('Sign-in:', $anonymous['notices'][0]);
        });
    }

    public function testSectionsAreCapped(): void
    {
        $this->withSite(function (Application $app): void {
            [, $text] = $this->get($app, '/api/checkin');
            assertStringContains('Open #issue items: 10', $text);
            assertSame(10, substr_count($this->section($text, 'Open #issue items'), '  Read: '));
        }, extraReplies: 14);
    }

    public function testOpenQueryFollowsTagsets(): void
    {
        $tagsets = [
            ['parent' => 'issue', 'targets' => ['reply', 'root'], 'unlocks' => ['claimed', 'resolved', 'closed', 'open'], 'exclusive' => true],
            ['parent' => 'recipe', 'targets' => ['reply'], 'unlocks' => ['tasty'], 'exclusive' => false],
        ];
        assertSame(['issue'], MarkedPostsQuery::workParents($tagsets));
        assertSame([], MarkedPostsQuery::workParents([]));
        $this->withSite(function (Application $app, LocalWriteService $writer, string $database): void {
            $pdo = new PDO('sqlite:' . $database);
            assertSame([], MarkedPostsQuery::open($pdo, []));
            assertSame([], MarkedPostsQuery::open($pdo, ['issue'], 0));
            $meta = array_column(MarkedPostsQuery::open($pdo, ['meta']), 'post_id');
            assertSame(['reply-meta', 'thread-meta'], $meta);
            $writer->applyPostTag(['post_id' => 'reply-003', 'tag' => 'claimed', 'author_identity_id' => self::APPROVED]);
            $rows = array_column(MarkedPostsQuery::open($pdo, ['ISSUE']), null, 'post_id');
            assertSame('claimed', $rows['reply-003']['state']);
            assertSame('open', $rows['root-001']['state']);
            assertSame(true, $rows['root-001']['is_root']);
        });
    }

    public function testWithoutTagUnlocksThereIsNoOpenSection(): void
    {
        putenv('FORUM_TAG_UNLOCKS_ENABLED=false');
        try {
            $this->withSite(function (Application $app): void {
                [$status, $text] = $this->get($app, '/api/checkin');
                assertSame(200, $status);
                assertStringNotContains('Open #', $text);
                assertStringContains('Newest threads: 3', $text);
            });
        } finally {
            putenv('FORUM_TAG_UNLOCKS_ENABLED');
        }
    }

    public function testFlagOffDisablesTheRoute(): void
    {
        putenv('FORUM_AGENT_CHECKIN_ENABLED=false');
        try {
            $this->withSite(function (Application $app): void {
                [$status, $text] = $this->get($app, '/api/checkin');
                assertSame(404, $status);
                assertSame("error=The agent check-in is disabled on this server.\n", $text);
            });
        } finally {
            putenv('FORUM_AGENT_CHECKIN_ENABLED');
        }
    }

    public function testOnlyGetIsAccepted(): void
    {
        $this->withSite(function (Application $app): void {
            ob_start();
            $app->handle('POST', '/api/checkin');
            ob_end_clean();
            assertSame(405, http_response_code());
        });
    }

    /** @return array{0:int,1:string} */
    private function get(Application $app, string $path): array
    {
        $_GET = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $_GET);
        $_SERVER['HTTP_HOST'] = 'forum.example';
        ob_start();
        $app->handle('GET', $path);
        $body = (string) ob_get_clean();
        unset($_SERVER['HTTP_HOST']);
        return [(int) http_response_code(), $body];
    }

    /** The block of lines from "$title:" to the next blank line. */
    private function section(string $text, string $title): string
    {
        $start = strpos($text, $title . ':');
        if ($start === false) {
            return '';
        }
        $end = strpos($text, "\n\n", $start);
        return substr($text, $start, $end === false ? null : $end - $start);
    }

    /** @param array<string, mixed>|null $viewer @param array<string, string> $query */
    private function checkin(string $database, ?array $viewer, array $query = [], bool $inbox = true): string
    {
        $viewerFn = static fn (): ?array => $viewer;
        $services = new RouteServices(
            $database,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            $viewerFn,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            $viewerFn,
        );
        $inboxFn = $inbox ? fn (): InboxPageController => new InboxPageController($services, $viewerFn, null, fn (): InboxMarkStore => $this->marks) : null;
        $_SERVER['HTTP_HOST'] = 'forum.example';
        ob_start();
        (new AgentCheckinController($services, static fn (array $thread): string => (string) $thread['subject'], 'test-version', true, $viewerFn, $inboxFn))->show('GET', $query);
        unset($_SERVER['HTTP_HOST']);
        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function profile(\PDO $pdo, string $identityId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM profiles WHERE identity_id = ?');
        $stmt->execute([$identityId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function post(\PDO $pdo, string $postId, string $createdAt, string $author, ?string $parentId = null): void
    {
        $threadId = $parentId === null ? $postId : (string) $pdo->query('SELECT thread_id FROM posts WHERE post_id = ' . $pdo->quote($parentId))->fetchColumn();
        $pdo->prepare("INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, author_identity_id, author_label, sequence_number)
            VALUES (?, ?, ?, ?, ?, ?, '[\"general\"]', ?, ?, 0)")
            ->execute([$postId, $createdAt, $threadId, $parentId, $parentId === null ? 'Subject ' . $postId : null, 'Body ' . $postId, $author, substr($author, 8)]);
        if ($parentId === null) {
            $pdo->prepare("INSERT INTO threads (root_post_id, subject, root_post_created_at, last_activity_at, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json)
                VALUES (?, ?, ?, ?, ?, 0, ?, '[\"general\"]', '[]')")->execute([$postId, 'Subject ' . $postId, $createdAt, $createdAt, 'Body ' . $postId, $postId]);
        } else {
            $pdo->prepare('UPDATE threads SET reply_count = reply_count + 1, last_activity_at = ? WHERE root_post_id = ?')->execute([$createdAt, $threadId]);
        }
    }

    /** @param callable(\PDO, string): void $test */
    private function withProfiles(callable $test): void
    {
        $this->marks = new InboxMarkStore(new \PDO('sqlite::memory:'), static fn (): string => '2026-11-07T12:00:00Z');
        $database = sys_get_temp_dir() . '/forum-rewrite-agent-checkin-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $database);
            foreach (\ForumRewrite\ReadModel\ReadModelSchema::statements() as $statement) $pdo->exec($statement);
            foreach (['alice', 'bob', 'ilyag'] as $username) {
                $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                    bootstrap_post_id, bootstrap_thread_id, public_key, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute([
                    'openpgp:' . $username, 'openpgp-' . $username, $username, $username, $username, str_repeat('A', 40),
                    'identity-' . $username, 'thread-' . $username, 'PUBLIC KEY ' . $username,
                ]);
            }
            $test($pdo, $database);
        } finally {
            @unlink($database);
        }
    }

    private function withSite(callable $check, int $extraReplies = 0): void
    {
        $base = sys_get_temp_dir() . '/forum-rewrite-agent-checkin-' . bin2hex(random_bytes(6));
        $root = $base . '/repo';
        $database = $base . '/db/read.sqlite3';
        $artifacts = $base . '/public';
        mkdir($base . '/db', 0777, true);
        mkdir($artifacts, 0777, true);
        try {
            exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . ' ' . escapeshellarg($root));
            $posts = $root . '/records/posts/';
            file_put_contents($posts . 'root-001.txt', str_replace("Board-Tags: general meta\n", "Board-Tags: general issue\n", (string) file_get_contents($posts . 'root-001.txt')));
            $reply = static function (string $id, string $at, string $thread, string $body) use ($posts): void {
                file_put_contents($posts . $id . '.txt', "Post-ID: $id\nCreated-At: $at\nBoard-Tags: general\nThread-ID: $thread\nParent-ID: $thread\n\n$body\n");
            };
            $reply('reply-002', '2026-04-10T12:06:00Z', 'root-001', 'Second reply, claimed.');
            $reply('reply-closed', '2026-04-10T12:06:30Z', 'root-001', 'A reply someone closed.');
            $reply('reply-003', '2026-04-10T12:07:00Z', 'root-001', 'Third reply, still open.');
            for ($i = 1; $i <= $extraReplies; $i++) {
                $reply(sprintf('reply-extra-%02d', $i), sprintf('2026-04-11T00:%02d:00Z', $i), 'root-001', 'Extra reply ' . $i . '.');
            }
            file_put_contents($posts . 'thread-meta.txt', "Post-ID: thread-meta\nCreated-At: 2026-06-01T00:00:00Z\nBoard-Tags: meta\nSubject: A meta thread\n\nMeta talk.\n");
            $reply('reply-meta', '2026-06-01T00:01:00Z', 'thread-meta', 'A reply in a #meta thread, which holds no work states.');
            exec('cd ' . escapeshellarg($root) . ' && git init -q && git config user.name t && git config user.email t@example.invalid && git add . && git commit -qm init 2>&1');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            $writer = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root));
            $app = new Application(dirname(__DIR__), $root, $database, $artifacts);
            $check($app, $writer, $database);
        } finally {
            exec('rm -rf ' . escapeshellarg($base));
        }
    }
}
