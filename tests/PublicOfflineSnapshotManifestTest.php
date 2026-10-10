<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;

final class PublicOfflineSnapshotManifestTest
{
    public function testManifestHashMatchesPublishedSnapshotAndChangesWithData(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $directory = sys_get_temp_dir() . '/forum-offline-manifest-' . $suffix;
        $sourcePath = $directory . '/source.sqlite3';
        $targetPath = $directory . '/offline/snapshot.sqlite3';
        $manifestPath = $directory . '/offline/manifest.json';

        try {
            $this->createSource($sourcePath, 1);
            $builder = new PublicOfflineSnapshotBuilder();

            $builder->build($sourcePath, $targetPath);
            $first = $this->readManifest($manifestPath);
            assertSame(hash_file('sha256', $targetPath), $first['sha256']);
            assertSame(filesize($targetPath), $first['size_bytes']);
            assertSame(PublicOfflineSnapshotBuilder::SNAPSHOT_VERSION, $first['snapshot_version']);

            $this->createSource($sourcePath, 2);
            $builder->build($sourcePath, $targetPath);
            $second = $this->readManifest($manifestPath);
            assertSame(hash_file('sha256', $targetPath), $second['sha256']);
            assertTrue($first['sha256'] !== $second['sha256'], 'Manifest hash should change when the snapshot changes.');
        } finally {
            @unlink($manifestPath);
            @unlink($targetPath);
            @unlink($sourcePath);
            @rmdir($directory . '/offline');
            @rmdir($directory);
        }
    }

    /** @return array<string, mixed> */
    private function readManifest(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        assertTrue(is_array($decoded), 'Manifest should decode to an object.');

        return $decoded;
    }

    private function createSource(string $path, int $threadCount): void
    {
        @mkdir(dirname($path), 0777, true);
        @unlink($path);
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
            author_identity_id TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER
        )');
        $pdo->exec('CREATE TABLE profiles (identity_id TEXT PRIMARY KEY, signer_fingerprint TEXT, public_key TEXT, is_approved INTEGER)');
        $thread = $pdo->prepare('INSERT INTO threads VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $post = $pdo->prepare('INSERT INTO posts VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($number = 1; $number <= $threadCount; $number++) {
            $id = sprintf('thread-%03d', $number);
            $time = sprintf('2026-01-%02dT00:00:00Z', $number);
            $thread->execute([$id, $time, $time, 'Subject ' . $number, 'Preview', 0, $id, '[]', '[]', 0]);
            $post->execute([$id, $time, $id, null, 'Subject ' . $number, 'Body ' . $number, '[]', 'thread', null, 'Anon', null, 1, 0]);
        }
    }
}
