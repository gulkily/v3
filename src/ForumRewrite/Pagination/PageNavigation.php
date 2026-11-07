<?php

declare(strict_types=1);

namespace ForumRewrite\Pagination;

/**
 * Native page links. Lists page with a plain number (`?page=2`); page 1 has no
 * parameter. Anything that is not a positive whole number reads as page 1.
 */
final class PageNavigation
{
    public const MAX_PAGE = 100000;

    /** The requested page; missing, malformed or out-of-range values read as 1. */
    public static function pageNumber(mixed $value): int
    {
        if (!is_string($value) || preg_match('/^[1-9][0-9]{0,5}$/D', $value) !== 1) return 1;
        return min((int) $value, self::MAX_PAGE);
    }

    /**
     * @param array<string,string> $query Non-default filter/order state; the page parameter is replaced.
     * @return array{previous_href:?string,next_href:?string,restart_href:?string}
     */
    public static function numbered(string $path, array $query, int $page, bool $hasNext, bool $restart = false, string $pageParameter = 'page', string $fragment = ''): array
    {
        unset($query[$pageParameter]);
        $url = static function (int $number) use ($path, $query, $pageParameter, $fragment): string {
            $parameters = $number > 1 ? $query + [$pageParameter => (string) $number] : $query;
            return $path . ($parameters === [] ? '' : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)) . $fragment;
        };
        return [
            'previous_href' => $page > 1 ? $url($page - 1) : null,
            'next_href' => $hasNext ? $url($page + 1) : null,
            'restart_href' => $restart && $page > 1 ? $url(1) : null,
        ];
    }

    /** Where `with` lands: the page holding the item, scrolled to its row. */
    public static function withLocation(string $path, array $query, int $page, string $id, string $pageParameter = 'page', string $anchorPrefix = 'post-'): string
    {
        unset($query[$pageParameter]);
        $parameters = $page > 1 ? $query + [$pageParameter => (string) $page] : $query;
        return $path . ($parameters === [] ? '' : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)) . '#' . $anchorPrefix . rawurlencode($id);
    }

    /** A post or thread ID given as `with`, or null when absent or unusable. */
    public static function withId(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 512 || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) return null;
        return $value;
    }
}
