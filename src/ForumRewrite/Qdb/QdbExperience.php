<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\Http\BoardPageController;
use ForumRewrite\Http\ComposeAndAccountKeyController;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\SiteProfileRegistry;
use PDO;

final class QdbExperience
{
    private const QUERY_KEYS = ['latest', 'top', 'leetness', 'add', 'random', 'search'];

    public function __construct(
        private readonly BoardPageController $boardPages,
        private readonly ComposeAndAccountKeyController $composePages,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function matches(string $path, array $query): bool
    {
        return $this->isEnabled() && $this->isPrimaryRoutePath($path);
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

            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'newest', 'latest', max(1, $page)));
        }

        if ($path === '/top' || $topPageMatch || array_key_exists('top', $query)) {
            $page = $topPageMatch ? (int) $topPathMatches[1] : (int) ($query['top'] ?? 1);

            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'top', 'top', max(1, $page)));
        }

        if ($path === '/leetness' || array_key_exists('leetness', $query)) {
            return QdbExperienceRouteResult::page($this->boardPages->board('all', 'leetness', 'leetness'));
        }

        if ($path === '/add' || array_key_exists('add', $query)) {
            return QdbExperienceRouteResult::page($this->composePages->composeThreadCompact($query));
        }

        if ($path === '/random' || array_key_exists('random', $query)) {
            return QdbExperienceRouteResult::page($this->boardPages->random());
        }

        if ($path === '/search' || array_key_exists('search', $query)) {
            return QdbExperienceRouteResult::page($this->boardPages->search((string) ($query['search'] ?? '')));
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
            return QdbExperienceRouteResult::page($this->boardPages->welcome());
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

    private function isEnabled(): bool
    {
        return in_array('qdb', SiteProfileRegistry::active()['enabledExperienceKeys'], true);
    }

    private function isPrimaryRoutePath(string $path): bool
    {
        return $path === '/' || $path === '' || in_array($path, ['/latest', '/top', '/leetness', '/add', '/random', '/search'], true)
            || preg_match('#^/(?:latest|top)/(\d+)/?$#', $path) === 1;
    }
}
