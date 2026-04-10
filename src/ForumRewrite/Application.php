<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\Write\LocalWriteService;
use PDO;
use RuntimeException;

final class Application
{
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ?string $artifactRoot = null,
    ) {
    }

    public function handle(string $method, string $requestUri): void
    {
        $this->ensureReadModel();

        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);

        if ($path === '/api/set_identity_hint') {
            $this->handleSetIdentityHint($method, $query);
            return;
        }

        if ($path === '/api/create_thread') {
            $this->handleCreateThread($method, $query);
            return;
        }

        if ($path === '/api/create_reply') {
            $this->handleCreateReply($method, $query);
            return;
        }

        if ($path === '/api/link_identity') {
            $this->handleLinkIdentity($method, $query);
            return;
        }

        if ($path === '/compose/thread' && $method === 'POST') {
            $this->handleComposeThreadSubmit($query);
            return;
        }

        if ($path === '/compose/reply' && $method === 'POST') {
            $this->handleComposeReplySubmit($query);
            return;
        }

        if (($path === '/account/key/' || $path === '/account/key') && $method === 'POST') {
            $this->handleAccountKeySubmit($query);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/([^/]+)/approve/?$#', $path, $matches) === 1) {
            $this->handleApproveUserSubmit($matches[1], $query);
            return;
        }

        if ($method !== 'GET') {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported in the local test slice, except for the identity-hint cookie route.',
                    'none'
                ),
                405
            );
            return;
        }

        if ($path === '/' || $path === '') {
            if (($query['format'] ?? null) === 'rss') {
                $this->sendXml($this->renderBoardRss(), 200);
                return;
            }

            $this->sendHtml($this->renderBoard(), 200);
            return;
        }

        if ($path === '/instance/' || $path === '/instance') {
            $this->sendHtml($this->renderInstance(), 200);
            return;
        }

        if ($path === '/activity/' || $path === '/activity') {
            if (($query['format'] ?? null) === 'rss') {
                $this->sendXml($this->renderActivityRss((string) ($query['view'] ?? 'all')), 200);
                return;
            }

            $this->sendHtml($this->renderActivity((string) ($query['view'] ?? 'all')), 200);
            return;
        }

        if ($path === '/users/' || $path === '/users') {
            $this->sendHtml($this->renderUserDirectory(), 200);
            return;
        }

        if ($path === '/compose/thread') {
            $this->sendHtml($this->renderComposeThread(), 200);
            return;
        }

        if ($path === '/compose/reply') {
            $this->sendHtml(
                $this->renderComposeReply((string) ($query['thread_id'] ?? ''), (string) ($query['parent_id'] ?? '')),
                200
            );
            return;
        }

        if ($path === '/account/key/' || $path === '/account/key') {
            $this->sendHtml($this->renderAccountKey(), 200);
            return;
        }

        if ($path === '/api/' || $path === '/api') {
            $this->sendText($this->renderApiIndex(), 200);
            return;
        }

        if ($path === '/api/list_index') {
            $this->sendText($this->renderApiListIndex(), 200);
            return;
        }

        if ($path === '/api/get_thread') {
            $thread = $this->renderApiGetThread((string) ($query['thread_id'] ?? ''));
            if ($thread === null) {
                $this->sendText("thread not found\n", 404);
                return;
            }

            $this->sendText($thread, 200);
            return;
        }

        if ($path === '/api/get_post') {
            $post = $this->renderApiGetPost((string) ($query['post_id'] ?? ''));
            if ($post === null) {
                $this->sendText("post not found\n", 404);
                return;
            }

            $this->sendText($post, 200);
            return;
        }

        if ($path === '/api/get_profile') {
            $profile = $this->renderApiGetProfile((string) ($query['profile_slug'] ?? ''));
            if ($profile === null) {
                $this->sendText("profile not found\n", 404);
                return;
            }

            $this->sendText($profile, 200);
            return;
        }

        if ($path === '/api/get_username_claim_cta') {
            $this->sendText("Generate a browser keypair, choose a username, and bootstrap your identity.\n", 200);
            return;
        }

        if ($path === '/api/read_model_status') {
            $this->sendText($this->renderReadModelStatus(), 200);
            return;
        }

        if (preg_match('#^/threads/([^/]+)/?$#', $path, $matches) === 1) {
            if (($query['format'] ?? null) === 'rss') {
                $xml = $this->renderThreadRss($matches[1]);
                if ($xml === null) {
                    $this->notFound();
                    return;
                }

                $this->sendXml($xml, 200);
                return;
            }

            $html = $this->renderThread($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/posts/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderPost($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/profiles/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderProfile($matches[1], isset($query['self']));
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if (preg_match('#^/user/([^/]+)/?$#', $path, $matches) === 1) {
            $html = $this->renderUsername($matches[1]);
            if ($html === null) {
                $this->notFound();
                return;
            }

            $this->sendHtml($html, 200);
            return;
        }

        if ($path === '/llms.txt') {
            $this->sendText($this->renderLlmsTxt(), 200);
            return;
        }

        $this->notFound();
    }

    private function ensureReadModel(): void
    {
        $rebuildReason = 'missing_database';
        if ($this->staleMarker()->exists()) {
            $rebuildReason = 'stale_marker';
        }

        if (is_file($this->databasePath) && $rebuildReason !== 'stale_marker') {
            $pdo = null;
            try {
                $pdo = $this->pdo();
                $metadata = $this->readMetadata($pdo);
                $pdo = null;

                $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
                if (($metadata['repository_root'] ?? null) !== $this->repositoryRoot) {
                    $rebuildReason = 'repository_root_mismatch';
                } elseif (($metadata['schema_version'] ?? null) !== ReadModelMetadata::SCHEMA_VERSION) {
                    $rebuildReason = 'schema_version_mismatch';
                } elseif (($metadata['repository_head'] ?? null) !== $currentRepositoryHead) {
                    $rebuildReason = 'repository_head_mismatch';
                } else {
                    return;
                }
            } catch (\Throwable) {
                $pdo = null;
                $rebuildReason = 'metadata_unreadable';
            }
        }

        $this->executionLock()->withExclusiveLock(function () use ($rebuildReason): void {
            if (is_file($this->databasePath)) {
                try {
                    $metadata = $this->readMetadata($this->pdo());
                    $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
                    if (!$this->staleMarker()->exists()
                        && ($metadata['repository_root'] ?? null) === $this->repositoryRoot
                        && ($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION
                        && ($metadata['repository_head'] ?? null) === $currentRepositoryHead) {
                        return;
                    }
                } catch (\Throwable) {
                    // Rebuild while holding the lock if metadata is still unreadable.
                }
            }

            $builder = new ReadModelBuilder(
                $this->repositoryRoot,
                $this->databasePath,
                new CanonicalRecordRepository($this->repositoryRoot),
                $rebuildReason,
            );
            $builder->rebuild();
            $this->staleMarker()->clear();
        });
    }

    private function renderReadModelStatus(): string
    {
        $metadata = [];
        if (is_file($this->databasePath)) {
            try {
                $metadata = $this->readMetadata($this->pdo());
            } catch (\Throwable) {
                $metadata = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker()->read();
        $status = (($metadata['repository_root'] ?? null) === $this->repositoryRoot)
            && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
            && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
            && $staleMarker === null
            ? 'ready'
            : 'stale';

        return "status={$status}\n"
            . 'schema_version=' . ($metadata['schema_version'] ?? 'missing') . "\n"
            . 'repository_root=' . ($metadata['repository_root'] ?? 'missing') . "\n"
            . 'repository_head=' . ($metadata['repository_head'] ?? 'missing') . "\n"
            . 'current_repository_head=' . $currentRepositoryHead . "\n"
            . 'rebuilt_at=' . ($metadata['rebuilt_at'] ?? 'missing') . "\n"
            . 'lock_status=' . ($this->executionLock()->isLocked() ? 'locked' : 'unlocked') . "\n"
            . 'stale_marker=' . ($staleMarker === null ? 'absent' : 'present') . "\n"
            . 'stale_reason=' . ($staleMarker['reason'] ?? 'none') . "\n"
            . 'stale_commit_sha=' . ($staleMarker['commit_sha'] ?? 'none') . "\n"
            . 'rebuild_reason=' . ($metadata['rebuild_reason'] ?? 'missing') . "\n";
    }

    private function renderBoard(): string
    {
        return $this->renderer()->renderPageTemplate(
            'board.php',
            [
                'threads' => $this->fetchThreads(),
            ],
            'Board',
            'board',
        );
    }

    private function renderThread(string $threadId): ?string
    {
        $threadRow = $this->fetchThread($threadId);
        if ($threadRow === null) {
            return null;
        }

        $title = $threadRow['subject'] ?: $threadRow['root_post_id'];

        return $this->renderer()->renderPageTemplate(
            'thread.php',
            [
                'thread' => $threadRow,
                'posts' => $this->fetchThreadPosts($threadId),
                'title' => $title,
            ],
            $title,
            'board',
        );
    }

    private function renderPost(string $postId): ?string
    {
        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        return $this->renderer()->renderPageTemplate(
            'post.php',
            [
                'post' => $post,
            ],
            'Post ' . $post['post_id'],
            'board',
        );
    }

    private function renderProfile(string $slug, bool $self = false): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        return $this->renderProfilePage($profile, $self);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function renderProfilePage(array $profile, bool $self = false, ?string $notice = null, ?string $error = null): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $canApprove = $viewerProfile !== null
            && ((int) $viewerProfile['is_approved']) === 1
            && ((string) $viewerProfile['identity_id']) !== ((string) $profile['identity_id']);

        return $this->renderer()->renderPageTemplate(
            'profile.php',
            [
                'profile' => $profile,
                'self' => $self,
                'identityHint' => $_COOKIE['identity_hint'] ?? '',
                'notice' => $notice,
                'error' => $error,
                'viewerProfile' => $viewerProfile,
                'canApprove' => $canApprove,
            ],
            'Profile ' . $profile['profile_slug'],
            'profiles',
        );
    }

    private function renderUsername(string $username): ?string
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT identity_id FROM username_routes WHERE username_token = :username_token');
        $stmt->execute(['username_token' => strtolower($username)]);
        $route = $stmt->fetch();
        if ($route === false) {
            return null;
        }

        $profileStmt = $pdo->prepare('SELECT profile_slug FROM profiles WHERE identity_id = :identity_id');
        $profileStmt->execute(['identity_id' => $route['identity_id']]);
        $profile = $profileStmt->fetch();
        if ($profile === false) {
            return null;
        }

        return $this->renderProfile($profile['profile_slug']);
    }

    private function renderInstance(): string
    {
        $pdo = $this->pdo();
        $instance = $pdo->query('SELECT * FROM instance_public WHERE singleton = 1')->fetch();

        return $this->renderer()->renderPageTemplate(
            'instance.php',
            [
                'instance' => $instance,
            ],
            'Instance',
            'instance',
        );
    }

    private function renderActivity(string $view): string
    {
        $view = $this->normalizeActivityView($view);

        return $this->renderer()->renderPageTemplate(
            'activity.php',
            [
                'view' => $view,
                'items' => $this->fetchActivity($view),
            ],
            'Activity',
            'activity',
        );
    }

    private function renderComposeThread(): string
    {
        return $this->renderComposeThreadPage();
    }

    private function renderUserDirectory(): string
    {
        return $this->renderer()->renderPageTemplate(
            'users.php',
            [
                'profiles' => $this->fetchUserDirectoryProfiles(),
            ],
            'Users',
            'profiles',
        );
    }

    private function renderComposeThreadPage(?string $notice = null, ?string $error = null): string
    {
        return $this->renderer()->renderPageTemplate('compose_thread.php', [
            'notice' => $notice,
            'error' => $error,
        ], 'Compose Thread', 'compose', [
            '/assets/openpgp.min.js',
            '/assets/browser_signing.js',
        ]);
    }

    private function renderComposeReply(string $threadId, string $parentId): string
    {
        return $this->renderComposeReplyPage($threadId, $parentId);
    }

    private function renderComposeReplyPage(string $threadId, string $parentId, ?string $notice = null, ?string $error = null): string
    {
        return $this->renderer()->renderPageTemplate('compose_reply.php', [
            'threadId' => $threadId,
            'parentId' => $parentId,
            'notice' => $notice,
            'error' => $error,
        ], 'Compose Reply', 'compose', [
            '/assets/openpgp.min.js',
            '/assets/browser_signing.js',
        ]);
    }

    private function renderAccountKey(): string
    {
        return $this->renderAccountKeyPage();
    }

    private function renderAccountKeyPage(?string $notice = null, ?string $error = null): string
    {
        return $this->renderer()->renderPageTemplate('account_key.php', [
            'identityHint' => $_COOKIE['identity_hint'] ?? '',
            'notice' => $notice,
            'error' => $error,
        ], 'Account Key', 'account', [
            '/assets/openpgp.min.js',
            '/assets/browser_signing.js',
        ]);
    }

    private function renderApiIndex(): string
    {
        return "GET /api/\nGET /api/list_index\nGET /api/get_thread?thread_id=<id>\nGET /api/get_post?post_id=<id>\nGET /api/get_profile?profile_slug=<slug>\nGET /api/get_username_claim_cta\nPOST /api/set_identity_hint\n";
    }

    private function renderApiListIndex(): string
    {
        $lines = [];
        foreach ($this->fetchThreads() as $thread) {
            $subject = $thread['subject'] ?: $thread['root_post_id'];
            $lines[] = $thread['root_post_id'] . "\t" . $subject . "\t" . $thread['reply_count'];
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderApiGetThread(string $threadId): ?string
    {
        $thread = $this->fetchThread($threadId);
        if ($thread === null) {
            return null;
        }

        $lines = [
            'Thread-ID: ' . $thread['root_post_id'],
            'Subject: ' . ($thread['subject'] ?: ''),
            'Reply-Count: ' . $thread['reply_count'],
            '',
        ];

        foreach ($this->fetchThreadPosts($threadId) as $post) {
            $lines[] = '[' . $post['post_id'] . '] ' . trim(str_replace("\n", ' ', $post['body']));
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderApiGetPost(string $postId): ?string
    {
        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        return "Post-ID: {$post['post_id']}\nThread-ID: {$post['thread_id']}\nAuthor: {$post['author_label']}\n\n{$post['body']}";
    }

    private function renderApiGetProfile(string $slug): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        $approved = ((int) $profile['is_approved']) === 1 ? 'yes' : 'no';

        return "Profile-Slug: {$profile['profile_slug']}\nIdentity-ID: {$profile['identity_id']}\nUsername: {$profile['username']}\nApproved: {$approved}\nPosts: {$profile['post_count']}\nThreads: {$profile['thread_count']}\n";
    }

    private function renderBoardRss(): string
    {
        $items = [];
        foreach ($this->fetchThreads() as $thread) {
            $title = $thread['subject'] ?: $thread['root_post_id'];
            $items[] = $this->renderRssItem($title, '/threads/' . $thread['root_post_id'], $thread['body_preview']);
        }

        return $this->renderRssFeed('Board', '/?format=rss', $items);
    }

    private function renderThreadRss(string $threadId): ?string
    {
        $thread = $this->fetchThread($threadId);
        if ($thread === null) {
            return null;
        }

        $items = [];
        foreach ($this->fetchThreadPosts($threadId) as $post) {
            $items[] = $this->renderRssItem(
                $post['post_id'],
                '/posts/' . $post['post_id'],
                trim($post['body'])
            );
        }

        return $this->renderRssFeed($thread['subject'] ?: $threadId, '/threads/' . $threadId . '?format=rss', $items);
    }

    private function renderActivityRss(string $view): string
    {
        $view = $this->normalizeActivityView($view);
        $items = [];
        foreach ($this->fetchActivity($view) as $item) {
            $items[] = $this->renderRssItem($item['label'], '/posts/' . $item['post_id'], $item['kind']);
        }

        return $this->renderRssFeed('Activity ' . $view, '/activity/?view=' . rawurlencode($view) . '&format=rss', $items);
    }

    private function renderLlmsTxt(): string
    {
        return "Local test slice\nGET /api/\nGET /api/list_index\nGET /api/get_thread\nGET /compose/thread\nGET /compose/reply\nGET /account/key/\nGET /instance/\n";
    }

    private function renderPage(string $title, string $content, string $activeSection, array $scriptPaths = []): string
    {
        return $this->renderer()->renderLayout($title, $content, $activeSection, $scriptPaths);
    }

    private function renderer(): TemplateRenderer
    {
        return new TemplateRenderer($this->projectRoot . '/templates');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreads(): array
    {
        $rows = $this->pdo()->query(
            'SELECT root_post_id, subject, body_preview, reply_count, board_tags_json FROM threads ORDER BY root_post_id DESC'
        )->fetchAll();

        return array_values(array_filter(
            $rows,
            fn (array $thread): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchThread(string $threadId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM threads WHERE root_post_id = :thread_id');
        $stmt->execute(['thread_id' => $threadId]);
        $thread = $stmt->fetch();

        return $thread === false ? null : $thread;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreadPosts(string $threadId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT post_id, thread_id, parent_id, subject, body, author_label, author_profile_slug
             FROM posts WHERE thread_id = :thread_id ORDER BY sequence_number ASC'
        );
        $stmt->execute(['thread_id' => $threadId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPost(string $postId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT post_id, thread_id, parent_id, subject, body, author_label, author_profile_slug
             FROM posts WHERE post_id = :post_id'
        );
        $stmt->execute(['post_id' => $postId]);
        $post = $stmt->fetch();

        return $post === false ? null : $post;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileBySlug(string $slug): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, post_count, thread_count
             FROM profiles WHERE profile_slug = :profile_slug'
        );
        $stmt->execute(['profile_slug' => $slug]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProfileByIdentityId(string $identityId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, post_count, thread_count
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveViewerProfileFromIdentityHint(): ?array
    {
        $hint = strtolower(trim((string) ($_COOKIE['identity_hint'] ?? '')));
        if ($hint === '') {
            return null;
        }

        $stmt = $this->pdo()->prepare('SELECT identity_id FROM username_routes WHERE username_token = :username_token');
        $stmt->execute(['username_token' => $hint]);
        $route = $stmt->fetch();
        if ($route !== false) {
            return $this->fetchProfileByIdentityId((string) $route['identity_id']);
        }

        if (str_starts_with($hint, 'openpgp:')) {
            return $this->fetchProfileByIdentityId($hint);
        }

        return $this->fetchProfileBySlug($hint);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchUserDirectoryProfiles(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT profile_slug, username, username_token, fallback_label, post_count, thread_count
             FROM profiles
             WHERE post_count > 0 OR thread_count > 0
             ORDER BY thread_count DESC, post_count DESC, username_token ASC, profile_slug ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivity(string $view): array
    {
        $view = $this->normalizeActivityView($view);
        $sql = 'SELECT kind, post_id, thread_id, label, board_tags_json FROM activity';
        $params = [];

        if ($view === 'content') {
            $sql .= ' WHERE kind IN (\'thread\', \'reply\')';
        } elseif ($view === 'code') {
            $sql .= ' WHERE 1 = 0';
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        return array_values(array_filter(
            $rows,
            fn (array $item): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $item['board_tags_json'])
        ));
    }

    private function renderRssFeed(string $title, string $link, array $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0"><channel><title>' . $this->escapeXml($title) . '</title>'
            . '<link>' . $this->escapeXml('http://localhost' . $link) . '</link>'
            . '<description>' . $this->escapeXml($title . ' feed') . '</description>'
            . implode('', $items)
            . '</channel></rss>';
    }

    private function renderRssItem(string $title, string $link, string $description): string
    {
        return '<item><title>' . $this->escapeXml($title) . '</title>'
            . '<link>' . $this->escapeXml('http://localhost' . $link) . '</link>'
            . '<description>' . $this->escapeXml($description) . '</description></item>';
    }

    private function normalizeActivityView(string $view): string
    {
        return in_array($view, ['all', 'content', 'code'], true) ? $view : 'all';
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
     * @param array<string, mixed> $query
     */
    private function handleSetIdentityHint(string $method, array $query): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $hint = strtolower(trim((string) ($query['identity_hint'] ?? $query['value'] ?? '')));
        if ($hint === '') {
            $hint = 'guest';
        }

        setcookie('identity_hint', $hint, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $_COOKIE['identity_hint'] = $hint;
        $this->sendText("identity_hint={$hint}\n", 200);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreateThread(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $input = $this->requestData($query);
        try {
            $result = $this->writer()->createThread($input);
            $this->sendText("status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n", 200);
        } catch (RuntimeException $exception) {
            $this->sendText("error=" . $exception->getMessage() . "\n", 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleCreateReply(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $input = $this->requestData($query);
        try {
            $result = $this->writer()->createReply($input);
            $this->sendText("status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n", 200);
        } catch (RuntimeException $exception) {
            $this->sendText("error=" . $exception->getMessage() . "\n", 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleLinkIdentity(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $input = $this->requestData($query);
        try {
            $result = $this->writer()->linkIdentity($input);
            $this->sendText(
                "status=ok\nidentity_id={$result['identity_id']}\nprofile_slug={$result['profile_slug']}\nusername={$result['username']}\nbootstrap_post_id={$result['bootstrap_post_id']}\nbootstrap_thread_id={$result['bootstrap_thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200
            );
        } catch (RuntimeException $exception) {
            $this->sendText("error=" . $exception->getMessage() . "\n", 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function requestData(array $query): array
    {
        $data = $query;

        foreach ($_POST as $key => $value) {
            $data[$key] = $value;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key)) {
                        $data[$key] = $value;
                    }
                }
            }
        }

        return $data;
    }

    private function writer(): LocalWriteService
    {
        return new LocalWriteService(
            $this->repositoryRoot,
            $this->databasePath,
            $this->artifactRoot ?? ($this->projectRoot . '/public'),
            new CanonicalRecordRepository($this->repositoryRoot),
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleComposeThreadSubmit(array $query): void
    {
        $input = $this->requestData($query);
        try {
            $result = $this->writer()->createThread($input);
            $location = '/threads/' . $result['thread_id'];
            $this->sendRedirect(
                $location,
                'Created thread ' . $result['thread_id'] . '. Commit ' . $result['commit_sha'] . '.'
            );
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderComposeThreadPage(null, $exception->getMessage()), 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleComposeReplySubmit(array $query): void
    {
        $input = $this->requestData($query);
        $threadId = (string) ($input['thread_id'] ?? '');
        $parentId = (string) ($input['parent_id'] ?? '');

        try {
            $result = $this->writer()->createReply($input);
            $notice = 'Created reply ' . $result['post_id'] . '. '
                . '<a href="/posts/' . $this->escape($result['post_id']) . '">Open post</a>. '
                . 'Commit ' . $this->escape($result['commit_sha']);
            $this->sendHtml($this->renderComposeReplyPage($threadId, $parentId, $notice, null), 200);
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderComposeReplyPage($threadId, $parentId, null, $exception->getMessage()), 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleAccountKeySubmit(array $query): void
    {
        $input = $this->requestData($query);
        try {
            $result = $this->writer()->linkIdentity($input);
            $location = '/profiles/' . $result['profile_slug'];
            $this->sendRedirect(
                $location,
                'Linked identity ' . $result['identity_id'] . ' as ' . $result['username'] . '. Commit ' . $result['commit_sha'] . '.'
            );
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderAccountKeyPage(null, $exception->getMessage()), 400);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleApproveUserSubmit(string $slug, array $query): void
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            $this->notFound();
            return;
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            $this->sendHtml($this->renderProfilePage($profile, false, null, 'Only approved users can approve other users.'), 403);
            return;
        }

        if ((string) $viewerProfile['identity_id'] === (string) $profile['identity_id']) {
            $this->sendHtml($this->renderProfilePage($profile, false, null, 'Self-approval is not allowed.'), 400);
            return;
        }

        try {
            $result = $this->writer()->approveUser([
                'approver_identity_id' => (string) $viewerProfile['identity_id'],
                'target_identity_id' => (string) $profile['identity_id'],
                'target_profile_slug' => (string) $profile['profile_slug'],
                'thread_id' => (string) $profile['bootstrap_thread_id'],
                'parent_id' => (string) $profile['bootstrap_post_id'],
            ]);
            $updatedProfile = $this->fetchProfileBySlug($slug) ?? $profile;
            $notice = 'Approved user ' . $this->escape($updatedProfile['username']) . '. '
                . '<a href="/posts/' . $this->escape($result['post_id']) . '">Open approval post</a>. '
                . 'Commit ' . $this->escape($result['commit_sha']);
            $this->sendHtml($this->renderProfilePage($updatedProfile, false, $notice, null), 200);
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderProfilePage($profile, false, null, $exception->getMessage()), 400);
        }
    }

    private function pdo(): PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
    }

    private function executionLock(): ExecutionLock
    {
        return new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock');
    }

    private function staleMarker(): ReadModelStaleMarker
    {
        return new ReadModelStaleMarker($this->databasePath);
    }

    /**
     * @return array<string, string>
     */
    private function readMetadata(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT key, value FROM metadata')->fetchAll();
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[(string) $row['key']] = (string) $row['value'];
        }

        return $metadata;
    }

    private function notFound(): void
    {
        $this->sendHtml(
            $this->renderMessagePage(
                'Not Found',
                'Not Found',
                'The requested route does not exist in the local test slice.',
                'none'
            ),
            404
        );
    }

    private function sendHtml(string $html, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    private function sendText(string $text, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
    }

    private function sendXml(string $xml, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $xml;
    }

    private function sendRedirect(string $location, string $message, int $statusCode = 303): void
    {
        http_response_code($statusCode);
        header('Location: ' . $location);
        header('Content-Type: text/html; charset=utf-8');

        echo $this->renderer()->renderPageTemplate(
            'redirect.php',
            [
                'location' => $location,
                'message' => $message,
            ],
            'Redirecting',
            'compose'
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderMessagePage(string $title, string $heading, string $message, string $activeSection): string
    {
        return $this->renderer()->renderPageTemplate(
            'message.php',
            [
                'heading' => $heading,
                'message' => $message,
            ],
            $title,
            $activeSection,
        );
    }

}
