<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Write\LocalWriteService;

/** Tags unlocked by a thread tag (resources/tagsets.txt): resolved/closed/open on #meta replies. */
final class TagUnlocksTest
{
    private const IDENTITY = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const APPROVED = self::IDENTITY;

    public function testStateTagsApplyOnlyToRepliesInMetaThreads(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root): void {
            $result = $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'Resolved', 'author_identity_id' => self::APPROVED]);
            assertSame('yes', $result['wrote_record']);
            assertSame('resolved', $result['tag']);

            foreach (['reply-rules' => 'replies in threads tagged #meta', 'root-001' => 'replies in threads tagged #meta'] as $postId => $message) {
                $error = '';
                try {
                    $writer->applyPostTag(['post_id' => $postId, 'tag' => 'closed', 'author_identity_id' => self::APPROVED]);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
                assertStringContains($message, $error);
            }

            $error = '';
            try {
                $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'banana', 'author_identity_id' => self::APPROVED]);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
            assertStringContains('tag must be one of', $error);
        });
    }

    public function testStateTagsCanBeGivenAgainAndNeverScore(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root, string $database): void {
            $before = $this->postScore($database, 'reply-001');
            foreach (['resolved', 'open', 'resolved'] as $tag) {
                assertSame('yes', $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => $tag, 'author_identity_id' => self::APPROVED])['wrote_record']);
            }
            assertSame(3, count(glob($root . '/records/post-reactions/*.txt') ?: []));
            assertSame($before, $this->postScore($database, 'reply-001'));

            // Ordinary tags keep their one-per-key rule.
            assertSame('yes', $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'like', 'author_identity_id' => self::APPROVED])['wrote_record']);
            assertSame('no', $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'like', 'author_identity_id' => self::APPROVED])['wrote_record']);
        });
    }

    public function testReplayedOfflineIntentWritesOnce(): void
    {
        $this->withWriter(function (LocalWriteService $writer): void {
            $input = ['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED,
                'intent_id' => 'intent-resolve-1', 'action_at' => '2026-11-12T10:00:00Z'];
            assertSame('yes', $writer->applyPostTag($input)['wrote_record']);
            assertSame('no', $writer->applyPostTag($input)['wrote_record']);
            assertSame('yes', $writer->applyPostTag(array_merge($input, ['intent_id' => 'intent-resolve-2']))['wrote_record']);
        });
    }

    public function testUnapprovedKeysMayMarkAndAreToldSo(): void
    {
        $this->withWriter(function (LocalWriteService $writer): void {
            $result = $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'closed', 'author_identity_id' => self::IDENTITY]);
            assertSame('yes', $result['wrote_record']);
            assertSame('no', $result['viewer_is_approved']);
        }, approved: false);
    }

    public function testFlagOffTreatsStateTagsAsUnknown(): void
    {
        putenv('FORUM_TAG_UNLOCKS_ENABLED=false');
        try {
            $this->withWriter(function (LocalWriteService $writer): void {
                $error = '';
                try {
                    $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED]);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
                assertStringContains('tag must be one of', $error);
            });
        } finally {
            putenv('FORUM_TAG_UNLOCKS_ENABLED');
        }
    }

    public function testReadModelKeepsLatestApprovedMarkAcrossIncrementalAndRebuild(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root, string $database): void {
            assertSame([null, null], $this->resolution($database, 'reply-001'));
            $mark = fn (string $tag, string $at) => $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => $tag,
                'author_identity_id' => self::APPROVED, 'action_at' => $at, 'intent_id' => 'i-' . $tag . '-' . md5($at)]);
            $mark('resolved', '2026-11-12T10:00:00Z');
            assertSame(['resolved', '2026-11-12T10:00:00Z'], $this->resolution($database, 'reply-001'));
            $mark('closed', '2026-11-12T11:00:00Z');
            assertSame(['closed', '2026-11-12T11:00:00Z'], $this->resolution($database, 'reply-001'));
            // A late offline send of an earlier tap does not override the later mark.
            $mark('open', '2026-11-12T09:00:00Z');
            assertSame(['closed', '2026-11-12T11:00:00Z'], $this->resolution($database, 'reply-001'));
            $mark('open', '2026-11-12T12:00:00Z');
            assertSame([null, '2026-11-12T12:00:00Z'], $this->resolution($database, 'reply-001'));
            $mark('resolved', '2026-11-12T13:00:00Z');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            assertSame(['resolved', '2026-11-12T13:00:00Z'], $this->resolution($database, 'reply-001'));
        });
    }

    public function testRemovingTheLatestMarkFallsBackToTheOneBefore(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root, string $database): void {
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED, 'action_at' => '2026-11-12T10:00:00Z']);
            $before = glob($root . '/records/post-reactions/*.txt') ?: [];
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'closed', 'author_identity_id' => self::APPROVED, 'action_at' => '2026-11-12T11:00:00Z']);
            foreach (array_diff(glob($root . '/records/post-reactions/*.txt') ?: [], $before) as $latest) {
                unlink($latest);
            }
            exec('cd ' . escapeshellarg($root) . ' && git add -A && git commit -qm retract');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            assertSame('resolved', $this->resolution($database, 'reply-001')[0]);
        });
    }

    public function testUnapprovedMarksCountOnceTheKeyIsApproved(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root, string $database): void {
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::IDENTITY]);
            assertSame([null, null], $this->resolution($database, 'reply-001'));
            exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1/records/approval-seeds') . ' ' . escapeshellarg($root . '/records/')
                . ' && cd ' . escapeshellarg($root) . ' && git add -A && git commit -qm approve');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            assertSame('resolved', $this->resolution($database, 'reply-001')[0]);
        }, approved: false);
    }

    public function testTextApiReportsResolution(): void
    {
        $this->withWriter(function (LocalWriteService $writer, string $root, string $database): void {
            $app = new Application(dirname(__DIR__), $root, $database, dirname($database, 2) . '/public');
            assertStringNotContains('Resolution:', $this->render($app, '/api/get_post?post_id=reply-001'));
            $writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'resolved', 'author_identity_id' => self::APPROVED]);
            assertStringContains("Body-Format: plain\nResolution: resolved\n\nReply body.", $this->render($app, '/api/get_post?post_id=reply-001'));
            assertStringContains('[reply-001] (resolved) Reply body.', $this->render($app, '/api/get_thread?thread_id=root-001'));
        });
    }

    private function render(Application $app, string $path): string
    {
        $_GET = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $_GET);
        ob_start();
        $app->handle('GET', $path);
        return (string) ob_get_clean();
    }

    /** @return array{0:?string,1:?string} state and set-at */
    private function resolution(string $database, string $postId): array
    {
        $pdo = new PDO('sqlite:' . $database);
        $stmt = $pdo->prepare('SELECT resolution_state, resolution_set_at FROM posts WHERE post_id = ?');
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return [$row[0], $row[1]];
    }

    private function postScore(string $database, string $postId): int
    {
        $pdo = new PDO('sqlite:' . $database);
        $stmt = $pdo->prepare('SELECT post_score_total FROM posts WHERE post_id = ?');
        $stmt->execute([$postId]);
        return (int) $stmt->fetchColumn();
    }

    private function withWriter(callable $check, bool $approved = true): void
    {
        $base = sys_get_temp_dir() . '/forum-rewrite-tag-unlocks-' . bin2hex(random_bytes(6));
        $root = $base . '/repo';
        $database = $base . '/db/read.sqlite3';
        $artifacts = $base . '/public';
        mkdir($base . '/db', 0777, true);
        mkdir($artifacts, 0777, true);
        try {
            exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . ' ' . escapeshellarg($root));
            file_put_contents($root . '/records/posts/reply-rules.txt',
                "Post-ID: reply-rules\nCreated-At: 2026-05-30T00:05:00Z\nBoard-Tags: general\nThread-ID: thread-zenmemes-rules\nParent-ID: thread-zenmemes-rules\n\nA reply outside #meta.\n");
            if (!$approved) {
                exec('rm -rf ' . escapeshellarg($root . '/records/approval-seeds'));
            }
            exec('cd ' . escapeshellarg($root) . ' && git init -q && git config user.name t && git config user.email t@example.invalid && git add . && git commit -qm init 2>&1');
            (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
            $check(new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root)), $root, $database);
        } finally {
            exec('rm -rf ' . escapeshellarg($base));
        }
    }
}
