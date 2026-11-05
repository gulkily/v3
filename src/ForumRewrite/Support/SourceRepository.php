<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Where this instance's application code lives, so agents can find it from
 * /llms.txt and the /api/ index instead of crawling site content.
 * FORUM_SOURCE_REPOSITORY_URL overrides the schema default; an empty value
 * hides the link. Only a plain https:// URL is published, because the value
 * is rendered into agent instructions.
 */
final class SourceRepository
{
    public const KEY = 'FORUM_SOURCE_REPOSITORY_URL';

    /** @param array<string, mixed> $privateConfig as returned by PrivateConfig::load() */
    public static function url(array $privateConfig): ?string
    {
        $value = array_key_exists(self::KEY, $privateConfig)
            ? $privateConfig[self::KEY]
            : PrivateConfigSchema::definitions()[self::KEY]['default'];

        return is_string($value) ? self::validated(trim($value)) : null;
    }

    public static function fromProjectRoot(string $projectRoot): ?string
    {
        return self::url(PrivateConfig::load($projectRoot));
    }

    private static function validated(string $value): ?string
    {
        if ($value === '' || preg_match('/[\s\x00-\x1f\x7f<>"`]/', $value) === 1) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return rtrim($value, '/');
    }
}
