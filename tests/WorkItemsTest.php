<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Work\WorkItems;
use ForumRewrite\Work\WorkStatusDirective;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}
if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new RuntimeException($message !== '' ? $message : 'Expected true.');
        }
    }
}

final class WorkItemsTest
{
    public function testThreadPageAndWorkTxt(): void
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-work-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($source as $item) {
            $target = $root . '/' . $source->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
        file_put_contents($root . '/records/posts/work-item-1.txt', "Post-ID: work-item-1\nCreated-At: 2026-11-03T00:00:00Z\nBoard-Tags: work\nSubject: Fix duplicate nav\n\nNav fix.\n");
        file_put_contents($root . '/records/posts/plain-1.txt', "Post-ID: plain-1\nCreated-At: 2026-11-03T00:00:00Z\nBoard-Tags: general\nSubject: Plain\n\nPlain.\n");
        exec('cd ' . escapeshellarg($root) . ' && git init -q && git -c user.name=t -c user.email=t@example.invalid add . && git -c user.name=t -c user.email=t@example.invalid commit -qm init 2>&1');
        $app = new \ForumRewrite\Application(dirname(__DIR__), $root, sys_get_temp_dir() . '/forum-rewrite-work-db-' . bin2hex(random_bytes(6)) . '.sqlite3');
        ob_start();
        $app->handle('GET', '/threads/work-item-1');
        $work = (string) ob_get_clean();
        ob_start();
        $app->handle('GET', '/threads/plain-1');
        $plain = (string) ob_get_clean();
        assertTrue(str_contains($work, 'data-work-status="open"'), 'work thread shows its status');
        assertTrue(!str_contains($plain, 'data-work-status'), 'other threads show nothing');
        ob_start();
        $app->handle('GET', '/work.txt?q=nav&status=open');
        $text = (string) ob_get_clean();
        assertSame(\ForumRewrite\Work\WorkListText::TEXT_HEADER . "\nopen\twork-item-1\tFix duplicate nav\t\t\t/threads/work-item-1\n", $text);
        ob_start();
        $app->handle('GET', '/work.txt?status=done');
        assertSame(\ForumRewrite\Work\WorkListText::TEXT_HEADER . "\n# no matching work items\n", (string) ob_get_clean());
    }

    private PDO $pdo;
    private int $sequence = 0;

    private function database(): PDO
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (ReadModelSchema::statements() as $statement) {
            $this->pdo->exec($statement);
        }
        $profile = $this->pdo->prepare("INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved) VALUES (?, ?, ?, ?, ?, 'fp', 'b', 'b', 'k', ?)");
        $profile->execute(['id-approved', 'approved', 'alice', 'alice', 'alice', 1]);
        $profile->execute(['id-claude', 'claude', 'claude', 'claude', 'claude', 1]);
        $profile->execute(['id-pending', 'pending', 'pat', 'pat', 'pat', 0]);
        $this->thread('item-1', 'Collapsible navigation', '["work"]', '2026-11-01T00:00:00Z');
        $this->thread('item-2', 'Login separate from registration', '["general","work"]', '2026-11-02T00:00:00Z');
        $this->thread('plain', 'Not a work item', '["general"]', '2026-11-02T00:00:00Z');

