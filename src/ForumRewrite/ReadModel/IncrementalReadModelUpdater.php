<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\PostRecord;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\TagScore;
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
     * @return array<string, float>
     */
    public function applyThreadLabelWrite(string $threadId, string $commitSha): array
    {
        $timings = [];
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $pdo->beginTransaction();

        try {
            $thread = $this->measure($timings, 'load_thread', fn (): array => $this->loadThread($pdo, $threadId));
            $records = $this->measure($timings, 'load_thread_label_records', fn (): array => $this->loadThreadLabelRecords($threadId));
            $approvedIdentityIds = $this->measure($timings, 'load_approved_identity_ids', fn (): array => $this->loadApprovedIdentityIds($pdo));
            $labelState = $this->measure(
                $timings,
                'derive_thread_label_state',
                fn (): array => $this->deriveThreadLabelState($threadId, $records, $approvedIdentityIds)
            );

            $this->measure(
                $timings,
                'update_thread_labels',
                fn (): mixed => $this->updateThreadLabels($pdo, $threadId, $labelState['labels'], $labelState['score_total'])
            );
            $this->measure(
                $timings,
                'refresh_thread_label_activity',
                fn (): mixed => $this->refreshThreadLabelActivity($pdo, $thread, $labelState['activity_events'])
            );
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
     * @return array{profile_slug:?string,username_token:?string,label:string,is_approved:int}
     */
    private function loadAuthorProfile(PDO $pdo, ?string $identityId): array
    {
        if ($identityId === null) {
            return [
                'profile_slug' => null,
                'username_token' => null,
                'label' => 'guest',
                'is_approved' => 0,
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT profile_slug, username, username_token, COALESCE(is_approved, 0) AS is_approved
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Incremental read-model update could not resolve author profile.');
        }

        return [
            'profile_slug' => (string) $row['profile_slug'],
            'username_token' => (string) $row['username_token'],
            'label' => (string) $row['username'],
            'is_approved' => (int) $row['is_approved'],
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
                root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json, score_total
             ) VALUES (
                :root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json, :thread_labels_json, :score_total
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
            'thread_labels_json' => '[]',
            'score_total' => 0,
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
        $author = $this->loadAuthorProfile($pdo, $record->authorIdentityId);
        $stmt = $pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved
             ) VALUES (
                :created_at, :kind, :post_id, :thread_id, :label, :board_tags_json,
                :author_identity_id, :author_profile_slug, :author_username_token, :author_label, :author_is_approved
             )'
        );
        $stmt->execute([
            'created_at' => $record->createdAt,
            'kind' => $record->isReply() ? 'reply' : 'thread',
            'post_id' => $record->postId,
            'thread_id' => $record->threadId ?? $record->postId,
            'label' => $record->subject ?? $this->preview($record->body),
            'board_tags_json' => $boardTagsJson,
            'author_identity_id' => $record->authorIdentityId,
            'author_profile_slug' => $author['profile_slug'],
            'author_username_token' => $author['username_token'],
            'author_label' => $author['label'],
            'author_is_approved' => $author['is_approved'],
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
     * @return array{root_post_id:string,board_tags_json:string}
     */
    private function loadThread(PDO $pdo, string $threadId): array
    {
        $stmt = $pdo->prepare('SELECT root_post_id, board_tags_json FROM threads WHERE root_post_id = :thread_id');
        $stmt->execute(['thread_id' => $threadId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Incremental thread-label update could not resolve target thread.');
        }

        return [
            'root_post_id' => (string) $row['root_post_id'],
            'board_tags_json' => (string) $row['board_tags_json'],
        ];
    }

    /**
     * @return list<ThreadLabelRecord>
     */
    private function loadThreadLabelRecords(string $threadId): array
    {
        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $records = [];
        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            try {
                $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            } catch (CanonicalRecordParseException) {
                continue;
            }

            if ($record->threadId !== $threadId) {
                continue;
            }

            $records[] = $record;
        }

        usort($records, static function (ThreadLabelRecord $left, ThreadLabelRecord $right): int {
            if ($left->createdAt !== $right->createdAt) {
                return $left->createdAt <=> $right->createdAt;
            }

            return $left->recordId <=> $right->recordId;
        });

        return $records;
    }

    /**
     * @return array<string, true>
     */
    private function loadApprovedIdentityIds(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT identity_id FROM profiles WHERE is_approved = 1')->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $rows), true);
    }

    /**
     * @param list<ThreadLabelRecord> $records
     * @param array<string, true> $approvedIdentityIds
     * @return array{labels:list<string>,score_total:int,activity_events:list<array{created_at:string,author_identity_id:?string,labels_added:list<string>}>}
     */
    private function deriveThreadLabelState(string $threadId, array $records, array $approvedIdentityIds): array
    {
        $labels = [];
        $scoreTotal = 0;
        $countedApprovedScoredTags = [];
        $activityEvents = [];

        foreach ($records as $record) {
            if ($record->threadId !== $threadId) {
                continue;
            }

            $labelsAdded = [];
            foreach ($record->labels as $label) {
                if (!isset($labels[$label])) {
                    $labels[$label] = true;
                    $labelsAdded[] = $label;
                }

                if ($record->authorIdentityId === null
                    || !isset($approvedIdentityIds[$record->authorIdentityId])
                    || !TagScore::isScoredTag($label)) {
                    continue;
                }

                $dedupeKey = $record->authorIdentityId . ':' . $label;
                if (isset($countedApprovedScoredTags[$dedupeKey])) {
                    continue;
                }

                $countedApprovedScoredTags[$dedupeKey] = true;
                $scoreTotal += TagScore::scoreValueForTag($label);
            }

            if ($labelsAdded !== []) {
                sort($labelsAdded);
                $activityEvents[] = [
                    'created_at' => $record->createdAt,
                    'author_identity_id' => $record->authorIdentityId,
                    'labels_added' => $labelsAdded,
                ];
            }
        }

        $labelList = array_keys($labels);
        sort($labelList);

        return [
            'labels' => $labelList,
            'score_total' => $scoreTotal,
            'activity_events' => $activityEvents,
        ];
    }

    /**
     * @param list<string> $labels
     */
    private function updateThreadLabels(PDO $pdo, string $threadId, array $labels, int $scoreTotal): void
    {
        $stmt = $pdo->prepare(
            'UPDATE threads
             SET thread_labels_json = :thread_labels_json,
                 score_total = :score_total
             WHERE root_post_id = :thread_id'
        );
        $stmt->execute([
            'thread_labels_json' => json_encode($labels, JSON_THROW_ON_ERROR),
            'score_total' => $scoreTotal,
            'thread_id' => $threadId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Incremental thread-label update could not update target thread.');
        }
    }

    /**
     * @param array{root_post_id:string,board_tags_json:string} $thread
     * @param list<array{created_at:string,author_identity_id:?string,labels_added:list<string>}> $activityEvents
     */
    private function refreshThreadLabelActivity(PDO $pdo, array $thread, array $activityEvents): void
    {
        $delete = $pdo->prepare('DELETE FROM activity WHERE kind = :kind AND thread_id = :thread_id');
        $delete->execute([
            'kind' => 'thread_label_add',
            'thread_id' => $thread['root_post_id'],
        ]);

        if ($this->isHiddenBootstrapBoardTagsJson($thread['board_tags_json'])) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved
             ) VALUES (
                :created_at, :kind, :post_id, :thread_id, :label, :board_tags_json,
                :author_identity_id, :author_profile_slug, :author_username_token, :author_label, :author_is_approved
             )'
        );

        foreach ($activityEvents as $event) {
            $author = $this->resolveActivityAuthor($pdo, $event['author_identity_id']);
            $insert->execute([
                'created_at' => $event['created_at'],
                'kind' => 'thread_label_add',
                'post_id' => $thread['root_post_id'],
                'thread_id' => $thread['root_post_id'],
                'label' => 'Labels added: ' . implode(', ', $event['labels_added']),
                'board_tags_json' => $thread['board_tags_json'],
                'author_identity_id' => $event['author_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
            ]);
        }
    }

    /**
     * @return array{author_profile_slug:?string,author_username_token:?string,author_label:string,author_is_approved:int}
     */
    private function resolveActivityAuthor(PDO $pdo, ?string $identityId): array
    {
        if ($identityId === null) {
            return [
                'author_profile_slug' => null,
                'author_username_token' => null,
                'author_label' => 'guest',
                'author_is_approved' => 0,
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT profile_slug, username, username_token, COALESCE(is_approved, 0) AS is_approved
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return [
                'author_profile_slug' => null,
                'author_username_token' => null,
                'author_label' => $identityId,
                'author_is_approved' => 0,
            ];
        }

        return [
            'author_profile_slug' => $row['profile_slug'] !== null ? (string) $row['profile_slug'] : null,
            'author_username_token' => $row['username_token'] !== null ? (string) $row['username_token'] : null,
            'author_label' => (string) $row['username'],
            'author_is_approved' => (int) $row['is_approved'],
        ];
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
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
