<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\Http\BoardPageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\SiteProfileRegistry;
use PDO;

final class QdbExperience
{
    private const QUERY_KEYS = ['latest', 'top', 'leetness', 'add', 'random', 'search'];

    public function __construct(
        private readonly BoardPageController $boardPages,
        private readonly PDO $pdo,
        private readonly QdbBoardPolicy $boardPolicy,
        private readonly RouteServices $routeServices,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function matches(string $path, array $query): bool
    {
        return $this->isEnabled() && $this->isPrimaryRoutePath($path);
    }

    public function isEnabled(): bool
    {
        return in_array('qdb', SiteProfileRegistry::active()['enabledExperienceKeys'], true);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function dispatch(string $path, array $query): ?QdbExperienceRouteResult
    {
        if (!$this->matches($path, $query)) {
            return null;
        }

        $latestPageMatch = preg_match('#^/latest/(\d+)/?$#', $path, $latestPathMatches) === 1;
        $topPageMatch = preg_match('#^/top/(\d+)/?$#', $path, $topPathMatches) === 1;
        if ($path === '/latest' || $latestPageMatch || array_key_exists('latest', $query)) {
            $page = $latestPageMatch ? (int) $latestPathMatches[1] : (int) ($query['latest'] ?? 1);

            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'newest', 'latest', max(1, $page), $this->boardPolicy));
        }

        if ($path === '/top' || $topPageMatch || array_key_exists('top', $query)) {
            $page = $topPageMatch ? (int) $topPathMatches[1] : (int) ($query['top'] ?? 1);

            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'top', 'top', max(1, $page), $this->boardPolicy));
        }

        if ($path === '/leetness' || array_key_exists('leetness', $query)) {
            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'leetness', 'leetness', 1, $this->boardPolicy));
        }

        if ($path === '/add' || array_key_exists('add', $query)) {
            return QdbExperienceRouteResult::page($this->add($query));
        }

        if ($path === '/random' || array_key_exists('random', $query)) {
            return QdbExperienceRouteResult::page($this->random());
        }

        if ($path === '/search' || array_key_exists('search', $query)) {
            return QdbExperienceRouteResult::page($this->search((string) ($query['search'] ?? '')));
        }

        foreach (array_keys($query) as $key) {
            if (in_array($key, array_merge(['view', 'sort', 'format'], self::QUERY_KEYS), true)) {
                continue;
            }

            if (ThreadRepository::byId($this->pdo, $key) !== null) {
                return QdbExperienceRouteResult::redirect('/threads/' . $key, 'Here is that quote.');
            }
        }

        if (($path === '/' || $path === '') && ($query['format'] ?? null) !== 'rss') {
            return QdbExperienceRouteResult::page($this->welcome());
        }

        return null;
    }

    public function dispatchUnmatched(string $path): ?QdbExperienceRouteResult
    {
        if (!$this->isEnabled() || preg_match('#^/([^/]+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        $resolvedThreadId = ThreadRepository::byId($this->pdo, $matches[1]) !== null ? $matches[1] : null;
        if ($resolvedThreadId === null && ctype_digit($matches[1])) {
            $resolvedThreadId = QdbQuoteNumbers::resolve($this->pdo, (int) $matches[1]);
        }

        return $resolvedThreadId === null
            ? null
            : QdbExperienceRouteResult::redirect('/threads/' . $resolvedThreadId, 'Here is that quote.');
    }

    /**
     * @param array<string, mixed> $query
     */
    public function rejects(string $path, array $query): bool
    {
        if ($this->isEnabled() || !$this->isPrimaryRoutePath($path)) {
            return false;
        }

        if ($path !== '/' && $path !== '') {
            return true;
        }

        foreach (self::QUERY_KEYS as $key) {
            if (array_key_exists($key, $query)) {
                return true;
            }
        }

        foreach (array_keys($query) as $key) {
            if (!in_array($key, ['view', 'sort', 'format'], true) && ThreadRepository::byId($this->pdo, $key) !== null) {
                return true;
            }
        }

        return false;
    }

    public function rss(): string
    {
        return $this->boardPages->rss($this->boardPolicy);
    }

    private function welcome(): string
    {
        $threads = $this->eligibleQuotes();

        return $this->routeServices->renderPageTemplate('qdb_welcome.php', [
            'qdbQuoteCount' => $this->boardPolicy->quoteCount($threads),
            'recentThreads' => array_slice($threads, 0, 5),
        ], 'Welcome', 'welcome');
    }

    /** @param array<string, mixed> $query */
    private function add(array $query): string
    {
        $isQdbCompose = PresentationSlotRegistry::resolve(SiteProfileRegistry::active(), 'compose') === 'qdb';
        $data = [
            'boardTags' => 'general', 'subject' => '', 'body' => (string) ($query['body'] ?? ''), 'notice' => null, 'error' => null,
        ];

        return $this->routeServices->renderPageTemplate(
            $isQdbCompose ? 'qdb_add.php' : 'compose_thread.php',
            $data,
            $isQdbCompose ? 'Add Quote' : 'Compose Thread',
            'compose',
            $isQdbCompose
                ? ['/assets/openpgp_loader.js', '/assets/browser_signing.js']
                : ['/assets/openpgp_loader.js', '/assets/browser_signing.js', '/assets/outbox_store.js', '/assets/outbox_storage.js', '/assets/outbox_compose.js'],
        );
    }

    private function random(): string
    {
        $threads = $this->eligibleQuotes();
        shuffle($threads);
        $threads = array_slice($threads, 0, 10);

        return $this->renderQuotePage('qdb_random.php', ['threads' => $threads], 'Random', 'random', $threads);
    }

    private function search(string $term): string
    {
        $term = trim($term);
        $threads = $term === '' ? [] : array_values(array_filter(
            $this->eligibleQuotes(),
            static fn (array $thread): bool => stripos((string) $thread['root_post_body'], $term) !== false,
        ));

        return $this->renderQuotePage('qdb_search.php', ['threads' => $threads, 'term' => $term], 'Search', 'search', $threads);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $threads
     */
    private function renderQuotePage(string $template, array $data, string $title, string $section, array $threads): string
    {
        $reactions = $this->boardPolicy->viewerReactionState($threads);
        return $this->routeServices->renderPageTemplate($template, $data + [
            'viewerUpvotedThreadIds' => $reactions['upvoted'], 'viewerDownvotedThreadIds' => $reactions['downvoted'], 'viewerFlaggedPostIds' => $reactions['flagged'],
        ], $title, $section, ['/assets/thread_reactions.js']);
    }

    /** @return array<int, array<string, mixed>> */
    private function eligibleQuotes(): array
    {
        return $this->boardPolicy->eligibleQuotes(ThreadRepository::fetchThreads($this->pdo));
    }

    private function isPrimaryRoutePath(string $path): bool
    {
        return $path === '/' || $path === '' || in_array($path, ['/latest', '/top', '/leetness', '/add', '/random', '/search'], true)
            || preg_match('#^/(?:latest|top)/(\d+)/?$#', $path) === 1;
    }
}
