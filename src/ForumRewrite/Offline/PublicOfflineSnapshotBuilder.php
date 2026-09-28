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
    public const SNAPSHOT_VERSION = '1';

    /**
     * @return array{generated_at:string,thread_count:int,post_count:int,size_bytes:int}
     */
    public function build(
        string $sourcePath,
        string $targetPath,
        int $threadLimit = 50,
        int $maxBytes = 10485760,
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
            $this->writeMetadata($snapshot, [
                'snapshot_version' => self::SNAPSHOT_VERSION,
                'generated_at' => $generatedAt,
                'thread_limit' => (string) $threadLimit,
                'max_bytes' => (string) $maxBytes,
            ]);

            $threadCount = 0;
            $postCount = 0;
            foreach ($this->visibleThreadRows($source) as $thread) {
                if ($threadCount >= $threadLimit) {
                    break;
                }

                $posts = $this->visiblePostsForThread($source, (string) $thread['root_post_id']);
                if ($posts === [] || (string) $posts[0]['post_id'] !== (string) $thread['root_post_id']) {
                    continue;
                }

                try {
                    $snapshot->beginTransaction();
                    $this->insertThread($snapshot, $thread, $posts);
                    foreach ($posts as $post) {
                        $this->insertPost($snapshot, $post);
                    }
                    $snapshot->commit();
                } catch (\Throwable $throwable) {
                    if ($snapshot->inTransaction()) {
                        $snapshot->rollBack();
                    }
                    if ($this->isSizeLimitFailure($throwable)) {
                        break;
                    }

                    throw $throwable;
                }

                $threadCount++;
                $postCount += count($posts);
            }

            $this->writeMetadata($snapshot, [
                'thread_count' => (string) $threadCount,
                'post_count' => (string) $postCount,
            ]);
            $snapshot = null;

            $size = filesize($temporaryPath);
            if ($size === false || $size > $maxBytes) {
                throw new RuntimeException('Offline snapshot exceeded its configured size limit.');
            }
            if (!rename($temporaryPath, $targetPath)) {
                throw new RuntimeException('Unable to publish offline snapshot.');
            }

            return [
                'generated_at' => $generatedAt,
                'thread_count' => $threadCount,
                'post_count' => $postCount,
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
        $pdo->exec('CREATE INDEX posts_thread_sequence_idx ON posts (thread_id, sequence_number, post_id)');
        $pdo->exec('PRAGMA max_page_count = ' . intdiv($maxBytes, 4096));
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

    /** @return list<array<string, mixed>> */
    private function visiblePostsForThread(PDO $source, string $threadId): array
    {
        $statement = $source->prepare(
            'SELECT post_id, created_at, thread_id, parent_id, subject, body, board_tags_json,
                    thread_type, author_label, author_profile_slug, sequence_number
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
        $statement->execute($post);
    }

    private function isSizeLimitFailure(\Throwable $throwable): bool
    {
        return str_contains(strtolower($throwable->getMessage()), 'database or disk is full');
    }
}
