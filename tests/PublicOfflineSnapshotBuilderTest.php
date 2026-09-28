<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;

final class PublicOfflineSnapshotBuilderTest
{
    public function testBuildsBoundedPublicSnapshotWithoutOperationalTables(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $sourcePath = sys_get_temp_dir() . '/forum-offline-source-' . $suffix . '.sqlite3';
        $targetPath = sys_get_temp_dir() . '/forum-offline-target-' . $suffix . '.sqlite3';

        try {
            $this->createSource($sourcePath);
            $result = (new PublicOfflineSnapshotBuilder())->build($sourcePath, $targetPath);
            $snapshot = new PDO('sqlite:' . $targetPath);

            assertSame(51, $result['thread_count']);
            assertSame('51', (string) $snapshot->query('SELECT COUNT(*) FROM threads')->fetchColumn());
            assertSame('102', (string) $snapshot->query('SELECT COUNT(*) FROM posts')->fetchColumn());
            assertSame('thread-055', (string) $snapshot->query('SELECT root_post_id FROM threads ORDER BY last_activity_at DESC LIMIT 1')->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'thread-001'")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'thread-005'")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id = 'reply-hidden'")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id IN ('thread-hidden', 'thread-bootstrap')")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'post_analyses'")->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM metadata WHERE key = 'snapshot_version' AND value = '2'")->fetchColumn());
            assertTrue($result['size_bytes'] <= 10485760);
        } finally {
            @unlink($sourcePath);
            @unlink($targetPath);
        }
    }

    private function createSource(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE threads (
            root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT,
            subject TEXT, body_preview TEXT, reply_count INTEGER, last_post_id TEXT,
            board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER
        )');
        $pdo->exec('CREATE TABLE posts (
            post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT,
            subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT,
            author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER
        )');
        $pdo->exec('CREATE TABLE post_analyses (post_id TEXT, raw_response_json TEXT)');
        $pdo->exec("INSERT INTO post_analyses VALUES ('thread-055', '{\"private\":true}')");

        $thread = $pdo->prepare('INSERT INTO threads VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $post = $pdo->prepare('INSERT INTO posts VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($number = 1; $number <= 55; $number++) {
            $id = sprintf('thread-%03d', $number);
            $timestamp = sprintf('2026-02-%02dT00:00:00Z', $number);
            $labels = $number === 1 ? '["pinned"]' : '[]';
            $thread->execute([$id, $timestamp, $timestamp, 'Subject ' . $number, 'Preview ' . $number, 1, 'reply-' . $number, '["general"]', $labels, 0]);
            $post->execute([$id, $timestamp, $id, null, 'Subject ' . $number, 'Root ' . $number, '["general"]', null, 'Author ' . $number, 'author-' . $number, $number * 2, 0]);
            $post->execute(['reply-' . $number, $timestamp, $id, $id, null, 'Reply ' . $number, '["general"]', null, 'Reply author ' . $number, 'reply-author-' . $number, $number * 2 + 1, 0]);
        }
        $post->execute(['reply-hidden', '2026-03-01T00:00:00Z', 'thread-055', 'thread-055', null, 'Hidden reply', '["general"]', null, 'Hidden author', null, 9998, 1]);
        $thread->execute(['thread-hidden', '2027-01-01T00:00:00Z', '2027-01-01T00:00:00Z', 'Hidden', '', 0, 'thread-hidden', '["general"]', '[]', 0]);
        $post->execute(['thread-hidden', '2027-01-01T00:00:00Z', 'thread-hidden', null, 'Hidden', '', '["general"]', null, 'Hidden', null, 999, 1]);
        $thread->execute(['thread-bootstrap', '2027-01-02T00:00:00Z', '2027-01-02T00:00:00Z', 'Bootstrap', '', 0, 'thread-bootstrap', '["identity"]', '[]', 0]);
        $post->execute(['thread-bootstrap', '2027-01-02T00:00:00Z', 'thread-bootstrap', null, 'Bootstrap', '', '["identity"]', null, 'Bootstrap', null, 1000, 0]);
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}
