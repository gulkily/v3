<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\ThreadLabelRecord;
use ForumRewrite\TagScore;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class ReadModelBuilder
{
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';
    /** @var array<string, float> */
    private array $timings = [];
    private int $invalidThreadLabelRecordCount = 0;
    /** @var array<int, array{created_at:string,thread_id:string,author_identity_id:?string,labels_added:list<string>}> */
    private array $threadLabelActivityEvents = [];

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly CanonicalRecordRepository $canonicalRepository,
        private readonly string $rebuildReason = 'manual',
    ) {
    }

    public function rebuild(): void
    {
        $databaseDir = dirname($this->databasePath);
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->timings = [];
        $this->invalidThreadLabelRecordCount = 0;
        $this->threadLabelActivityEvents = [];
        $pdo->beginTransaction();
        try {
            $this->measure('drop_schema', fn (): mixed => $this->dropSchema($pdo));
            $this->measure('create_schema', fn (): mixed => $this->createSchema($pdo));

            $posts = $this->measure('index_posts', fn (): array => $this->indexPosts($pdo));
            $profiles = $this->measure('index_profiles', fn (): array => $this->indexProfiles($pdo));
            $approvalState = $this->measure('derive_approval_state', fn (): array => $this->deriveApprovalState($profiles, $posts));
            $this->measure('index_thread_labels', fn (): mixed => $this->indexThreadLabels($pdo, $approvalState));
            $profileCounts = $this->measure('derive_profile_counts', fn (): array => $this->deriveProfileCounts($profiles, $posts));
            $this->measure('link_post_authors', fn (): mixed => $this->linkPostAuthors($pdo, $profiles, $approvalState, $profileCounts));
            $this->measure('index_instance', fn (): mixed => $this->indexInstance($pdo));
            $this->measure('index_activity', fn (): mixed => $this->indexActivity($pdo, $posts));
            $this->measure('write_metadata', fn (): mixed => $this->writeMetadata($pdo));
            $pdo->commit();
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
    public function timings(): array
    {
        return $this->timings;
    }

    private function dropSchema(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS metadata');
        $pdo->exec('DROP TABLE IF EXISTS posts');
        $pdo->exec('DROP TABLE IF EXISTS threads');
        $pdo->exec('DROP TABLE IF EXISTS profiles');
        $pdo->exec('DROP TABLE IF EXISTS username_routes');
        $pdo->exec('DROP TABLE IF EXISTS instance_public');
        $pdo->exec('DROP TABLE IF EXISTS activity');
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE posts (
                post_id TEXT PRIMARY KEY,
                created_at TEXT NOT NULL,
                thread_id TEXT NOT NULL,
                parent_id TEXT NULL,
                subject TEXT NULL,
                body TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_type TEXT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_label TEXT NOT NULL DEFAULT \'guest\',
                sequence_number INTEGER NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE threads (
                root_post_id TEXT PRIMARY KEY,
                root_post_created_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                subject TEXT NULL,
                body_preview TEXT NOT NULL,
                reply_count INTEGER NOT NULL,
                last_post_id TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_labels_json TEXT NOT NULL,
                score_total INTEGER NOT NULL DEFAULT 0
            )'
        );

        $pdo->exec(
            'CREATE TABLE profiles (
                identity_id TEXT PRIMARY KEY,
                profile_slug TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL,
                username_token TEXT NOT NULL,
                fallback_label TEXT NOT NULL,
                signer_fingerprint TEXT NOT NULL,
                bootstrap_post_id TEXT NOT NULL,
                bootstrap_thread_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                is_approved INTEGER NOT NULL DEFAULT 0,
                approved_by_identity_id TEXT NULL,
                approved_by_profile_slug TEXT NULL,
                approved_by_label TEXT NULL,
                post_count INTEGER NOT NULL DEFAULT 0,
                thread_count INTEGER NOT NULL DEFAULT 0
            )'
        );

        $pdo->exec(
            'CREATE TABLE username_routes (
                username_token TEXT PRIMARY KEY,
                identity_id TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE instance_public (
                singleton INTEGER PRIMARY KEY CHECK (singleton = 1),
                instance_name TEXT NOT NULL,
                admin_name TEXT NOT NULL,
                admin_contact TEXT NOT NULL,
                retention_policy TEXT NOT NULL,
                install_date TEXT NOT NULL,
                body TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                kind TEXT NOT NULL,
                post_id TEXT NOT NULL,
                thread_id TEXT NOT NULL,
                label TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_username_token TEXT NULL,
                author_label TEXT NOT NULL,
                author_is_approved INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    /**
     * @return array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,sequence_number:int}>
     */
    private function indexPosts(PDO $pdo): array
    {
        $insertPost = $pdo->prepare(
            'INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, thread_type, author_identity_id, sequence_number)
             VALUES (:post_id, :created_at, :thread_id, :parent_id, :subject, :body, :board_tags_json, :thread_type, :author_identity_id, :sequence_number)'
        );
        $insertThread = $pdo->prepare(
            'INSERT INTO threads (root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json, score_total)
             VALUES (:root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json, :thread_labels_json, :score_total)'
        );

        $paths = $this->findRelativePaths('records/posts');
        $parsedPosts = [];

        foreach ($paths as $index => $relativePath) {
            $record = $this->canonicalRepository->loadPost($relativePath);
            $threadId = $record->threadId ?? $record->postId;
            $parsedPosts[] = [
                'post_id' => $record->postId,
                'created_at' => $record->createdAt,
                'thread_id' => $threadId,
                'parent_id' => $record->parentId,
                'subject' => $record->subject,
                'body' => $record->body,
                'board_tags_json' => json_encode($record->boardTags, JSON_THROW_ON_ERROR),
                'thread_type' => $record->threadType,
                'author_identity_id' => $record->authorIdentityId,
                'source_order' => $index + 1,
            ];
        }

        usort($parsedPosts, static function (array $left, array $right): int {
            if ($left['thread_id'] !== $right['thread_id']) {
                return $left['thread_id'] <=> $right['thread_id'];
            }

            $leftIsRoot = $left['post_id'] === $left['thread_id'];
            $rightIsRoot = $right['post_id'] === $right['thread_id'];
            if ($leftIsRoot !== $rightIsRoot) {
                return $leftIsRoot ? -1 : 1;
            }

            return $left['source_order'] <=> $right['source_order'];
        });

        $posts = [];
        $threadSummaries = [];
        foreach ($parsedPosts as $index => $post) {
            $post['sequence_number'] = $index + 1;
            unset($post['source_order']);
            $posts[] = $post;
            $insertPost->execute($post);

            if (!isset($threadSummaries[$post['thread_id']])) {
                $threadSummaries[$post['thread_id']] = [
                    'root_post_id' => $post['thread_id'],
                    'root_post_created_at' => $post['created_at'],
                    'last_activity_at' => $post['created_at'],
                    'subject' => $post['subject'],
                    'body_preview' => $this->preview($post['body']),
                    'reply_count' => 0,
                    'last_post_id' => $post['post_id'],
                    'board_tags_json' => $post['board_tags_json'],
                    'thread_labels_json' => '[]',
                    'score_total' => 0,
                ];
            }

            $summary = &$threadSummaries[$post['thread_id']];
            if ($post['post_id'] !== $post['thread_id']) {
                $summary['reply_count']++;
            }
            if ($post['created_at'] > $summary['last_activity_at']) {
                $summary['last_activity_at'] = $post['created_at'];
            }
            $summary['last_post_id'] = $post['post_id'];
            unset($summary);
        }

        foreach ($threadSummaries as $summary) {
            $insertThread->execute($summary);
        }

        return $posts;
    }

    /**
     * @param array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}> $approvalState
     */
    private function indexThreadLabels(PDO $pdo, array $approvalState): void
    {
        $rootThreadIds = $pdo->query('SELECT root_post_id FROM threads')->fetchAll(PDO::FETCH_COLUMN);
        $knownRootThreads = array_fill_keys(array_map(static fn (mixed $value): string => (string) $value, $rootThreadIds), true);
        $records = [];

        foreach ($this->findRelativePaths('records/thread-labels') as $relativePath) {
            try {
                $records[] = $this->canonicalRepository->loadThreadLabel($relativePath);
            } catch (CanonicalRecordParseException) {
                $this->invalidThreadLabelRecordCount++;
            }
        }

        usort($records, static function (ThreadLabelRecord $left, ThreadLabelRecord $right): int {
            if ($left->createdAt !== $right->createdAt) {
                return $left->createdAt <=> $right->createdAt;
            }

            return $left->recordId <=> $right->recordId;
        });

        $labelsByThread = [];
        $scoreByThread = [];
        $countedApprovedScoredTagsByThread = [];
        foreach ($records as $record) {
            if (!isset($knownRootThreads[$record->threadId])) {
                $this->invalidThreadLabelRecordCount++;
                continue;
            }

            $labelsByThread[$record->threadId] ??= [];
            $scoreByThread[$record->threadId] ??= 0;
            $countedApprovedScoredTagsByThread[$record->threadId] ??= [];
            $labelsAdded = [];
            foreach ($record->labels as $label) {
                if (!isset($labelsByThread[$record->threadId][$label])) {
                    $labelsByThread[$record->threadId][$label] = true;
                    $labelsAdded[] = $label;
                }

                if ($record->authorIdentityId === null || !isset($approvalState[$record->authorIdentityId]) || !TagScore::isScoredTag($label)) {
                    continue;
                }

                $dedupeKey = $record->authorIdentityId . ':' . $label;
                if (isset($countedApprovedScoredTagsByThread[$record->threadId][$dedupeKey])) {
                    continue;
                }

                $countedApprovedScoredTagsByThread[$record->threadId][$dedupeKey] = true;
                $scoreByThread[$record->threadId] += TagScore::scoreValueForTag($label);
            }

            if ($labelsAdded !== []) {
                sort($labelsAdded);
                $this->threadLabelActivityEvents[] = [
                    'created_at' => $record->createdAt,
                    'thread_id' => $record->threadId,
                    'author_identity_id' => $record->authorIdentityId,
                    'labels_added' => $labelsAdded,
                ];
            }
        }

        $updateThread = $pdo->prepare(
            'UPDATE threads
             SET thread_labels_json = :thread_labels_json,
                 score_total = :score_total
             WHERE root_post_id = :root_post_id'
        );

        foreach ($knownRootThreads as $threadId => $_present) {
            $labels = array_keys($labelsByThread[$threadId] ?? []);
            sort($labels);
            $updateThread->execute([
                'thread_labels_json' => json_encode($labels, JSON_THROW_ON_ERROR),
                'score_total' => $scoreByThread[$threadId] ?? 0,
                'root_post_id' => $threadId,
            ]);
        }
    }

    /**
     * @return array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}>
     */
    private function indexProfiles(PDO $pdo): array
    {
        $insertProfile = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key
             )'
        );
        $insertUsernameRoute = $pdo->prepare(
            'INSERT INTO username_routes (username_token, identity_id) VALUES (:username_token, :identity_id)'
        );

        $profiles = [];
        $claimedUsernameTokens = [];
        foreach ($this->findRelativePaths('records/identity') as $relativePath) {
            $identity = $this->canonicalRepository->loadIdentity($relativePath);
            $publicKeyPath = 'records/public-keys/openpgp-' . $identity->signerFingerprint . '.asc';
            if (!is_file($this->repositoryRoot . '/' . $publicKeyPath)) {
                $publicKeyPath = 'records/public-keys/openpgp-' . strtolower($identity->signerFingerprint) . '.asc';
            }

            $publicKey = $this->canonicalRepository->loadPublicKey($publicKeyPath);
            $username = $identity->username !== '' ? $identity->username : 'guest';
            $usernameToken = strtolower($username);
            $fallbackLabel = $username . '-' . substr(strtolower($identity->signerFingerprint), 0, 8);
            $profile = [
                'identity_id' => $identity->identityId,
                'profile_slug' => $identity->identitySlug(),
                'username' => $username,
                'username_token' => $usernameToken,
                'fallback_label' => $fallbackLabel,
                'signer_fingerprint' => $identity->signerFingerprint,
                'bootstrap_post_id' => $identity->bootstrapByPost,
                'bootstrap_thread_id' => $identity->bootstrapByThread,
                'public_key' => $publicKey->armoredKey,
            ];

            $insertProfile->execute($profile);
            if (!isset($claimedUsernameTokens[$usernameToken])) {
                $insertUsernameRoute->execute([
                    'username_token' => $usernameToken,
                    'identity_id' => $identity->identityId,
                ]);
                $claimedUsernameTokens[$usernameToken] = true;
            }

            $profiles[$identity->identityId] = [
                'identity_id' => $identity->identityId,
                'profile_slug' => $identity->identitySlug(),
                'username' => $username,
                'username_token' => $usernameToken,
                'bootstrap_post_id' => $identity->bootstrapByPost,
                'bootstrap_thread_id' => $identity->bootstrapByThread,
            ];
        }

        return $profiles;
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}> $approvalState
     * @param array<string, array{post_count:int,thread_count:int}> $profileCounts
     */
    private function linkPostAuthors(PDO $pdo, array $profiles, array $approvalState, array $profileCounts): void
    {
        $updatePost = $pdo->prepare(
            'UPDATE posts
             SET author_identity_id = :author_identity_id, author_profile_slug = :author_profile_slug, author_label = :author_label
             WHERE post_id = :bootstrap_post_id OR author_identity_id = :author_identity_id'
        );
        $updateProfileCounts = $pdo->prepare(
            'UPDATE profiles
             SET is_approved = :is_approved,
                 approved_by_identity_id = :approved_by_identity_id,
                 approved_by_profile_slug = :approved_by_profile_slug,
                 approved_by_label = :approved_by_label,
                 post_count = :post_count,
                 thread_count = :thread_count
             WHERE identity_id = :identity_id'
        );

        foreach ($profiles as $profile) {
            $updatePost->execute([
                'author_identity_id' => $profile['identity_id'],
                'author_profile_slug' => $profile['profile_slug'],
                'author_label' => $profile['username'],
                'bootstrap_post_id' => $profile['bootstrap_post_id'],
            ]);

            $counts = $profileCounts[$profile['identity_id']] ?? ['post_count' => 0, 'thread_count' => 0];
            $approval = $approvalState[$profile['identity_id']] ?? [
                'approved_by_identity_id' => null,
                'approved_by_profile_slug' => null,
                'approved_by_label' => null,
            ];
            $updateProfileCounts->execute([
                'is_approved' => isset($approvalState[$profile['identity_id']]) ? 1 : 0,
                'approved_by_identity_id' => $approval['approved_by_identity_id'],
                'approved_by_profile_slug' => $approval['approved_by_profile_slug'],
                'approved_by_label' => $approval['approved_by_label'],
                'post_count' => $counts['post_count'],
                'thread_count' => $counts['thread_count'],
                'identity_id' => $profile['identity_id'],
            ]);
        }
    }

    private function indexInstance(PDO $pdo): void
    {
        $instance = $this->canonicalRepository->loadInstancePublic('records/instance/public.txt');
        $stmt = $pdo->prepare(
            'INSERT INTO instance_public (
                singleton, instance_name, admin_name, admin_contact, retention_policy, install_date, body
             ) VALUES (
                1, :instance_name, :admin_name, :admin_contact, :retention_policy, :install_date, :body
             )'
        );
        $stmt->execute([
            'instance_name' => $instance->headers['Instance-Name'],
            'admin_name' => $instance->headers['Admin-Name'],
            'admin_contact' => $instance->headers['Admin-Contact'],
            'retention_policy' => $instance->headers['Retention-Policy'],
            'install_date' => $instance->headers['Install-Date'],
            'body' => $instance->body,
        ]);
    }

    /**
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,sequence_number:int}> $posts
     */
    private function indexActivity(PDO $pdo, array $posts): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO activity (
                created_at, kind, post_id, thread_id, label, board_tags_json,
                author_identity_id, author_profile_slug, author_username_token, author_label, author_is_approved
             ) VALUES (
                :created_at, :kind, :post_id, :thread_id, :label, :board_tags_json,
                :author_identity_id, :author_profile_slug, :author_username_token, :author_label, :author_is_approved
             )'
        );
        $postsById = [];

        foreach ($posts as $post) {
            $postsById[$post['post_id']] = $post;
            $author = $this->resolveActivityAuthor($pdo, $post['author_identity_id']);
            $kind = $post['post_id'] === $post['thread_id'] ? 'thread' : 'reply';
            $label = $post['subject'] ?? $this->preview($post['body']);
            $stmt->execute([
                'created_at' => $post['created_at'],
                'kind' => $kind,
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $label,
                'board_tags_json' => $post['board_tags_json'],
                'author_identity_id' => $post['author_identity_id'],
                'author_profile_slug' => $author['author_profile_slug'],
                'author_username_token' => $author['author_username_token'],
                'author_label' => $author['author_label'],
                'author_is_approved' => $author['author_is_approved'],
            ]);
        }

        foreach ($this->threadLabelActivityEvents as $event) {
            $thread = $postsById[$event['thread_id']] ?? null;
            if ($thread === null || $this->isHiddenBootstrapBoardTagsJson($thread['board_tags_json'])) {
                continue;
            }

            $author = $this->resolveActivityAuthor($pdo, $event['author_identity_id']);
            $stmt->execute([
                'created_at' => $event['created_at'],
                'kind' => 'thread_label_add',
                'post_id' => $event['thread_id'],
                'thread_id' => $event['thread_id'],
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

    private function writeMetadata(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO metadata (key, value) VALUES (:key, :value)');
        $metadata = [
            'schema_version' => ReadModelMetadata::SCHEMA_VERSION,
            'repository_root' => $this->repositoryRoot,
            'repository_head' => ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'rebuilt_at' => gmdate('c'),
            'rebuild_reason' => $this->rebuildReason,
            'thread_label_invalid_count' => (string) $this->invalidThreadLabelRecordCount,
        ];

        foreach ($metadata as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function findRelativePaths(string $relativeDirectory): array
    {
        $basePath = $this->repositoryRoot . '/' . $relativeDirectory;
        if (!is_dir($basePath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );
        $paths = [];
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $paths[] = substr($item->getPathname(), strlen($this->repositoryRoot) + 1);
        }

        sort($paths);

        return $paths;
    }

    private function preview(string $body): string
    {
        $line = strtok($body, "\n");
        return $line === false ? '' : $line;
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,sequence_number:int}> $posts
     * @return array<string, array{approved_by_identity_id:?string,approved_by_profile_slug:?string,approved_by_label:?string}>
     */
    private function deriveApprovalState(array $profiles, array $posts): array
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
            $targetIdentityId = $this->extractApprovalTargetIdentityId($post);
            if ($targetIdentityId === null || !isset($profiles[$targetIdentityId])) {
                continue;
            }

            $approverIdentityId = $post['author_identity_id'];
            if ($approverIdentityId === null || !isset($approved[$approverIdentityId])) {
                continue;
            }

            if ($approverIdentityId === $targetIdentityId) {
                continue;
            }

            $targetProfile = $profiles[$targetIdentityId];
            if ($post['thread_id'] !== $targetProfile['bootstrap_thread_id']) {
                continue;
            }

            if ($post['parent_id'] !== $targetProfile['bootstrap_post_id']) {
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
     * @return list<string>
     */
    private function loadApprovalSeedIdentityIds(): array
    {
        $identityIds = [];
        foreach ($this->findRelativePaths('records/approval-seeds') as $relativePath) {
            $identityIds[] = $this->canonicalRepository->loadApprovalSeed($relativePath)->approvedIdentityId;
        }

        sort($identityIds);

        return $identityIds;
    }

    /**
     * @param array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,sequence_number:int} $post
     */
    private function extractApprovalTargetIdentityId(array $post): ?string
    {
        $boardTags = json_decode($post['board_tags_json'], true);
        if (!is_array($boardTags) || !in_array('identity', $boardTags, true) || !in_array('approval', $boardTags, true)) {
            return null;
        }

        if (preg_match('/^Approve-Identity-ID: (openpgp:[a-f0-9]{40})$/m', $post['body'], $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param array<string, array{identity_id:string,profile_slug:string,username:string,username_token:string,bootstrap_post_id:string,bootstrap_thread_id:string}> $profiles
     * @param array<int, array{post_id:string,created_at:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,author_identity_id:?string,sequence_number:int}> $posts
     * @return array<string, array{post_count:int,thread_count:int}>
     */
    private function deriveProfileCounts(array $profiles, array $posts): array
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

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
    }

    private function measure(string $name, callable $callback): mixed
    {
        $startedAt = hrtime(true);
        try {
            return $callback();
        } finally {
            $this->timings[$name] = round((hrtime(true) - $startedAt) / 1000000, 1);
        }
    }
}
