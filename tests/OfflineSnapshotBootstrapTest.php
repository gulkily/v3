<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\OfflineSnapshotBootstrap;
use ForumRewrite\Offline\OfflineSnapshotLocator;

final class OfflineSnapshotBootstrapTest
{
    public function testPublishesWhenNoSnapshotIsServed(): void
    {
        [$root, $databasePath] = $this->createEnvironment();
        try {
            $result = (new OfflineSnapshotBootstrap($root))->ensure($databasePath, true);

            assertSame('published', $result['status']);
            assertSame($root . '/offline/snapshot.sqlite3', $result['path']);
            assertSame($result['path'], (new OfflineSnapshotLocator())->servedSnapshotPath($root));
        } finally {
            $this->removeTree(dirname($root));
        }
    }

    public function testRetainsAnExistingServedSnapshot(): void
    {
        [$root, $databasePath] = $this->createEnvironment();
        try {
            $bootstrap = new OfflineSnapshotBootstrap($root);
            $published = $bootstrap->ensure($databasePath, true);
            file_put_contents((string) $published['path'], "SQLite format 3\000existing");

            $result = $bootstrap->ensure('/missing/read-model.sqlite3', true);

            assertSame('already_available', $result['status']);
            assertSame($published['path'], $result['path']);
            assertSame("SQLite format 3\000existing", file_get_contents((string) $published['path']));
        } finally {
            $this->removeTree(dirname($root));
        }
    }

    public function testSkipsPublicationWhenPublicSnapshotsAreUnavailable(): void
    {
        [$root, $databasePath] = $this->createEnvironment();
        try {
            $result = (new OfflineSnapshotBootstrap($root))->ensure($databasePath, false);

            assertSame('unavailable', $result['status']);
            assertSame(null, $result['path']);
            assertSame(null, (new OfflineSnapshotLocator())->servedSnapshotPath($root));
        } finally {
            $this->removeTree(dirname($root));
        }
    }

    /** @return array{string,string} */
    private function createEnvironment(): array
    {
        $base = sys_get_temp_dir() . '/forum-offline-bootstrap-' . bin2hex(random_bytes(6));
        $root = $base . '/static_html';
        $databasePath = $base . '/read-model.sqlite3';
        mkdir($base, 0700, true);
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT, subject TEXT, body_preview TEXT, board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER)');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER)');
        $pdo->exec("INSERT INTO threads VALUES ('root', '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z', 'Subject', 'Preview', '[]', '[]', 0)");
        $pdo->exec("INSERT INTO posts VALUES ('root', '2026-01-01T00:00:00Z', 'root', NULL, 'Subject', 'Body', '[]', NULL, 'Author', NULL, 1, 0)");

        return [$root, $databasePath];
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
