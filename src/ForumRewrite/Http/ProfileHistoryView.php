<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\AuthoredContentRepository;
use PDO;

/** The two public identity lists share visibility, but keep independent page numbers. */
final class ProfileHistoryView
{
    private const KINDS = ['threads', 'posts'];

    public static function build(PDO $pdo, array $profile, array $query): array
    {
        $history = [];
        $path = self::path($profile);
        foreach (self::KINDS as $kind) {
            $number = PageNavigation::pageNumber($query[$kind . '_page'] ?? null);
            $page = AuthoredContentRepository::visibleIdentityPage($pdo, $profile['identity_id'], $kind, $number);
            $links = PageNavigation::numbered($path, self::state($query, $kind), $number, $page['has_next'],
                $page['items'] === [], $kind . '_page', '#' . $kind);
            $history[$kind] = $page + ['total' => AuthoredContentRepository::countVisibleIdentity($pdo, $profile['identity_id'], $kind),
                'error' => null, 'pagination' => $links];
        }
        return $history;
    }

    /**
     * Where `with` lands: a thread opens in the Threads list, any other post in
     * the Posts list. Null when the ID is unusable or not in either list.
     */
    public static function withLocation(PDO $pdo, array $profile, array $query): ?string
    {
        $id = PageNavigation::withId($query['with'] ?? null);
        if ($id === null) return null;
        foreach (self::KINDS as $kind) {
            $page = AuthoredContentRepository::visibleIdentityPageOf($pdo, $profile['identity_id'], $kind, $id);
            if ($page === null) continue;
            return PageNavigation::withLocation(self::path($profile), self::state($query, $kind), $page, $id, $kind . '_page', $kind . '-post-');
        }
        return null;
    }

    private static function path(array $profile): string
    {
        return '/profiles/' . rawurlencode($profile['profile_slug']);
    }

    /** @return array<string,string> State kept in this list's links: self view and the other list's page. */
    private static function state(array $query, string $kind): array
    {
        $state = [];
        if (($query['self'] ?? '') === '1') $state['self'] = '1';
        $other = ($kind === 'threads' ? 'posts' : 'threads') . '_page';
        $otherPage = PageNavigation::pageNumber($query[$other] ?? null);
        if ($otherPage > 1) $state[$other] = (string) $otherPage;
        return $state;
    }
}
