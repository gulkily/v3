<?php

declare(strict_types=1);

namespace ForumRewrite\Offline;

use ForumRewrite\ReadModel\ThreadRowSupport;
use PDO;
use RuntimeException;

/**
 * Produces the deliberately small, public-only SQLite database used by the
 * offline reader. It never copies the application's full read model.
 */
final class PublicOfflineSnapshotBuilder
{
    public const SNAPSHOT_VERSION = '3';
    public const DEFAULT_THREAD_LIMIT = 200;
    public const DEFAULT_MAX_BYTES = 100 * 1024 * 1024;

    /**
     * @return array{generated_at:string,thread_count:int,post_count:int,public_key_count:int,size_bytes:int}
     */
    public function build(
        string $sourcePath,
        string $targetPath,
        int $threadLimit = self::DEFAULT_THREAD_LIMIT,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
    ): array {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('Offline snapshot source database does not exist.');
        }
        if ($threadLimit < 1 || $maxBytes < 4096) {
            throw new RuntimeException('Offline snapshot limits must be positive and support at least one SQLite page.');
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Unable to create offline snapshot directory.');
        }

        $temporaryPath = $targetPath . '.tmp-' . bin2hex(random_bytes(8));
        $source = new PDO('sqlite:' . $sourcePath);
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $snapshot = new PDO('sqlite:' . $temporaryPath);
        $snapshot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $this->createSchema($snapshot, $maxBytes);
            $generatedAt = gmdate('Y-m-d\TH:i:s\Z');
            $snapshotThreads = $this->snapshotThreadRows($source, $threadLimit);
            $this->writeMetadata($snapshot, [
                'snapshot_version' => self::SNAPSHOT_VERSION,
                'generated_at' => $generatedAt,
                'thread_limit' => (string) $threadLimit,
                'max_bytes' => (string) $maxBytes,
            ]);

            $threadCount = 0;
            $postCount = 0;
            $selectedAuthorIdentityIds = [];
            foreach ($snapshotThreads as $thread) {

                $posts = $this->visiblePostsForThread($source, (string) $thread['root_post_id']);
                if ($posts === [] || (string) $posts[0]['post_id'] !== (string) $thread['root_post_id']) {
                    continue;
                }

                try {
                    $snapshot->beginTransaction();
                    $this->insertThread($snapshot, $thread, $posts);
                    foreach ($posts as $post) {
                        $this->insertPost($snapshot, $post);
                        if (($post['author_identity_id'] ?? null) !== null) {
                            $selectedAuthorIdentityIds[(string) $post['author_identity_id']] = true;
                        }
                    }
                    $snapshot->commit();
                } catch (\Throwable $throwable) {
                    if ($snapshot->inTransaction()) {
                        $snapshot->rollBack();
                    }
                    if ($this->isSizeLimitFailure($throwable)) {
                        if ($this->isPinned($thread)) {
                            throw new RuntimeException('Pinned public threads exceed the offline snapshot size limit.', 0, $throwable);
                        }
                        break;
                    }

                    throw $throwable;
                }

                $threadCount++;
                $postCount += count($posts);
            }

            $publicKeyCount = $this->insertPublicKeys($snapshot, $source, array_keys($selectedAuthorIdentityIds));

            $this->writeMetadata($snapshot, [
                'thread_count' => (string) $threadCount,
                'post_count' => (string) $postCount,
                'public_key_count' => (string) $publicKeyCount,
            ]);
            $snapshot = null;

            $size = filesize($temporaryPath);
            if ($size === false || $size > $maxBytes) {
                throw new RuntimeException('Offline snapshot exceeded its configured size limit.');
            }
            if (!rename($temporaryPath, $targetPath)) {
                throw new RuntimeException('Unable to publish offline snapshot.');
            }
            $this->writeManifest($targetPath, $generatedAt, $size);

