<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\Write\LocalWriteService;
use PDO;
use RuntimeException;

final class Application
{
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

        if ($method !== 'GET') {
            $this->sendHtml($this->renderPage('Method Not Allowed', '<p>Only GET is supported in the local test slice, except for the identity-hint cookie route.</p>', 'none'), 405);
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
        if (is_file($this->databasePath)) {
            $pdo = null;
            try {
                $pdo = $this->pdo();
                $stmt = $pdo->prepare('SELECT value FROM metadata WHERE key = :key');
                $stmt->execute(['key' => 'repository_root']);
                $repositoryRoot = $stmt->fetchColumn();
                $stmt = null;
                $pdo = null;
                if ($repositoryRoot === $this->repositoryRoot) {
                    return;
                }
            } catch (\Throwable) {
                $pdo = null;
                // Rebuild if the database is missing metadata or has an older schema.
            }
        }

        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
        );
        $builder->rebuild();
    }

    private function renderBoard(): string
    {
        $threads = $this->fetchThreads();

        $items = '';
        foreach ($threads as $thread) {
            $subject = $thread['subject'] ?: $thread['root_post_id'];
            $items .= sprintf(
                '<article class="card"><h2><a href="/threads/%1$s">%2$s</a></h2><p>%3$s</p><p class="meta">%4$d replies</p></article>',
                $this->escape($thread['root_post_id']),
                $this->escape($subject),
                nl2br($this->escape($thread['body_preview'])),
                (int) $thread['reply_count'],
            );
        }

        return $this->renderPage('Board', '<section class="stack"><h1>Board</h1>' . $items . '</section>', 'board');
    }

    private function renderThread(string $threadId): ?string
    {
        $threadRow = $this->fetchThread($threadId);
        if ($threadRow === null) {
            return null;
        }

        $items = '';
        foreach ($this->fetchThreadPosts($threadId) as $post) {
            $items .= $this->renderPostCard($post);
        }

        $title = $threadRow['subject'] ?: $threadRow['root_post_id'];

        return $this->renderPage(
            $title,
            '<section class="stack"><h1>' . $this->escape($title) . '</h1><p class="meta">' . (int) $threadRow['reply_count'] . ' replies</p>' . $items . '</section>',
            'board'
        );
    }

    private function renderPost(string $postId): ?string
    {
        $post = $this->fetchPost($postId);
        if ($post === null) {
            return null;
        }

        $author = $post['author_profile_slug']
            ? '<a href="/profiles/' . $this->escape($post['author_profile_slug']) . '">' . $this->escape($post['author_label']) . '</a>'
            : $this->escape($post['author_label']);

        $content = '<section class="stack">'
            . '<h1>Post ' . $this->escape($post['post_id']) . '</h1>'
            . '<p class="meta">Thread <a href="/threads/' . $this->escape($post['thread_id']) . '">' . $this->escape($post['thread_id']) . '</a></p>'
            . '<p class="meta">Author ' . $author . '</p>'
            . '<article class="card"><div class="body">' . nl2br($this->escape($post['body'])) . '</div></article>'
            . '</section>';

        return $this->renderPage('Post ' . $post['post_id'], $content, 'board');
    }

    private function renderProfile(string $slug, bool $self = false): ?string
    {
        $profile = $this->fetchProfileBySlug($slug);
        if ($profile === null) {
            return null;
        }

        $identityHint = $_COOKIE['identity_hint'] ?? '';
        $selfBanner = $self
            ? '<article class="card"><p class="meta">Self profile mode</p><p>This route can show account-aware bootstrap context. Current cookie hint: '
                . $this->escape($identityHint !== '' ? $identityHint : 'none') . '</p></article>'
            : '';

        $content = '<section class="stack">'
            . '<h1>Profile ' . $this->escape($profile['profile_slug']) . '</h1>'
            . $selfBanner
            . '<article class="card">'
            . '<p><strong>Identity ID:</strong> ' . $this->escape($profile['identity_id']) . '</p>'
            . '<p><strong>Visible username:</strong> ' . $this->escape($profile['username']) . '</p>'
            . '<p><strong>Fallback label:</strong> ' . $this->escape($profile['fallback_label']) . '</p>'
            . '<p><strong>Bootstrap post:</strong> <a href="/posts/' . $this->escape($profile['bootstrap_post_id']) . '">' . $this->escape($profile['bootstrap_post_id']) . '</a></p>'
            . '<p><strong>Bootstrap thread:</strong> <a href="/threads/' . $this->escape($profile['bootstrap_thread_id']) . '">' . $this->escape($profile['bootstrap_thread_id']) . '</a></p>'
            . '<p><strong>Threads:</strong> ' . (int) $profile['thread_count'] . '</p>'
            . '<p><strong>Posts:</strong> ' . (int) $profile['post_count'] . '</p>'
            . '<p><strong>Username route:</strong> <a href="/user/' . $this->escape($profile['username_token']) . '">/user/' . $this->escape($profile['username_token']) . '</a></p>'
            . '</article>'
            . '<article class="card"><h2>Public key</h2><pre>' . $this->escape($profile['public_key']) . '</pre></article>'
            . '</section>';

        return $this->renderPage('Profile ' . $profile['profile_slug'], $content, 'profiles');
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

        $content = '<section class="stack"><h1>Instance</h1><article class="card">'
            . '<p><strong>Name:</strong> ' . $this->escape($instance['instance_name']) . '</p>'
            . '<p><strong>Admin:</strong> ' . $this->escape($instance['admin_name']) . '</p>'
            . '<p><strong>Contact:</strong> ' . $this->escape($instance['admin_contact']) . '</p>'
            . '<p><strong>Installed:</strong> ' . $this->escape($instance['install_date']) . '</p>'
            . '<p><strong>Retention:</strong> ' . $this->escape($instance['retention_policy']) . '</p>'
            . '</article><article class="card"><div class="body">' . nl2br($this->escape($instance['body'])) . '</div></article></section>';

        return $this->renderPage('Instance', $content, 'instance');
    }

    private function renderActivity(string $view): string
    {
        $view = $this->normalizeActivityView($view);
        $items = $this->fetchActivity($view);

        $content = '<section class="stack"><h1>Activity</h1><p class="meta">View: ' . $this->escape($view) . '</p>';
        foreach ($items as $item) {
            $content .= '<article class="card"><p class="meta">' . $this->escape($item['kind']) . '</p>'
                . '<p><a href="/posts/' . $this->escape($item['post_id']) . '">' . $this->escape($item['post_id']) . '</a></p>'
                . '<p>' . $this->escape($item['label']) . '</p>'
                . '</article>';
        }
        $content .= '</section>';

        return $this->renderPage('Activity', $content, 'activity');
    }

    private function renderComposeThread(): string
    {
        return $this->renderComposeThreadPage();
    }

    private function renderComposeThreadPage(?string $notice = null, ?string $error = null): string
    {
        $feedback = $this->renderFeedback($notice, $error);
        $content = '<section class="stack"><h1>Compose Thread</h1><article class="card">'
            . $feedback
            . '<p>Posts are stored as canonical ASCII files and the SQLite read model rebuilds immediately.</p>'
            . '<form method="post" class="stack">'
            . '<label>Board tags<input type="text" name="board_tags" value="general"></label>'
            . '<label>Subject<input type="text" name="subject" placeholder="Thread subject"></label>'
            . '<label>Body<textarea name="body" rows="7" placeholder="ASCII body"></textarea></label>'
            . '<button type="submit">Create thread</button>'
            . '</form>'
            . '</article></section>';

        return $this->renderPage('Compose Thread', $content, 'compose');
    }

    private function renderComposeReply(string $threadId, string $parentId): string
    {
        return $this->renderComposeReplyPage($threadId, $parentId);
    }

    private function renderComposeReplyPage(string $threadId, string $parentId, ?string $notice = null, ?string $error = null): string
    {
        $feedback = $this->renderFeedback($notice, $error);
        $content = '<section class="stack"><h1>Compose Reply</h1><article class="card">'
            . $feedback
            . '<p><strong>Thread ID:</strong> ' . $this->escape($threadId !== '' ? $threadId : 'missing') . '</p>'
            . '<p><strong>Parent ID:</strong> ' . $this->escape($parentId !== '' ? $parentId : 'missing') . '</p>'
            . '<form method="post" class="stack">'
            . '<input type="hidden" name="thread_id" value="' . $this->escape($threadId) . '">'
            . '<input type="hidden" name="parent_id" value="' . $this->escape($parentId) . '">'
            . '<label>Board tags<input type="text" name="board_tags" value="general"></label>'
            . '<label>Body<textarea name="body" rows="7" placeholder="ASCII reply body"></textarea></label>'
            . '<button type="submit">Create reply</button>'
            . '</form>'
            . '</article></section>';

        return $this->renderPage('Compose Reply', $content, 'compose');
    }

    private function renderAccountKey(): string
    {
        return $this->renderAccountKeyPage();
    }

    private function renderAccountKeyPage(?string $notice = null, ?string $error = null): string
    {
        $identityHint = $_COOKIE['identity_hint'] ?? '';
        $feedback = $this->renderFeedback($notice, $error);
        $content = '<section class="stack"><h1>Account Key</h1><article class="card">'
            . $feedback
            . '<p>Paste an armored public key to bootstrap an identity from its public key user ID.</p>'
            . '<p><strong>Identity hint cookie:</strong> ' . $this->escape($identityHint !== '' ? $identityHint : 'none') . '</p>'
            . '<form method="post" class="stack">'
            . '<label>Bootstrap post ID<input type="text" name="bootstrap_post_id" value="root-001"></label>'
            . '<label>Public key<textarea name="public_key" rows="10" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----"></textarea></label>'
            . '<button type="submit">Link identity</button>'
            . '</form>'
            . '</article></section>';

        return $this->renderPage('Account Key', $content, 'account');
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

        return "Profile-Slug: {$profile['profile_slug']}\nIdentity-ID: {$profile['identity_id']}\nUsername: {$profile['username']}\nPosts: {$profile['post_count']}\nThreads: {$profile['thread_count']}\n";
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

    private function renderPage(string $title, string $content, string $activeSection): string
    {
        $nav = [
            '/' => ['Board', 'board'],
            '/activity/' => ['Activity', 'activity'],
            '/compose/thread' => ['Compose', 'compose'],
            '/account/key/' => ['Account', 'account'],
            '/instance/' => ['Instance', 'instance'],
            '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954' => ['Profile', 'profiles'],
        ];

        $navHtml = '';
        foreach ($nav as $href => [$label, $section]) {
            $class = $section === $activeSection ? 'nav-link is-active' : 'nav-link';
            $navHtml .= '<a class="' . $class . '" href="' . $href . '">' . $this->escape($label) . '</a>';
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . '</title>'
            . '<link rel="stylesheet" href="/assets/site.css"></head><body>'
            . '<!-- route-source: php-fallback -->'
            . '<div class="shell"><header class="site-header"><p class="eyebrow">PHP Forum Rewrite</p><nav class="nav">' . $navHtml . '</nav></header>'
            . '<main class="main">' . $content . '</main></div></body></html>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchThreads(): array
    {
        return $this->pdo()->query(
            'SELECT root_post_id, subject, body_preview, reply_count FROM threads ORDER BY root_post_id DESC'
        )->fetchAll();
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
                    bootstrap_thread_id, public_key, post_count, thread_count
             FROM profiles WHERE profile_slug = :profile_slug'
        );
        $stmt->execute(['profile_slug' => $slug]);
        $profile = $stmt->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivity(string $view): array
    {
        $view = $this->normalizeActivityView($view);
        $sql = 'SELECT kind, post_id, thread_id, label FROM activity';
        $params = [];

        if ($view === 'content') {
            $sql .= ' WHERE kind IN (\'thread\', \'reply\')';
        } elseif ($view === 'code') {
            $sql .= ' WHERE 1 = 0';
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function renderPostCard(array $post): string
    {
        $author = $post['author_profile_slug']
            ? '<a href="/profiles/' . $this->escape((string) $post['author_profile_slug']) . '">' . $this->escape((string) $post['author_label']) . '</a>'
            : $this->escape((string) $post['author_label']);

        return '<article class="card">'
            . '<p class="meta">Post <a href="/posts/' . $this->escape((string) $post['post_id']) . '">' . $this->escape((string) $post['post_id']) . '</a> by ' . $author . '</p>'
            . '<div class="body">' . nl2br($this->escape((string) $post['body'])) . '</div>'
            . '</article>';
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
                "status=ok\nidentity_id={$result['identity_id']}\nprofile_slug={$result['profile_slug']}\nusername={$result['username']}\ncommit_sha={$result['commit_sha']}\n",
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
            $notice = 'Linked identity ' . $result['identity_id'] . ' as ' . $result['username'] . '. '
                . '<a href="/profiles/' . $this->escape($result['profile_slug']) . '">Open profile</a>. '
                . 'Commit ' . $this->escape($result['commit_sha']);
            $this->sendHtml($this->renderAccountKeyPage($notice, null), 200);
        } catch (RuntimeException $exception) {
            $this->sendHtml($this->renderAccountKeyPage(null, $exception->getMessage()), 400);
        }
    }

    private function pdo(): PDO
    {
        return (new ReadModelConnection($this->databasePath))->open();
    }

    private function notFound(): void
    {
        $this->sendHtml($this->renderPage('Not Found', '<p>The requested route does not exist in the local test slice.</p>', 'none'), 404);
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

        echo $this->renderPage(
            'Redirecting',
            '<section class="stack"><h1>Redirecting</h1><article class="card"><p>'
            . $this->escape($message)
            . ' <a href="' . $this->escape($location) . '">Continue</a></p></article></section>',
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

    private function renderFeedback(?string $notice, ?string $error): string
    {
        $html = '';
        if ($notice !== null && $notice !== '') {
            $html .= '<div class="feedback feedback-ok">' . $notice . '</div>';
        }

        if ($error !== null && $error !== '') {
            $html .= '<div class="feedback feedback-error">' . $this->escape($error) . '</div>';
        }

        return $html;
    }
}
