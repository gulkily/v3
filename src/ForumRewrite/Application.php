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
use PDOStatement;

final class Application
{
    private const HIDDEN_BOOTSTRAP_TAG = 'identity';
    private ?string $appVersion = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ?string $artifactRoot = null,
    ) {
    }

    public function handle(string $method, string $requestUri): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $query);

        if ($path === '/api/version') {
            $this->sendText($this->appVersion() . "\n", 200, [
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
                'Pragma: no-cache',
                'Expires: 0',
            ]);
            return;
        }

        $this->ensureReadModel();

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

        if ($path === '/api/approve_user') {
            $this->handleApproveUserApi($method, $query);
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

        if ($path === '/downloads/repository.tar.gz') {
            $this->handleRepositoryDownload($method, 'tar.gz');
            return;
        }

        if ($path === '/downloads/repository.zip') {
            $this->handleRepositoryDownload($method, 'zip');
            return;
        }

        if ($path === '/downloads/read_model.sqlite3') {
            $this->handleReadModelDatabaseDownload($method);
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

        if ($path === '/users/pending/' || $path === '/users/pending') {
            $this->handlePendingUserDirectory($method);
            return;
        }

        if ($path === '/users/' || $path === '/users') {
            $this->sendHtml($this->renderUserDirectory(), 200);
            return;
        }

        if ($path === '/tools/' || $path === '/tools') {
            $this->sendHtml($this->renderTools(), 200);
            return;
        }

        if ($path === '/compose/thread') {
            $this->sendHtml(
                $this->renderComposeThread(
                    (string) ($query['board_tags'] ?? 'general'),
                    (string) ($query['subject'] ?? ''),
                    (string) ($query['body'] ?? '')
                ),
                200
            );
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
            $html = $this->renderProfile($matches[1], isset($query['self']), $query);
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

    /**
     * @param array<string, mixed> $query
     */
    private function renderProfile(string $slug, bool $self = false, array $query = []): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        return $this->renderProfilePage($profile, $self, $this->profileNoticeFromQuery($profile, $query));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function renderProfilePage(array $profile, bool $self = false, ?string $notice = null, ?string $error = null): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        $isOwnProfile = $viewerProfile !== null
            && ((string) $viewerProfile['identity_id']) === ((string) $profile['identity_id']);
        $canApprove = $viewerProfile !== null
            && ((int) $viewerProfile['is_approved']) === 1
            && ((int) $profile['is_approved']) !== 1
            && ((string) $viewerProfile['identity_id']) !== ((string) $profile['identity_id']);
        $pageTitleLabel = trim((string) ($profile['username'] ?? ''));
        if ($pageTitleLabel === '') {
            $pageTitleLabel = trim((string) ($profile['fallback_label'] ?? ''));
        }
        if ($pageTitleLabel === '') {
            $pageTitleLabel = (string) $profile['profile_slug'];
        }

        return $this->renderer()->renderPageTemplate(
            'profile.php',
            [
                'profile' => $profile,
                'self' => $self,
                'identityHint' => $_COOKIE['identity_hint'] ?? '',
                'notice' => $notice,
                'error' => $error,
                'viewerProfile' => $viewerProfile,
                'isOwnProfile' => $isOwnProfile,
                'canApprove' => $canApprove,
            ],
            $pageTitleLabel . ' - Profile',
            'profiles',
        );
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $query
     */
    private function profileNoticeFromQuery(array $profile, array $query): ?string
    {
        if (($query['approval'] ?? null) !== 'success') {
            return null;
        }

        $postId = (string) ($query['post_id'] ?? '');
        $commitSha = (string) ($query['commit'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $postId) || !preg_match('/^[a-f0-9]{40}$/', $commitSha)) {
            return null;
        }

        return 'Approved user ' . $this->escape((string) $profile['username']) . '. '
            . '<a href="/posts/' . $this->escape($postId) . '">Open approval post</a>. '
            . 'Commit ' . $this->escape($commitSha);
    }

    private function renderUsername(string $username): ?string
    {
        $usernameToken = strtolower($username);
        $profiles = $this->fetchProfilesByUsernameToken($usernameToken);
        if ($profiles === []) {
            return null;
        }

        $approvedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) === 1
        ));
        $unapprovedProfiles = array_values(array_filter(
            $profiles,
            static fn (array $profile): bool => ((int) $profile['is_approved']) !== 1
        ));
        $approvedIdentityIds = array_values(array_map(
            static fn (array $profile): string => (string) $profile['identity_id'],
            $approvedProfiles
        ));

        return $this->renderer()->renderPageTemplate(
            'username.php',
            [
                'usernameToken' => $usernameToken,
                'approvedProfiles' => $approvedProfiles,
                'unapprovedProfiles' => $unapprovedProfiles,
                'approvedThreadCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, true),
                'approvedPostCount' => $this->countVisibleAuthoredRows($approvedIdentityIds, false),
                'approvedThreads' => $this->fetchVisibleAuthoredThreads($approvedIdentityIds),
                'approvedPosts' => $this->fetchVisibleAuthoredPosts($approvedIdentityIds),
            ],
            'User ' . $usernameToken,
            'profiles',
        );
    }

    private function renderInstance(): string
    {
        return $this->renderer()->renderPageTemplate(
            'instance.php',
            [
                'siteName' => SiteConfig::SITE_NAME,
                'admins' => $this->fetchSeedApprovedUsers(),
                'downloads' => [
                    [
                        'href' => '/downloads/repository.tar.gz',
                        'label' => 'Content repository (.tar.gz)',
                        'description' => 'Tarball of the full repository, including .git history.',
                    ],
                    [
                        'href' => '/downloads/repository.zip',
                        'label' => 'Content repository (.zip)',
                        'description' => 'ZIP archive of the full repository, including .git history.',
                    ],
                    [
                        'href' => '/downloads/read_model.sqlite3',
                        'label' => 'SQLite index database',
                        'description' => 'Current read-model database for local indexing and queries.',
                    ],
                ],
            ],
            'Backup',
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
                'viewOptions' => [
                    [
                        'label' => 'All Activity',
                        'href' => '/activity/?view=all',
                        'is_active' => $view === 'all',
                    ],
                    [
                        'label' => 'Visible Content',
                        'href' => '/activity/?view=content',
                        'is_active' => $view === 'content',
                    ],
                    [
                        'label' => 'Identity',
                        'href' => '/activity/?view=identity',
                        'is_active' => $view === 'identity',
                    ],
                    [
                        'label' => 'Bootstraps',
                        'href' => '/activity/?view=bootstrap',
                        'is_active' => $view === 'bootstrap',
                    ],
                    [
                        'label' => 'Approvals',
                        'href' => '/activity/?view=approval',
                        'is_active' => $view === 'approval',
                    ],
                    [
                        'label' => 'RSS',
                        'href' => '/activity/?view=' . rawurlencode($view) . '&format=rss',
                        'is_active' => false,
                    ],
                ],
                'items' => $this->fetchActivity($view),
            ],
            'Activity',
            'activity',
        );
    }

    private function renderComposeThread(
        string $boardTags = 'general',
        string $subject = '',
        string $body = '',
    ): string
    {
        return $this->renderComposeThreadPage($boardTags, $subject, $body);
    }

    private function renderUserDirectory(): string
    {
        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();

        return $this->renderer()->renderPageTemplate(
            'users.php',
            [
                'users' => $this->fetchApprovedUserDirectoryUsers(),
                'showPendingLink' => $viewerProfile !== null
                    && ((int) $viewerProfile['is_approved']) === 1
                    && $this->hasPendingUserDirectoryProfiles(),
            ],
            'Users',
            'profiles',
        );
    }

    private function renderPendingUserDirectory(): string
    {
        return $this->renderer()->renderPageTemplate(
            'users_pending.php',
            [
                'profiles' => $this->fetchPendingUserDirectoryProfiles(),
            ],
            'Users Awaiting Approval',
            'profiles',
            ['/assets/pending_approvals.js'],
        );
    }

    private function renderTools(): string
    {
        return $this->renderer()->renderPageTemplate(
            'tools.php',
            [
                'bookmarklets' => [
                    [
                        'label' => '+URL',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with the current page URL in the body.',
                        'bookmarklet_kind' => 'url',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'same-window',
                        'description' => 'Open Compose Thread in this tab with only the selected text.',
                        'bookmarklet_kind' => 'selection',
                    ],
                    [
                        'label' => 'Clip',
                        'mode' => 'new-window',
                        'description' => 'Open Compose Thread in a new window with selected text plus source title and URL.',
                        'bookmarklet_kind' => 'clip',
                    ],
                    [
                        'label' => 'Rip',
                        'mode' => 'new-window',
                        'description' => 'Open Compose Thread in a new window with only the selected text.',
                        'bookmarklet_kind' => 'selection',
                    ],
                ],
            ],
            'Tools',
            'tools',
            ['/assets/tools_bookmarklets.js'],
        );
    }

    private function renderComposeThreadPage(
        string $boardTags = 'general',
        string $subject = '',
        string $body = '',
        ?string $notice = null,
        ?string $error = null
    ): string
    {
        return $this->renderer()->renderPageTemplate('compose_thread.php', [
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'subject' => $subject,
            'body' => $body,
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

    private function renderComposeReplyPage(
        string $threadId,
        string $parentId,
        ?string $notice = null,
        ?string $error = null,
        string $boardTags = 'general',
        string $body = ''
    ): string
    {
        return $this->renderer()->renderPageTemplate('compose_reply.php', [
            'threadId' => $threadId,
            'parentId' => $parentId,
            'notice' => $notice,
            'error' => $error,
            'boardTags' => $boardTags !== '' ? $boardTags : 'general',
            'body' => $body,
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
        return "GET /api/\nGET /api/version\nGET /api/list_index\nGET /api/get_thread?thread_id=<id>\nGET /api/get_post?post_id=<id>\nGET /api/get_profile?profile_slug=<slug>\nGET /api/get_username_claim_cta\nPOST /api/set_identity_hint\n";
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
            'Created-At: ' . $thread['root_post_created_at'],
            'Last-Activity-At: ' . $thread['last_activity_at'],
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

        return "Post-ID: {$post['post_id']}\nCreated-At: {$post['created_at']}\nThread-ID: {$post['thread_id']}\nAuthor: {$post['author_label']}\n\n{$post['body']}";
    }

    private function renderApiGetProfile(string $slug): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        $approved = ((int) $profile['is_approved']) === 1 ? 'yes' : 'no';

        $approvedBy = ((int) $profile['is_approved']) === 1 ? (string) ($profile['approved_by_label'] ?? '') : '';

        return "Profile-Slug: {$profile['profile_slug']}\nIdentity-ID: {$profile['identity_id']}\nUsername: {$profile['username']}\nApproved: {$approved}\nApproved-By: {$approvedBy}\nPosts: {$profile['post_count']}\nThreads: {$profile['thread_count']}\n";
    }

    private function renderBoardRss(): string
    {
        $items = [];
        foreach ($this->fetchThreads() as $thread) {
            $title = $thread['subject'] ?: $thread['root_post_id'];
            $items[] = $this->renderRssItem($title, '/threads/' . $thread['root_post_id'], $thread['body_preview'], (string) $thread['last_activity_at']);
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
                trim($post['body']),
                (string) $post['created_at']
            );
        }

        return $this->renderRssFeed($thread['subject'] ?: $threadId, '/threads/' . $threadId . '?format=rss', $items);
    }

    private function renderActivityRss(string $view): string
    {
        $view = $this->normalizeActivityView($view);
        $items = [];
        foreach ($this->fetchActivity($view) as $item) {
            $items[] = $this->renderRssItem($item['label'], '/posts/' . $item['post_id'], $item['kind'], (string) $item['created_at']);
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
        return new TemplateRenderer($this->projectRoot . '/templates', $this->appVersion());
    }

    private function appVersion(): string
    {
        if ($this->appVersion !== null) {
            return $this->appVersion;
        }

        $this->appVersion = ReadModelMetadata::repositoryHead($this->repositoryRoot);

        return $this->appVersion;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreads(): array
    {
        $rows = $this->pdo()->query(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.board_tags_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             ORDER BY last_activity_at DESC, root_post_id DESC'
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
        $stmt = $this->pdo()->prepare(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.last_post_id, threads.board_tags_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id = :thread_id'
        );
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
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.created_at,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.thread_id = :thread_id
             ORDER BY posts.sequence_number ASC'
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
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.created_at,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.post_id = :post_id'
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
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
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
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
             FROM profiles WHERE identity_id = :identity_id'
        );
        $stmt->execute(['identity_id' => $identityId]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProfilesByUsernameToken(string $usernameToken): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id,
                    bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_profile_slug,
                    approved_by_label, post_count, thread_count
             FROM profiles WHERE username_token = :username_token
             ORDER BY is_approved DESC, profile_slug ASC'
        );
        $stmt->execute(['username_token' => $usernameToken]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSeedApprovedUsers(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT username_token, MIN(username) AS username
             FROM profiles
             WHERE approved_by_label = \'root\'
             GROUP BY username_token
             ORDER BY username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchVisibleAuthoredThreads(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = $this->prepareIdentityListQuery(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.last_post_id, threads.board_tags_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id IN (
                 SELECT post_id FROM posts
                 WHERE post_id = thread_id AND author_identity_id IN (%s)
             )
             ORDER BY last_activity_at DESC, root_post_id ASC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        return array_values(array_filter(
            $rows,
            fn (array $thread): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));
    }

    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchVisibleAuthoredPosts(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = $this->prepareIdentityListQuery(
            'SELECT posts.post_id, posts.created_at, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved, posts.board_tags_json
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE author_identity_id IN (%s)
             ORDER BY created_at DESC, sequence_number DESC, post_id DESC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        return array_values(array_filter(
            $rows,
            fn (array $post): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $post['board_tags_json'])
        ));
    }

    /**
     * @param list<string> $identityIds
     */
    private function countVisibleAuthoredRows(array $identityIds, bool $threadsOnly): int
    {
        return count($threadsOnly ? $this->fetchVisibleAuthoredThreads($identityIds) : $this->fetchVisibleAuthoredPosts($identityIds));
    }

    /**
     * @param list<string> $identityIds
     */
    private function prepareIdentityListQuery(string $sql, array $identityIds): PDOStatement
    {
        $placeholders = implode(', ', array_fill(0, count($identityIds), '?'));

        return $this->pdo()->prepare(sprintf($sql, $placeholders));
    }

    private function handleRepositoryDownload(string $method, string $format): void
    {
        if ($method !== 'GET') {
            $this->sendHtml($this->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none'), 405);
            return;
        }

        $download = $this->buildRepositoryArchive($format);
        $this->sendDownload(
            $download['path'],
            $download['contentType'],
            SiteConfig::SITE_NAME . '-repository-' . $this->repositoryShortCommit() . '.' . $download['extension'],
            true
        );
    }

    /**
     * @return array{path: string, contentType: string, extension: string}
     */
    private function buildRepositoryArchive(string $format): array
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'forum-repo-');
        if ($archivePath === false) {
            throw new RuntimeException('Unable to create temporary archive path.');
        }

        @unlink($archivePath);

        $parent = dirname($this->repositoryRoot);
        $base = basename($this->repositoryRoot);

        if ($format === 'tar.gz') {
            $archiveTarget = $archivePath . '.tar.gz';
            $command = sprintf(
                'tar -czf %s -C %s %s 2>&1',
                escapeshellarg($archiveTarget),
                escapeshellarg($parent),
                escapeshellarg($base)
            );
            $contentType = 'application/gzip';
        } elseif ($format === 'zip') {
            $archiveTarget = $archivePath . '.zip';
            $command = sprintf(
                'cd %s && zip -qr %s %s 2>&1',
                escapeshellarg($parent),
                escapeshellarg($archiveTarget),
                escapeshellarg($base)
            );
            $contentType = 'application/zip';
        } else {
            throw new RuntimeException('Unsupported repository archive format.');
        }

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($archiveTarget)) {
            @unlink($archiveTarget);
            throw new RuntimeException('Unable to archive repository download.');
        }

        return [
            'path' => $archiveTarget,
            'contentType' => $contentType,
            'extension' => $format,
        ];
    }

    private function handleReadModelDatabaseDownload(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendHtml($this->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none'), 405);
            return;
        }

        if (!is_file($this->databasePath)) {
            $this->sendHtml($this->renderMessagePage('Not Found', 'Not Found', 'Read-model database is not available yet.', 'instance'), 404);
            return;
        }

        $this->sendDownload($this->databasePath, 'application/x-sqlite3', SiteConfig::SITE_NAME . '-read-model.sqlite3');
    }

    private function sendDownload(string $path, string $contentType, string $filename, bool $deleteAfterSend = false): void
    {
        $size = filesize($path);
        if ($size === false) {
            if ($deleteAfterSend) {
                @unlink($path);
            }
            throw new RuntimeException('Unable to determine download size.');
        }

        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string) $size);
        readfile($path);

        if ($deleteAfterSend) {
            @unlink($path);
        }
    }

    private function repositoryShortCommit(): string
    {
        $command = sprintf('git -C %s rev-parse --short HEAD 2>&1', escapeshellarg($this->repositoryRoot));
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return 'unknown';
        }

        $shortCommit = trim(implode("\n", $output));

        return $shortCommit !== '' ? $shortCommit : 'unknown';
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
    private function fetchApprovedUserDirectoryUsers(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT username_token, MIN(username) AS username,
                    COUNT(*) AS approved_profile_count,
                    SUM(thread_count) AS thread_count,
                    SUM(post_count) AS post_count
             FROM profiles
             WHERE is_approved = 1
             GROUP BY username_token
             ORDER BY SUM(thread_count) DESC, SUM(post_count) DESC, username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingUserDirectoryProfiles(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT profile_slug, username, username_token, fallback_label, post_count, thread_count, bootstrap_post_id, bootstrap_thread_id
             FROM profiles
             WHERE is_approved = 0
             ORDER BY thread_count DESC, post_count DESC, username_token ASC, profile_slug ASC'
        );

        return $stmt->fetchAll();
    }

    private function hasPendingUserDirectoryProfiles(): bool
    {
        $stmt = $this->pdo()->query('SELECT 1 FROM profiles WHERE is_approved = 0 LIMIT 1');

        return $stmt->fetchColumn() !== false;
    }

    private function handlePendingUserDirectory(string $method): void
    {
        if ($method !== 'GET') {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Method Not Allowed',
                    'Method Not Allowed',
                    'Only GET is supported for the pending user directory.',
                    'none'
                ),
                405
            );
            return;
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            $this->sendHtml(
                $this->renderMessagePage(
                    'Forbidden',
                    'Forbidden',
                    'Only approved users can view the pending approval directory.',
                    'profiles'
                ),
                403
            );
            return;
        }

        $this->sendHtml($this->renderPendingUserDirectory(), 200);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivity(string $view): array
    {
        $view = $this->normalizeActivityView($view);
        $rows = $this->pdo()->query(
            'SELECT activity.created_at, activity.kind, activity.post_id, activity.thread_id, activity.label, activity.board_tags_json,
                    activity.id, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM activity
             JOIN posts ON posts.post_id = activity.post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             ORDER BY activity.created_at DESC, activity.id DESC'
        )->fetchAll();

        $items = array_map(function (array $post): array {
            return [
                'created_at' => $post['created_at'],
                'kind' => $post['kind'],
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $post['label'],
                'board_tags_json' => $post['board_tags_json'],
                'id' => (int) $post['id'],
                'author_label' => $post['author_label'],
                'author_profile_slug' => $post['author_profile_slug'],
                'author_username_token' => $post['author_username_token'],
                'author_is_approved' => (int) $post['author_is_approved'],
            ];
        }, $rows);

        return array_values(array_filter($items, function (array $item) use ($view): bool {
            $boardTagsJson = (string) $item['board_tags_json'];

            return match ($view) {
                'all' => true,
                'content' => !$this->isHiddenBootstrapBoardTagsJson($boardTagsJson),
                'identity' => $this->hasBoardTag($boardTagsJson, 'identity'),
                'bootstrap' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'internal'),
                'approval' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'approval'),
                default => true,
            };
        }));
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

    private function renderRssItem(string $title, string $link, string $description, ?string $publishedAt = null): string
    {
        $item = '<item><title>' . $this->escapeXml($title) . '</title>'
            . '<link>' . $this->escapeXml('http://localhost' . $link) . '</link>'
            . '<description>' . $this->escapeXml($description) . '</description>';

        if ($publishedAt !== null && $publishedAt !== '') {
            $timestamp = strtotime($publishedAt);
            if ($timestamp !== false) {
                $item .= '<pubDate>' . $this->escapeXml(gmdate(DATE_RSS, $timestamp)) . '</pubDate>';
            }
        }

        return $item . '</item>';
    }

    private function normalizeActivityView(string $view): string
    {
        return in_array($view, ['all', 'content', 'identity', 'bootstrap', 'approval'], true) ? $view : 'all';
    }

    private function preview(string $body): string
    {
        $line = strtok($body, "\n");
        return $line === false ? '' : $line;
    }

    private function hasBoardTag(string $boardTagsJson, string $tag): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array($tag, $boardTags, true);
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
     */
    private function handleApproveUserApi(string $method, array $query): void
    {
        if ($method !== 'POST') {
            $this->sendText("method not allowed\n", 405);
            return;
        }

        $profileSlug = trim((string) ($this->requestData($query)['profile_slug'] ?? ''));
        if ($profileSlug === '') {
            $this->sendText("error=Missing profile_slug.\n", 400);
            return;
        }

        try {
            $result = $this->approveUserBySlug($profileSlug);
            $this->sendText(
                "status=ok\nprofile_slug={$result['profile_slug']}\nusername={$result['username']}\npost_id={$result['post_id']}\ncommit_sha={$result['commit_sha']}\n",
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
            $this->sendHtml(
                $this->renderComposeThreadPage(
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['subject'] ?? ''),
                    (string) ($input['body'] ?? ''),
                    null,
                    $exception->getMessage()
                ),
                400
            );
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
            $this->sendHtml(
                $this->renderComposeReplyPage(
                    $threadId,
                    $parentId,
                    null,
                    $exception->getMessage(),
                    (string) ($input['board_tags'] ?? 'general'),
                    (string) ($input['body'] ?? '')
                ),
                400
            );
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

        try {
            $result = $this->approveUserBySlug($slug);
            $location = '/profiles/' . rawurlencode((string) $profile['profile_slug'])
                . '?approval=success&post_id=' . rawurlencode((string) $result['post_id'])
                . '&commit=' . rawurlencode((string) $result['commit_sha']);
            $this->sendRedirect($location, 'Approved user ' . (string) $profile['username'] . '.');
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderProfilePage($profile, false, null, $exception->getMessage()), 400);
        }
    }

    /**
     * @return array{profile_slug:string,username:string,post_id:string,commit_sha:string}
     */
    private function approveUserBySlug(string $slug): array
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            throw new RuntimeException('Profile not found.');
        }

        $viewerProfile = $this->resolveViewerProfileFromIdentityHint();
        if ($viewerProfile === null || ((int) $viewerProfile['is_approved']) !== 1) {
            throw new RuntimeException('Only approved users can approve other users.');
        }

        if ((string) $viewerProfile['identity_id'] === (string) $profile['identity_id']) {
            throw new RuntimeException('Self-approval is not allowed.');
        }

        if ((int) $profile['is_approved'] === 1) {
            throw new RuntimeException('User is already approved.');
        }

        $result = $this->writer()->approveUser([
            'approver_identity_id' => (string) $viewerProfile['identity_id'],
            'target_identity_id' => (string) $profile['identity_id'],
            'target_profile_slug' => (string) $profile['profile_slug'],
            'thread_id' => (string) $profile['bootstrap_thread_id'],
            'parent_id' => (string) $profile['bootstrap_post_id'],
        ]);

        return [
            'profile_slug' => (string) $profile['profile_slug'],
            'username' => (string) $profile['username'],
            'post_id' => (string) $result['post_id'],
            'commit_sha' => (string) $result['commit_sha'],
        ];
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

    private function sendText(string $text, int $statusCode, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        foreach ($headers as $headerValue) {
            header($headerValue);
        }
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