            return [
                'generated_at' => $generatedAt,
                'thread_count' => $threadCount,
                'post_count' => $postCount,
                'public_key_count' => $publicKeyCount,
                'size_bytes' => $size,
            ];
        } finally {
            $snapshot = null;
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function createSchema(PDO $pdo, int $maxBytes): void
    {
        $pdo->exec('PRAGMA page_size = 4096');
        $pdo->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE threads (
            root_post_id TEXT PRIMARY KEY,
            root_post_created_at TEXT NOT NULL,
            last_activity_at TEXT NOT NULL,
            subject TEXT NULL,
            body_preview TEXT NOT NULL,
            reply_count INTEGER NOT NULL,
            last_post_id TEXT NOT NULL,
            board_tags_json TEXT NOT NULL,
            thread_labels_json TEXT NOT NULL,
            score_total INTEGER NOT NULL,
            author_label TEXT NOT NULL,
            author_profile_slug TEXT NULL
        )');
        $pdo->exec('CREATE TABLE posts (
            post_id TEXT PRIMARY KEY,
            created_at TEXT NOT NULL,
            thread_id TEXT NOT NULL,
            parent_id TEXT NULL,
            subject TEXT NULL,
            body TEXT NOT NULL,
            board_tags_json TEXT NOT NULL,
            thread_type TEXT NULL,
            author_label TEXT NOT NULL,
            author_profile_slug TEXT NULL,
            sequence_number INTEGER NOT NULL
        )');
        $pdo->exec('CREATE TABLE public_keys (
            identity_id TEXT PRIMARY KEY,
            signer_fingerprint TEXT NOT NULL,
            armored_key TEXT NOT NULL,
            is_approved INTEGER NOT NULL
        )');
        $pdo->exec('CREATE INDEX posts_thread_sequence_idx ON posts (thread_id, sequence_number, post_id)');
        $pdo->exec('PRAGMA max_page_count = ' . intdiv($maxBytes, 4096));
    }

    /**
     * Written only after the snapshot is in place, so the hash always
     * describes the published file. Replaced atomically like the snapshot.
     */
    private function writeManifest(string $snapshotPath, string $generatedAt, int $size): void
    {
        $manifestPath = dirname($snapshotPath) . '/manifest.json';
        $temporaryPath = $manifestPath . '.tmp-' . bin2hex(random_bytes(8));
        $manifest = json_encode([
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'generated_at' => $generatedAt,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $snapshotPath),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        if (file_put_contents($temporaryPath, $manifest) === false || !rename($temporaryPath, $manifestPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to publish offline snapshot manifest.');
        }
    }

    /** @param array<string, string> $metadata */
    private function writeMetadata(PDO $pdo, array $metadata): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO metadata (key, value) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        foreach ($metadata as $key => $value) {
            $statement->execute(['key' => $key, 'value' => $value]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function visibleThreadRows(PDO $source): array
    {
        $rows = $source->query(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at,
                    threads.subject, threads.body_preview, threads.board_tags_json,
                    threads.thread_labels_json, threads.score_total, posts.author_label,
                    posts.author_profile_slug
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             WHERE posts.is_hidden = 0
             ORDER BY threads.last_activity_at DESC, threads.root_post_id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])
        ));
    }

    /**
     * Keeps every visible pinned thread, then fills the remaining reading set
     * with the requested number of newest non-pinned threads. Pinned content
     * is selected first so the size cap cannot discard it in favor of a newer
     * ordinary thread.
     *
     * @return list<array<string, mixed>>
     */
    private function snapshotThreadRows(PDO $source, int $recentThreadLimit): array
    {
        $pinned = [];
        $recent = [];
        foreach ($this->visibleThreadRows($source) as $row) {
            if ($this->isPinned($row)) {
                $pinned[] = $row;
                continue;
            }

            if (count($recent) < $recentThreadLimit) {
                $recent[] = $row;
            }
        }

        return [...$pinned, ...$recent];
    }

    /** @param array<string, mixed> $thread */
    private function isPinned(array $thread): bool
    {
        $labels = json_decode((string) ($thread['thread_labels_json'] ?? '[]'), true);

        return is_array($labels) && in_array('pinned', $labels, true);
    }

    /** @return list<array<string, mixed>> */
    private function visiblePostsForThread(PDO $source, string $threadId): array
    {
        $statement = $source->prepare(
            'SELECT post_id, created_at, thread_id, parent_id, subject, body, board_tags_json,
                    thread_type, author_identity_id, author_label, author_profile_slug, sequence_number
             FROM posts
             WHERE thread_id = :thread_id AND is_hidden = 0
             ORDER BY sequence_number ASC, post_id ASC'
        );
        $statement->execute(['thread_id' => $threadId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])
        ));
    }

    /**
     * @param list<string> $selectedAuthorIdentityIds
     */
    private function insertPublicKeys(PDO $snapshot, PDO $source, array $selectedAuthorIdentityIds): int
    {
        $placeholders = implode(', ', array_fill(0, count($selectedAuthorIdentityIds), '?'));
        $where = 'is_approved = 1';
        if ($placeholders !== '') {
            $where .= ' OR identity_id IN (' . $placeholders . ')';
        }

        $statement = $source->prepare(
            'SELECT identity_id, signer_fingerprint, public_key, is_approved
             FROM profiles
             WHERE ' . $where . '
             ORDER BY identity_id ASC'
        );
        $statement->execute($selectedAuthorIdentityIds);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $insert = $snapshot->prepare(
            'INSERT INTO public_keys (identity_id, signer_fingerprint, armored_key, is_approved)
             VALUES (:identity_id, :signer_fingerprint, :armored_key, :is_approved)'
        );
        foreach ($rows as $row) {
            $insert->execute([
                'identity_id' => $row['identity_id'],
                'signer_fingerprint' => $row['signer_fingerprint'],
                'armored_key' => $row['public_key'],
                'is_approved' => $row['is_approved'],
            ]);
        }

        return count($rows);
    }

    /** @param array<string, mixed> $thread @param list<array<string, mixed>> $posts */
    private function insertThread(PDO $snapshot, array $thread, array $posts): void
    {
        $lastPost = $posts[array_key_last($posts)];
        $statement = $snapshot->prepare(
            'INSERT INTO threads (
                root_post_id, root_post_created_at, last_activity_at, subject, body_preview,
                reply_count, last_post_id, board_tags_json, thread_labels_json, score_total,
                author_label, author_profile_slug
            ) VALUES (
                :root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview,
                :reply_count, :last_post_id, :board_tags_json, :thread_labels_json, :score_total,
                :author_label, :author_profile_slug
            )'
        );
        $statement->execute([
            'root_post_id' => $thread['root_post_id'],
            'root_post_created_at' => $thread['root_post_created_at'],
            'last_activity_at' => $thread['last_activity_at'],
            'subject' => $thread['subject'],
            'body_preview' => $thread['body_preview'],
            'reply_count' => max(0, count($posts) - 1),
            'last_post_id' => $lastPost['post_id'],
            'board_tags_json' => $thread['board_tags_json'],
            'thread_labels_json' => $thread['thread_labels_json'],
            'score_total' => $thread['score_total'],
            'author_label' => $thread['author_label'],
            'author_profile_slug' => $thread['author_profile_slug'],
        ]);
    }

    /** @param array<string, mixed> $post */
    private function insertPost(PDO $snapshot, array $post): void
    {
        $statement = $snapshot->prepare(
            'INSERT INTO posts (
                post_id, created_at, thread_id, parent_id, subject, body, board_tags_json,
                thread_type, author_label, author_profile_slug, sequence_number
            ) VALUES (
                :post_id, :created_at, :thread_id, :parent_id, :subject, :body, :board_tags_json,
                :thread_type, :author_label, :author_profile_slug, :sequence_number
            )'
        );
        unset($post['author_identity_id']);
        $statement->execute($post);
    }

    private function isSizeLimitFailure(\Throwable $throwable): bool
    {
        return str_contains(strtolower($throwable->getMessage()), 'database or disk is full');
    }
}
