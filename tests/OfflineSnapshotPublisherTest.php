<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\OfflineSnapshotPublisher;

final class OfflineSnapshotPublisherTest
{
    public function testPublishesSnapshotOutsideStaticRelease(): void
    {
        [$root, $source] = $this->createPaths();
        try {
            $this->createSource($source);
            $result = (new OfflineSnapshotPublisher($root))->publish($source);

            assertSame($root . '/offline/snapshot.sqlite3', $result['path']);
            assertSame(1, $result['thread_count']);
            assertTrue(is_file($result['path']));
            assertSame("SQLite format 3\000", file_get_contents($result['path'], false, null, 0, 16));
        } finally {
            $this->removeTree($root);
            @unlink($source);
        }
    }

    public function testFailureRetainsPreviousSnapshot(): void
    {
        [$root, $source] = $this->createPaths();
        $target = $root . '/offline/snapshot.sqlite3';
        try {
            mkdir(dirname($target), 0700, true);
            file_put_contents($target, "SQLite format 3\000previous");

            try {
                (new OfflineSnapshotPublisher($root))->publish($source);
                throw new RuntimeException('Expected publish to fail for missing source.');
            } catch (RuntimeException $exception) {
                assertStringContains('source database does not exist', $exception->getMessage());
            }

            assertSame("SQLite format 3\000previous", file_get_contents($target));
        } finally {
            $this->removeTree($root);
        }
    }

    /** @return array{0:string,1:string} */
    private function createPaths(): array
    {
        $suffix = bin2hex(random_bytes(6));

        return [
            sys_get_temp_dir() . '/forum-offline-publish-' . $suffix,
            sys_get_temp_dir() . '/forum-offline-publish-source-' . $suffix . '.sqlite3',
        ];
    }

    private function createSource(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT, subject TEXT, body_preview TEXT, board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER)');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER)');
        $pdo->exec("INSERT INTO threads VALUES ('root', '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z', 'Subject', 'Preview', '[]', '[]', 0)");
        $pdo->exec("INSERT INTO posts VALUES ('root', '2026-01-01T00:00:00Z', 'root', NULL, 'Subject', 'Body', '[]', NULL, 'Author', NULL, 1, 0)");
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->removeTree($entryPath) : @unlink($entryPath);
        }
        @rmdir($path);
    }
}
