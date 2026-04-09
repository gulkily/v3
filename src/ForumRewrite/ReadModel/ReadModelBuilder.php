<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class ReadModelBuilder
{
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

        $this->dropSchema($pdo);
        $this->createSchema($pdo);

        $posts = $this->indexPosts($pdo);
        $profiles = $this->indexProfiles($pdo);
        $this->linkPostAuthors($pdo, $profiles);
        $this->indexInstance($pdo);
        $this->indexActivity($pdo, $posts);
        $this->writeMetadata($pdo);
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
                subject TEXT NULL,
                body_preview TEXT NOT NULL,
                reply_count INTEGER NOT NULL,
                last_post_id TEXT NOT NULL,
                board_tags_json TEXT NOT NULL
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
                kind TEXT NOT NULL,
                post_id TEXT NOT NULL,
                thread_id TEXT NOT NULL,
                label TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array<int, array{post_id:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,sequence_number:int}>
     */
    private function indexPosts(PDO $pdo): array
    {
        $insertPost = $pdo->prepare(
            'INSERT INTO posts (post_id, thread_id, parent_id, subject, body, board_tags_json, thread_type, sequence_number)
             VALUES (:post_id, :thread_id, :parent_id, :subject, :body, :board_tags_json, :thread_type, :sequence_number)'
        );
        $insertThread = $pdo->prepare(
            'INSERT INTO threads (root_post_id, subject, body_preview, reply_count, last_post_id, board_tags_json)
             VALUES (:root_post_id, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json)'
        );

        $paths = $this->findRelativePaths('records/posts');
        $parsedPosts = [];

        foreach ($paths as $index => $relativePath) {
            $record = $this->canonicalRepository->loadPost($relativePath);
            $threadId = $record->threadId ?? $record->postId;
            $parsedPosts[] = [
                'post_id' => $record->postId,
                'thread_id' => $threadId,
                'parent_id' => $record->parentId,
                'subject' => $record->subject,
                'body' => $record->body,
                'board_tags_json' => json_encode($record->boardTags, JSON_THROW_ON_ERROR),
                'thread_type' => $record->threadType,
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
        $threadChildren = [];
        foreach ($parsedPosts as $index => $post) {
            $post['sequence_number'] = $index + 1;
            unset($post['source_order']);
            $posts[] = $post;
            $insertPost->execute($post);
            $threadChildren[$post['thread_id']][] = $post['post_id'];
        }

        foreach ($posts as $post) {
            if ($post['thread_id'] !== $post['post_id']) {
                continue;
            }

            $children = $threadChildren[$post['post_id']] ?? [$post['post_id']];
            $replyCount = count($children) - 1;
            $insertThread->execute([
                'root_post_id' => $post['post_id'],
                'subject' => $post['subject'],
                'body_preview' => $this->preview($post['body']),
                'reply_count' => $replyCount,
                'last_post_id' => $children[array_key_last($children)],
                'board_tags_json' => $post['board_tags_json'],
            ]);
        }

        return $posts;
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
            if (!isset($profiles[$usernameToken])) {
                $insertUsernameRoute->execute([
                    'username_token' => $usernameToken,
                    'identity_id' => $identity->identityId,
                ]);
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
     */
    private function linkPostAuthors(PDO $pdo, array $profiles): void
    {
        $updatePost = $pdo->prepare(
            'UPDATE posts
             SET author_identity_id = :author_identity_id, author_profile_slug = :author_profile_slug, author_label = :author_label
             WHERE post_id = :post_id'
        );
        $updateProfileCounts = $pdo->prepare(
            'UPDATE profiles
             SET post_count = post_count + :post_count, thread_count = thread_count + :thread_count
             WHERE identity_id = :identity_id'
        );

        foreach ($profiles as $profile) {
            $updatePost->execute([
                'author_identity_id' => $profile['identity_id'],
                'author_profile_slug' => $profile['profile_slug'],
                'author_label' => $profile['username'],
                'post_id' => $profile['bootstrap_post_id'],
            ]);

            $threadCount = $profile['bootstrap_post_id'] === $profile['bootstrap_thread_id'] ? 1 : 0;
            $updateProfileCounts->execute([
                'post_count' => 1,
                'thread_count' => $threadCount,
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
     * @param array<int, array{post_id:string,thread_id:string,parent_id:?string,subject:?string,body:string,board_tags_json:string,thread_type:?string,sequence_number:int}> $posts
     */
    private function indexActivity(PDO $pdo, array $posts): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO activity (kind, post_id, thread_id, label) VALUES (:kind, :post_id, :thread_id, :label)'
        );

        foreach ($posts as $post) {
            $kind = $post['post_id'] === $post['thread_id'] ? 'thread' : 'reply';
            $label = $post['subject'] ?? $this->preview($post['body']);
            $stmt->execute([
                'kind' => $kind,
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $label,
            ]);
        }
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
}