        return $this->pdo;
    }

    private function thread(string $id, string $subject, string $tags, string $lastActivity): void
    {
        $this->pdo->prepare("INSERT INTO threads (root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json) VALUES (?, '2026-10-01T00:00:00Z', ?, ?, ?, 0, ?, ?, '[]')")
            ->execute([$id, $lastActivity, $subject, 'Preview of ' . $subject, $id, $tags]);
        $this->post($id, $id, null, 'Root body', '2026-10-01T00:00:00Z');
    }

    private function post(string $id, string $thread, ?string $identity, string $body, string $createdAt, int $verified = 1, ?string $retracted = null): void
    {
        $this->pdo->prepare('INSERT INTO posts (post_id, thread_id, created_at, body, board_tags_json, sequence_number, author_identity_id, attribution_verified, retracted_at) VALUES (?, ?, ?, ?, \'[]\', ?, ?, ?, ?)')
            ->execute([$id, $thread, $createdAt, $body, ++$this->sequence, $identity, $verified, $retracted]);
    }

    public function testDirectiveParsing(): void
    {
        assertSame(['status' => 'active', 'note' => 'session abc on feat/x'], WorkStatusDirective::extract("Taking this.\nstatus: Active - session abc on feat/x"));
        assertSame(['status' => 'done', 'note' => ''], WorkStatusDirective::extract('status: done'));
        assertSame(null, WorkStatusDirective::extract('> status: done'));
        assertSame(null, WorkStatusDirective::extract('status: finished'));
        assertSame(['status' => 'dropped', 'note' => 'dup'], WorkStatusDirective::extract("status: maybe\nstatus: dropped: dup"));
        assertSame(null, WorkStatusDirective::extract('the status: active line must start the line'));
    }

    public function testLatestValidStatusWins(): void
    {
        $pdo = $this->database();
        $now = strtotime('2026-11-03T00:00:00Z');
        assertSame('open', WorkItems::item($pdo, 'item-1', $now)['status'], 'no directive means open');
        $this->post('r1', 'item-1', 'id-claude', 'status: active - claude session 1', '2026-11-01T01:00:00Z');
        $this->post('r2', 'item-1', 'id-pending', 'status: dropped', '2026-11-01T02:00:00Z');
        $this->post('r3', 'item-1', null, 'status: dropped', '2026-11-01T03:00:00Z');
        $this->post('r4', 'item-1', 'id-approved', 'status: dropped', '2026-11-01T04:00:00Z', 0);
        $this->post('r5', 'item-1', 'id-approved', 'status: dropped', '2026-11-01T05:00:00Z', 1, '2026-11-01T06:00:00Z');
        $item = WorkItems::item($pdo, 'item-1', $now);
        assertSame('active', $item['status'], 'unapproved, guest, unverified and retracted lines are ignored');
        assertSame('claude', $item['set_by']);
        assertSame('claude session 1', $item['note']);
        $this->post('r6', 'item-1', 'id-approved', "Fixed in abc123.\nstatus: done", '2026-11-02T00:00:00Z');
        assertSame('done', WorkItems::item($pdo, 'item-1', $now)['status']);
        assertSame(null, WorkItems::item($pdo, 'plain', $now), 'untagged threads are not items');
    }

    public function testTextCellsStayOnOneLine(): void
    {
        $text = \ForumRewrite\Work\WorkListText::text([['status' => 'active', 'stale' => true, 'thread_id' => 't1', 'title' => "Two\tparts\nhere", 'set_by' => 'claude', 'note' => 'n']]);
        assertSame("active (stale)\tt1\tTwo parts here\tclaude\tn\t/threads/t1", explode("\n", $text)[1]);
    }

    public function testStalenessListingAndFilters(): void
    {
        $pdo = $this->database();
        $this->post('c1', 'item-2', 'id-claude', 'status: active', '2026-11-02T00:00:00Z');
        $soon = strtotime('2026-11-04T00:00:00Z');
        $later = strtotime('2026-11-20T00:00:00Z');
        assertSame(false, WorkItems::item($pdo, 'item-2', $soon)['stale']);
        assertSame(true, WorkItems::item($pdo, 'item-2', $later)['stale']);
        assertSame(['item-1', 'item-2'], array_column(WorkItems::list($pdo, [], null, $soon), 'thread_id'), 'stable thread-ID order');
        assertSame(['item-2'], array_column(WorkItems::list($pdo, ['LOGIN'], null, $soon), 'thread_id'));
        assertSame([], WorkItems::list($pdo, ['login', 'nav'], null, $soon), 'all words must match');
        assertSame(['item-1'], array_column(WorkItems::list($pdo, [], 'open', $soon), 'thread_id'));
        assertSame(['item-2'], array_column(WorkItems::list($pdo, [], 'stale', $later), 'thread_id'));
        $pdo->exec("UPDATE threads SET retracted_at = '2026-11-03T00:00:00Z' WHERE root_post_id = 'item-1'");
        assertSame(['item-2'], array_column(WorkItems::list($pdo, [], null, $soon), 'thread_id'), 'retracted items disappear');
    }
}
