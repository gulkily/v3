<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\IdentityBootstrapRecord;
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
    public function applyIdentityLink(IdentityBootstrapRecord $record, string $commitSha): array
    {
        $timings = [];
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $pdo->beginTransaction();

        try {
            $this->measure($timings, 'ensure_bootstrap_post', fn (): mixed => $this->ensureBootstrapPost($pdo, $record));
            $profile = $this->measure($timings, 'insert_profile', fn (): array => $this->insertProfile($pdo, $record));
            $this->measure($timings, 'ensure_username_route', fn (): mixed => $this->ensureUsernameRoute($pdo, $profile));
            $this->measure($timings, 'link_posts', fn (): mixed => $this->linkIdentityPosts($pdo, $profile));
            $this->measure($timings, 'link_activity', fn (): mixed => $this->linkIdentityActivity($pdo, $profile));
            $this->measure($timings, 'refresh_profile_state', fn (): mixed => $this->refreshProfileState($pdo));
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

            $this->measure($timings, 'upsert_activity', fn (): mixed => $this->insertActivity($pdo, $record, $boardTagsJson));

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
     * @return array<string, float>
     */
    public function applyApprovalWrite(PostRecord $record, string $commitSha): array
    {
        $timings = [];
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $pdo->beginTransaction();

        try {
            $author = $this->measure($timings, 'load_author_profile', fn (): array => $this->loadAuthorProfile($pdo, $record->authorIdentityId));
            $sequenceNumber = $this->measure($timings, 'next_sequence_number', fn (): int => $this->nextSequenceNumber($pdo));
            $boardTagsJson = json_encode($record->boardTags, JSON_THROW_ON_ERROR);

            $this->measure(
                $timings,
                'insert_post',
                fn (): mixed => $this->insertPostRow($pdo, $record, $author, $sequenceNumber, $boardTagsJson)
            );
            $this->measure($timings, 'update_thread', fn (): mixed => $this->updateThreadForReply($pdo, $record));
            $this->measure($timings, 'upsert_activity', fn (): mixed => $this->insertActivity($pdo, $record, $boardTagsJson));
            $changedIdentityIds = $this->measure(
                $timings,
                'refresh_approval_state',
                fn (): array => $this->refreshApprovalState($pdo)
            );

            if ($changedIdentityIds !== []) {
                $this->measure(
                    $timings,
                    'refresh_approval_sensitive_thread_scores',
                    fn (): mixed => $this->refreshApprovalSensitiveThreadScores($pdo, $changedIdentityIds)
                );
                $this->measure(
                    $timings,
                    'refresh_changed_activity_authors',
                    fn (): mixed => $this->refreshActivityAuthors($pdo, $changedIdentityIds)
                );
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

    private function ensureBootstrapPost(PDO $pdo, IdentityBootstrapRecord $identity): void
    {
        $stmt = $pdo->prepare('SELECT 1 FROM posts WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $identity->bootstrapByPost]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $bootstrapPost = $repository->loadPost(CanonicalPathResolver::post($identity->bootstrapByPost));
        $author = $this->loadAuthorProfile($pdo, $bootstrapPost->authorIdentityId);
        $sequenceNumber = $this->nextSequenceNumber($pdo);
        $boardTagsJson = json_encode($bootstrapPost->boardTags, JSON_THROW_ON_ERROR);

        $this->insertPostRow($pdo, $bootstrapPost, $author, $sequenceNumber, $boardTagsJson);
        if ($bootstrapPost->isReply()) {
            $this->updateThreadForReply($pdo, $bootstrapPost);
        } else {
            $this->insertThread($pdo, $bootstrapPost, $boardTagsJson);
        }

        $this->insertActivity($pdo, $bootstrapPost, $boardTagsJson);
        if ($bootstrapPost->authorIdentityId !== null && !$this->isHiddenBootstrapBoardTags($bootstrapPost->boardTags)) {
            $this->updateProfileCounts($pdo, $bootstrapPost);
        }
    }

    /**
     * @return array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}
     */
    private function insertProfile(PDO $pdo, IdentityBootstrapRecord $identity): array
    {
        $username = $identity->username !== '' ? $identity->username : 'guest';
        $usernameToken = strtolower($username);
        $profile = [
            'identity_id' => $identity->identityId,
            'profile_slug' => $identity->identitySlug(),
            'username' => $username,
            'username_token' => $usernameToken,
            'fallback_label' => $username . '-' . substr(strtolower($identity->signerFingerprint), 0, 8),
            'signer_fingerprint' => $identity->signerFingerprint,
            'bootstrap_post_id' => $identity->bootstrapByPost,
            'bootstrap_thread_id' => $identity->bootstrapByThread,
            'public_key' => $identity->armoredPublicKey,
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key
             )'
        );
        $stmt->execute($profile);

        return [
            'identity_id' => $profile['identity_id'],
            'profile_slug' => $profile['profile_slug'],
            'username' => $profile['username'],
            'username_token' => $profile['username_token'],
            'bootstrap_post_id' => $profile['bootstrap_post_id'],
            'bootstrap_thread_id' => $profile['bootstrap_thread_id'],
        ];
    }

    /**
     * @param array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string} $profile
     */
    private function ensureUsernameRoute(PDO $pdo, array $profile): void
    {
        $existing = $pdo->prepare('SELECT identity_id FROM username_routes WHERE username_token = :username_token');
        $existing->execute(['username_token' => $profile['username_token']]);
        if ($existing->fetchColumn() !== false) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO username_routes (username_token, identity_id) VALUES (:username_token, :identity_id)'
        );
        $insert->execute([
            'username_token' => $profile['username_token'],
            'identity_id' => $profile['identity_id'],
        ]);
    }

    /**
     * @param array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string} $profile
     */
    private function linkIdentityPosts(PDO $pdo, array $profile): void
    {
        $stmt = $pdo->prepare(
            'UPDATE posts
             SET author_identity_id = :author_identity_id,
                 author_profile_slug = :author_profile_slug,
                 author_label = :author_label
             WHERE post_id = :bootstrap_post_id OR author_identity_id = :author_identity_id'
        );
        $stmt->execute([
            'author_identity_id' => $profile['identity_id'],
            'author_profile_slug' => $profile['profile_slug'],
            'author_label' => $profile['username'],
            'bootstrap_post_id' => $profile['bootstrap_post_id'],
        ]);
    }

    /**
     * @param array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string} $profile
     */
    private function linkIdentityActivity(PDO $pdo, array $profile): void
    {
        $approved = $this->loadProfileApprovalFields($pdo, $profile['identity_id']);
        $stmt = $pdo->prepare(
            'UPDATE activity
             SET author_profile_slug = :author_profile_slug,
                 author_username_token = :author_username_token,
                 author_label = :author_label,
                 author_is_approved = :author_is_approved
             WHERE author_identity_id = :author_identity_id'
        );
        $stmt->execute([
            'author_identity_id' => $profile['identity_id'],
            'author_profile_slug' => $profile['profile_slug'],
            'author_username_token' => $profile['username_token'],
            'author_label' => $profile['username'],
            'author_is_approved' => $approved['is_approved'],
        ]);
    }

    private function refreshProfileState(PDO $pdo): void
    {
        $profiles = $this->loadProfiles($pdo);
        if ($profiles === []) {
            return;
        }

        $posts = $this->loadPostsForProfileDerivation($pdo);
        $approvalState = $this->deriveApprovalState($posts, $profiles);
        $profileCounts = $this->deriveProfileCounts($posts, $profiles);

        $update = $pdo->prepare(
            'UPDATE profiles
             SET is_approved = :is_approved,
                 approved_by_identity_id = :approved_by_identity_id,
                 approved_by_profile_slug = :approved_by_profile_slug,
                 approved_by_label = :approved_by_label,
                 post_count = :post_count,
                 thread_count = :thread_count
             WHERE identity_id = :identity_id'
        );

        foreach ($profiles as $identityId => $profile) {
            $approval = $approvalState[$identityId] ?? [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => null,
            ];
            $counts = $profileCounts[$identityId] ?? ['post_count' => 0, 'thread_count' => 0];
            $update->execute([
                'is_approved' => isset($approvalState[$identityId]) ? 1 : 0,
                'approved_by_identity_id' => $approval['approved_by_identity_id'],
                'approved_by_profile_slug' => $approval['approved_by_profile_slug'],
                'approved_by_label' => $approval['approved_by_label'],
                'post_count' => $counts['post_count'],
                'thread_count' => $counts['thread_count'],
                'identity_id' => $identityId,
            ]);
        }

        foreach ($profiles as $identityId => $profile) {
            $this->linkIdentityActivity($pdo, $profile);
        }
    }

    /**
     * @return list<string>
     */
    private function refreshApprovalState(PDO $pdo): array
    {
        $profiles = $this->loadProfiles($pdo);
        if ($profiles === []) {
            return [];
        }

        $posts = $this->loadPostsForProfileDerivation($pdo);
        $approvalState = $this->deriveApprovalState($posts, $profiles);
        $currentApprovalState = $this->loadCurrentApprovalState($pdo);
        $changedIdentityIds = [];

        $update = $pdo->prepare(
            'UPDATE profiles
             SET is_approved = :is_approved,
                 approved_by_identity_id = :approved_by_identity_id,
                 approved_by_profile_slug = :approved_by_profile_slug,
                 approved_by_label = :approved_by_label
             WHERE identity_id = :identity_id'
        );

        foreach ($profiles as $identityId => $profile) {
            $approval = $approvalState[$identityId] ?? [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => null,
            ];
            $nextState = [
                'is_approved' => isset($approvalState[$identityId]) ? 1 : 0,
                'approved_by_identity_id' => $approval['approved_by_identity_id'],
                'approved_by_profile_slug' => $approval['approved_by_profile_slug'],
                'approved_by_label' => $approval['approved_by_label'],
            ];
            $currentState = $currentApprovalState[$identityId] ?? [
                'is_approved' => 0,
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => null,
            ];

            if ($currentState !== $nextState) {
                $changedIdentityIds[] = $identityId;
            }

            $update->execute([
                'is_approved' => $nextState['is_approved'],
                'approved_by_identity_id' => $nextState['approved_by_identity_id'],
                'approved_by_profile_slug' => $nextState['approved_by_profile_slug'],
                'approved_by_label' => $nextState['approved_by_label'],
                'identity_id' => $identityId,
            ]);
        }

        sort($changedIdentityIds);

        return $changedIdentityIds;
    }

    /**
     * @return array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}>
     */
    private function loadProfiles(PDO $pdo): array
    {
        $profiles = [];
        $rows = $pdo->query(
            'SELECT identity_id, profile_slug, username, username_token, bootstrap_post_id, bootstrap_thread_id
             FROM profiles ORDER BY profile_slug ASC'
        )->fetchAll();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $profiles[(string) $row['identity_id']] = [
                'identity_id' => (string) $row['identity_id'],
                'profile_slug' => (string) $row['profile_slug'],
                'username' => (string) $row['username'],
                'username_token' => (string) $row['username_token'],
                'bootstrap_post_id' => (string) $row['bootstrap_post_id'],
                'bootstrap_thread_id' => (string) $row['bootstrap_thread_id'],
            ];
        }

        return $profiles;
    }

    /**
     * @return array<string, array{is_approved:int,approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}>
     */
    private function loadCurrentApprovalState(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT identity_id, COALESCE(is_approved, 0) AS is_approved,
                    approved_by_identity_id, approved_by_profile_slug, approved_by_label
             FROM profiles'
        )->fetchAll();
        $approvalState = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $approvalState[(string) $row['identity_id']] = [
                'is_approved' => (int) $row['is_approved'],
                'approved_by_identity_id' => $row['approved_by_identity_id'] !== null ? (string) $row['approved_by_identity_id'] : null,
                'approved_by_profile_slug' => $row['approved_by_profile_slug'] !== null ? (string) $row['approved_by_profile_slug'] : null,
                'approved_by_label' => $row['approved_by_label'] !== null ? (string) $row['approved_by_label'] : null,
            ];
        }

        return $approvalState;
    }

    /**
     * @return list<array{post_id:string,thread_id:string,parent_id:?string,body:string,board_tags_json:string,author_identity_id:?string,sequence_number:int}>
     */
    private function loadPostsForProfileDerivation(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT post_id, thread_id, parent_id, body, board_tags_json, author_identity_id, sequence_number
             FROM posts ORDER BY sequence_number ASC'
        )->fetchAll();
        $posts = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $posts[] = [
                'post_id' => (string) $row['post_id'],
                'thread_id' => (string) $row['thread_id'],
                'parent_id' => $row['parent_id'] !== null ? (string) $row['parent_id'] : null,
                'body' => (string) $row['body'],
                'board_tags_json' => (string) $row['board_tags_json'],
                'author_identity_id' => $row['author_identity_id'] !== null ? (string) $row['author_identity_id'] : null,
                'sequence_number' => (int) $row['sequence_number'],
            ];
        }

        return $posts;
    }

    /**
     * @param list<array{post_id:string,thread_id:string,parent_id:?string,body:string,board_tags_json:string,author_identity_id:?string,sequence_number:int}> $posts
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @return array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}>
     */
    private function deriveApprovalState(array $posts, array $profiles): array
    {
        $approved = [];
        foreach ($this->loadApprovalSeedIdentityIds() as $identityId) {
            $approved[$identityId] = [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => 'root',
            ];
        }

        foreach ($posts as $post) {
            $targetIdentityId = $this->extractApprovalTargetIdentityId($post['board_tags_json'], $post['body']);
            if ($targetIdentityId === null || !isset($profiles[$targetIdentityId])) {
                continue;
            }

            $approverIdentityId = $post['author_identity_id'];
            if ($approverIdentityId === null || !isset($approved[$approverIdentityId]) || $approverIdentityId === $targetIdentityId) {
                continue;
            }

            $targetProfile = $profiles[$targetIdentityId];
            if ($post['thread_id'] !== $targetProfile['bootstrap_thread_id'] || $post['parent_id'] !== $targetProfile['bootstrap_post_id']) {
                continue;
            }

            $approverProfile = $profiles[$approverIdentityId] ?? null;
            $approved[$targetIdentityId] = [
                'approved_by_identity_id' => $approverIdentityId,
                'approved_by_profile_slug' => $approverProfile['profile_slug'] ?? null,
                'approved_by_label' => $approverProfile['username'] ?? $approverIdentityId,
            ];
        }

        return $approved;
    }

    /**
     * @param list<array{post_id:string,thread_id:string,parent_id:?string,body:string,board_tags_json:string,author_identity_id:?string,sequence_number:int}> $posts
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @return array<string, array{post_count:int,thread_count:int}>
     */
    private function deriveProfileCounts(array $posts, array $profiles): array
    {
        $counts = [];
        $bootstrapPostOwners = [];
        foreach ($profiles as $identityId => $profile) {
            $counts[$identityId] = ['post_count' => 0, 'thread_count' => 0];
            $bootstrapPostOwners[$profile['bootstrap_post_id']] = $identityId;
        }

        foreach ($posts as $post) {
            if ($this->isHiddenBootstrapBoardTagsJson($post['board_tags_json'])) {
                continue;
            }

            $owners = [];
            if ($post['author_identity_id'] !== null && isset($counts[$post['author_identity_id']])) {
                $owners[$post['author_identity_id']] = true;
            }
            if (isset($bootstrapPostOwners[$post['post_id']])) {
                $owners[$bootstrapPostOwners[$post['post_id']]] = true;
            }

            foreach (array_keys($owners) as $identityId) {
                $counts[$identityId]['post_count']++;
                if ($post['post_id'] === $post['thread_id']) {
                    $counts[$identityId]['thread_count']++;
                }
            }
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function loadApprovalSeedIdentityIds(): array
    {
        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $identityIds = [];
        foreach (glob($this->repositoryRoot . '/records/approval-seeds/*.txt') ?: [] as $path) {
            $identityIds[] = $repository->loadApprovalSeed('records/approval-seeds/' . basename($path))->approvedIdentityId;
        }

        sort($identityIds);

        return $identityIds;
    }

    private function extractApprovalTargetIdentityId(string $boardTagsJson, string $body): ?string
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags) || !in_array('identity', $boardTags, true) || !in_array('approval', $boardTags, true)) {
            return null;
        }

        if (preg_match('/^Approve-Identity-ID: (openpgp:[a-f0-9]{40})$/m', $body, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array{is_approved:int}
     */
    private function loadProfileApprovalFields(PDO $pdo, string $identityId): array
    {
        $stmt = $pdo->prepare('SELECT COALESCE(is_approved, 0) AS is_approved FROM profiles WHERE identity_id = :identity_id');
        $stmt->execute(['identity_id' => $identityId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['is_approved' => 0];
        }

        return ['is_approved' => (int) $row['is_approved']];
    }

    /**
     * @param array{profile_slug:?string,username_token:?string,label:string,is_approved:int} $author
     */
    private function insertPostRow(PDO $pdo, PostRecord $record, array $author, int $sequenceNumber, string $boardTagsJson): void
    {
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
     * @param list<string> $identityIds
     */
    private function refreshActivityAuthors(PDO $pdo, array $identityIds): void
    {
        $update = $pdo->prepare(
            'UPDATE activity
             SET author_profile_slug = :author_profile_slug,
                 author_username_token = :author_username_token,
                 author_label = :author_label,
                 author_is_approved = :author_is_approved
             WHERE author_identity_id = :author_identity_id'
        );

        foreach ($identityIds as $identityId) {
            $author = $this->resolveActivityAuthor($pdo, $identityId);
            $update->execute([
                'author_identity_id' => $identityId,
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
            ]);
        }
    }

    /**
     * @param list<string> $changedIdentityIds
     */
    private function refreshApprovalSensitiveThreadScores(PDO $pdo, array $changedIdentityIds): void
    {
        $affectedThreadIds = $this->loadThreadIdsForLabelAuthors($changedIdentityIds);
        if ($affectedThreadIds === []) {
            return;
        }

        $approvedIdentityIds = $this->loadApprovedIdentityIds($pdo);
        foreach ($affectedThreadIds as $threadId) {
            if (!$this->threadExists($pdo, $threadId)) {
                continue;
            }

            $records = $this->loadThreadLabelRecords($threadId);
            $labelState = $this->deriveThreadLabelState($threadId, $records, $approvedIdentityIds);
            $this->updateThreadScoreTotal($pdo, $threadId, $labelState['score_total']);
        }
    }

    /**
     * @param list<string> $identityIds
     * @return list<string>
     */
    private function loadThreadIdsForLabelAuthors(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $identityLookup = array_fill_keys($identityIds, true);
        $repository = new CanonicalRecordRepository($this->repositoryRoot);
        $threadIds = [];

        foreach (glob($this->repositoryRoot . '/records/thread-labels/*.txt') ?: [] as $path) {
            try {
                $record = $repository->loadThreadLabel('records/thread-labels/' . basename($path));
            } catch (CanonicalRecordParseException) {
                continue;
            }

            if ($record->authorIdentityId === null || !isset($identityLookup[$record->authorIdentityId])) {
                continue;
            }

            $threadIds[$record->threadId] = true;
        }

        $result = array_keys($threadIds);
        sort($result);

        return $result;
    }

    private function threadExists(PDO $pdo, string $threadId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM threads WHERE root_post_id = :thread_id');
        $stmt->execute(['thread_id' => $threadId]);

        return $stmt->fetchColumn() !== false;
    }

    private function updateThreadScoreTotal(PDO $pdo, string $threadId, int $scoreTotal): void
    {
        $stmt = $pdo->prepare(
            'UPDATE threads
             SET score_total = :score_total
             WHERE root_post_id = :thread_id'
        );
        $stmt->execute([
            'score_total' => $scoreTotal,
            'thread_id' => $threadId,
        ]);
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
