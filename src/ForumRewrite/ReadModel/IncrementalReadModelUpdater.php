<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\PostRecord;
use PDO;
use RuntimeException;

class IncrementalReadModelUpdater
{
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';

    public function __construct(
        private readonly string $databasePath,
        private readonly string $repositoryRoot,
    ) {
    }

    /**
     * @return array<string, float>
     */
    public function applyPostWrite(PostRecord $record, string $commitSha): array
    {
        $timings = [];
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $pdo->beginTransaction();

        try {
            $author = $this->measure($timings, 'load_author_profile', fn (): array => $this->loadAuthorProfile($pdo, $record->authorIdentityId));
            $sequenceNumber = $this->measure($timings, 'next_sequence_number', fn (): int => $this->nextSequenceNumber($pdo));
            $boardTagsJson = json_encode($record->boardTags, JSON_THROW_ON_ERROR);
            $hidden = $this->isHiddenBootstrapBoardTags($record->boardTags);

            $this->measure($timings, 'insert_post', function () use ($pdo, $record, $author, $sequenceNumber, $boardTagsJson): void {
                $stmt = $pdo->prepare(
                    'INSERT INTO posts (
                        post_id, created_at, thread_id, parent_id, subject, body, board_tags_json,
                        thread_type, author_identity_id, author_profile_slug, author_label, sequence_number
                     ) VALUES (
                        :post_id, :created_at, :thread_id, :parent_id, :subject, :body, :board_tags_json,
                        :thread_type, :author_identity_id, :author_profile_slug, :author_label, :sequence_number
                     )'
                );
                $stmt->execute([
                    'post_id' => $record->postId,
                    'created_at' => $record->createdAt,
                    'thread_id' => $record->threadId ?? $record->postId,
                    'parent_id' => $record->parentId,
                    'subject' => $record->subject,
                    'body' => $record->body,
                    'board_tags_json' => $boardTagsJson,
                    'thread_type' => $record->threadType,
                    'author_identity_id' => $record->authorIdentityId,
                    'author_profile_slug' => $author['profile_slug'],
                    'author_label' => $author['label'],
                    'sequence_number' => $sequenceNumber,
                ]);
            });

            if ($record->isReply()) {
                $this->measure($timings, 'update_thread', fn (): mixed => $this->updateThreadForReply($pdo, $record));
            } else {
                $this->measure($timings, 'insert_thread', fn (): mixed => $this->insertThread($pdo, $record, $boardTagsJson));
            }

            if (!$hidden) {
                $this->measure($timings, 'upsert_activity', fn (): mixed => $this->insertActivity($pdo, $record, $boardTagsJson));
            }

            if ($record->authorIdentityId !== null && !$hidden) {
                $this->measure($timings, 'update_profile_counts', fn (): mixed => $this->updateProfileCounts($pdo, $record));
            }

            $this->measure($timings, 'write_metadata', fn (): mixed => $this->writeMetadata($pdo, $commitSha));
            $pdo->commit();

            return $timings;
        } catch (\Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @return array{profile_slug:?string,label:string}
     */
    private function loadAuthorProfile(PDO $pdo, ?string $identityId): array
    {
        if ($identityId === null) {
            return [
                'profile_slug' => null,
                'label' => 'guest',
            ];
        }

        $stmt = $pdo->prepare('SELECT profile_slug, username FROM profiles WHERE identity_id = :identity_id');
        $stmt->execute(['identity_id' => $identityId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Incremental read-model update could not resolve author profile.');
        }

        return [
            'profile_slug' => (string) $row['profile_slug'],
            'label' => (string) $row['username'],
        ];
    }

    private function nextSequenceNumber(PDO $pdo): int
    {
        $value = $pdo->query('SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM posts')->fetchColumn();
        return max(1, (int) $value);
    }

    private function insertThread(PDO $pdo, PostRecord $record, string $boardTagsJson): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO threads (
                root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json
             ) VALUES (
                :root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json
             )'
        );
        $stmt->execute([
            'root_post_id' => $record->postId,
            'root_post_created_at' => $record->createdAt,
            'last_activity_at' => $record->createdAt,
            'subject' => $record->subject,
            'body_preview' => $this->preview($record->body),
            'reply_count' => 0,
            'last_post_id' => $record->postId,
            'board_tags_json' => $boardTagsJson,
        ]);
    }

    private function updateThreadForReply(PDO $pdo, PostRecord $record): void
    {
        $threadId = $record->threadId;
        if ($threadId === null) {
            throw new RuntimeException('Reply record is missing thread_id.');
        }

        $stmt = $pdo->prepare(
            'UPDATE threads
             SET last_activity_at = CASE
                    WHEN :created_at > last_activity_at THEN :created_at
                    ELSE last_activity_at
                 END,
                 reply_count = reply_count + 1,
                 last_post_id = :last_post_id
             WHERE root_post_id = :thread_id'
        );
        $stmt->execute([
            'created_at' => $record->createdAt,
            'last_post_id' => $record->postId,
            'thread_id' => $threadId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Incremental read-model update could not resolve target thread.');
        }
    }

    private function insertActivity(PDO $pdo, PostRecord $record, string $boardTagsJson): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO activity (created_at, kind, post_id, thread_id, label, board_tags_json)
             VALUES (:created_at, :kind, :post_id, :thread_id, :label, :board_tags_json)'
        );
        $stmt->execute([
            'created_at' => $record->createdAt,
            'kind' => $record->isReply() ? 'reply' : 'thread',
            'post_id' => $record->postId,
            'thread_id' => $record->threadId ?? $record->postId,
            'label' => $record->subject ?? $this->preview($record->body),
            'board_tags_json' => $boardTagsJson,
        ]);
    }

    private function updateProfileCounts(PDO $pdo, PostRecord $record): void
    {
        $identityId = $record->authorIdentityId;
        if ($identityId === null) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE profiles
             SET post_count = post_count + 1,
                 thread_count = thread_count + :thread_increment
             WHERE identity_id = :identity_id'
        );
        $stmt->execute([
            'thread_increment' => $record->isReply() ? 0 : 1,
            'identity_id' => $identityId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Incremental read-model update could not update author profile counts.');
        }
    }

    private function writeMetadata(PDO $pdo, string $commitSha): void
    {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO metadata (key, value) VALUES (:key, :value)');
        $metadata = [
            'schema_version' => ReadModelMetadata::SCHEMA_VERSION,
            'repository_root' => $this->repositoryRoot,
            'repository_head' => $commitSha,
            'rebuilt_at' => gmdate('c'),
            'rebuild_reason' => 'write_incremental',
        ];

        foreach ($metadata as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    /**
     * @param string[] $boardTags
     */
    private function isHiddenBootstrapBoardTags(array $boardTags): bool
    {
        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
    }

    private function preview(string $body): string
    {
        $line = strtok($body, "\n");
        return $line === false ? '' : $line;
    }

    /**
     * @param array<string, float> $timings
     */
    private function measure(array &$timings, string $name, callable $callback): mixed
    {
        $startedAt = hrtime(true);
        try {
            return $callback();
        } finally {
            $timings[$name] = round((hrtime(true) - $startedAt) / 1000000, 1);
        }
    }
}
