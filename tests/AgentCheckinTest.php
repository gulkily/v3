<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Tools\MarkedPostsQuery;
use ForumRewrite\Write\LocalWriteService;

/** GET /api/checkin (agent_checkin): what needs an agent's attention, each section ending with the next request. */
final class AgentCheckinTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testPublicSectionsListOpenIssueItemsAndNewestThreads(): void
    {
        $this->withSite(function (Application $app, LocalWriteService $writer): void {
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED]);
            $writer->applyPostTag(['post_id' => 'reply-002', 'tag' => 'claimed', 'author_identity_id' => self::APPROVED]);
            $writer->applyPostTag(['post_id' => 'reply-closed', 'tag' => 'closed', 'author_identity_id' => self::APPROVED]);
            [$status, $text] = $this->get($app, '/api/checkin');
            assertSame(200, $status);
            assertStringContains("Check-in: ", $text);
            assertStringContains("Viewer: anonymous\n", $text);

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
