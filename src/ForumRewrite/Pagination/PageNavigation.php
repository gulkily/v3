<?php

declare(strict_types=1);

namespace ForumRewrite\Pagination;

use InvalidArgumentException;

/** Native page links; boundaries belong to the calling list's query adapter. */
final class PageNavigation
{
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
