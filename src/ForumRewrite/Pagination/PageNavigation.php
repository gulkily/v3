<?php

declare(strict_types=1);

namespace ForumRewrite\Pagination;

use InvalidArgumentException;

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

    /** A post or thread ID given as `with`, or null when absent or unusable. */
    public static function withId(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 512 || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) return null;
        return $value;
    }

    /** @param array<string, string> $boundary */
    public static function encode(string $context, string $direction, array $boundary): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'version' => 1, 'context' => $context, 'direction' => $direction, 'boundary' => $boundary,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{direction:string,boundary:array<string,string>} */
    public static function decode(string $token, string $context): array
    {
        $invalid = new InvalidArgumentException('This page position is invalid.');
        if ($token === '' || strlen($token) > 8192 || preg_match('/^[A-Za-z0-9_-]+$/D', $token) !== 1) {
            throw $invalid;
        }
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        try {
            $value = $json === false ? null : json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw $invalid;
        }
        if (!is_array($value) || ($value['version'] ?? null) !== 1 || ($value['context'] ?? null) !== $context
            || !in_array($value['direction'] ?? null, ['next', 'previous'], true)
            || !is_array($value['boundary'] ?? null) || $value['boundary'] === [] || count($value['boundary']) > 16) {
            throw $invalid;
        }
        foreach ($value['boundary'] as $key => $part) {
            if (!is_string($key) || !is_string($part) || strlen($part) > 4096) throw $invalid;
        }
        return ['direction' => $value['direction'], 'boundary' => $value['boundary']];
    }

    /**
     * @param array<string,string> $query Filter/order state, excluding position.
     * @param array<string,string>|null $previous
     * @param array<string,string>|null $next
     * @return array{previous_href:?string,next_href:?string,restart_href:?string}
     */
    public static function links(string $path, array $query, string $context, ?array $previous, ?array $next, bool $restart = false, string $positionParameter = 'position'): array
    {
        unset($query[$positionParameter]);
        $url = static fn (array $parameters): string => $path . ($parameters === [] ? '' : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
        return [
            'previous_href' => $previous === null ? null : $url($query + [$positionParameter => self::encode($context, 'previous', $previous)]),
            'next_href' => $next === null ? null : $url($query + [$positionParameter => self::encode($context, 'next', $next)]),
            'restart_href' => $restart ? $url($query) : null,
        ];
    }
}
