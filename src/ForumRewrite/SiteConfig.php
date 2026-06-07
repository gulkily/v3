<?php

declare(strict_types=1);

namespace ForumRewrite;

final class SiteConfig
{
    public const SITE_NAME = 'zenmemes';

    public static function unicodeAuthoredTextEnabled(): bool
    {
        return self::envFlagEnabled('FORUM_UNICODE_AUTHORED_TEXT', false);
    }

    public static function appVersionNotificationEnabled(): bool
    {
        return self::envFlagEnabled('FORUM_APP_VERSION_NOTIFICATION', true);
    }

    private static function envFlagEnabled(string $name, bool $default): bool
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
