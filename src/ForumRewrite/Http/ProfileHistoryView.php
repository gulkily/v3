<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\AuthoredContentRepository;
use PDO;

/** The two public identity lists share visibility, but keep independent positions. */
final class ProfileHistoryView
{
    public static function build(PDO $pdo, array $profile, array $query): array
    {
        $history = [];
        $path = '/profiles/' . rawurlencode($profile['profile_slug']);
        foreach (['threads', 'posts'] as $kind) {
            $parameter = $kind . '_position';
            $other = ($kind === 'threads' ? 'posts' : 'threads') . '_position';
            $context = 'profile:' . $profile['identity_id'] . ':' . $kind . ':created:desc';
            $position = $query[$parameter] ?? null;
            $error = null;
            try {
                if ($position !== null && !is_string($position)) throw new \InvalidArgumentException('This page position is invalid.');
                $cursor = $position === null ? null : PageNavigation::decode($position, $context);
                $page = AuthoredContentRepository::visibleIdentityPage($pdo, $profile['identity_id'], $kind, $cursor);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
                $page = ['items' => [], 'previous' => null, 'next' => null];
            }
            $state = [];
            if (($query['self'] ?? '') === '1') $state['self'] = '1';
            if (is_string($query[$other] ?? null) && strlen($query[$other]) <= 8192) $state[$other] = $query[$other];
            $links = PageNavigation::links($path, $state, $context, $page['previous'], $page['next'],
                $error !== null || ($position !== null && $page['items'] === []), $parameter);
            foreach ($links as &$href) if ($href !== null) $href .= '#' . $kind;
            unset($href);
            $history[$kind] = $page + ['total' => AuthoredContentRepository::countVisibleIdentity($pdo, $profile['identity_id'], $kind),
                'error' => $error, 'pagination' => $links];
        }
        return $history;
    }
}
