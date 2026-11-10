<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

/**
 * Site identity and instance paths shared by the web app, CLI, cron and
 * child processes. Precedence: environment variable, then the checkout's
 * private file (PrivateConfig::path()), then the caller's own default.
 */
final class DeploymentSettings
{
    public const KEYS = [
        'FORUM_SITE_ID',
        'FORUM_REPOSITORY_ROOT',
        'FORUM_DATABASE_PATH',
        'FORUM_STATIC_HTML_ROOT',
        'FORUM_PUBLIC_ARTIFACT_ROOT',
        'FORUM_IMAGE_ROOT',
    ];

    /** @var array<string, array<string, string>> */
    private static array $fileCache = [];

    /**
     * The configured value, or null when neither the environment nor the
     * private file sets it (the caller then applies its default).
     */
    public static function value(string $key, ?string $projectRoot = null): ?string
    {
        self::assertKey($key);
        $environment = getenv($key);
        if ($environment !== false && $environment !== '') {
            return $environment;
        }

        return self::fileValues($projectRoot)[$key] ?? null;
    }

    /**
     * Where value() found the setting: "environment", "private file <path>" or "default".
     */
    public static function source(string $key, ?string $projectRoot = null): string
    {
        self::assertKey($key);
        $environment = getenv($key);
        if ($environment !== false && $environment !== '') {
            return 'environment';
        }

        return isset(self::fileValues($projectRoot)[$key])
            ? 'private file ' . PrivateConfig::path(self::root($projectRoot))
            : 'default';
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @internal Test hook: clears the cached settings files. */
    public static function resetForTesting(): void
    {
        self::$fileCache = [];
    }

    /** @return array<string, string> */
    private static function fileValues(?string $projectRoot): array
    {
        $path = PrivateConfig::path(self::root($projectRoot));
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return [];
        }
        $cacheKey = $path . "\0" . (string) filemtime($path) . "\0" . (string) filesize($path);
        if (isset(self::$fileCache[$cacheKey])) {
            return self::$fileCache[$cacheKey];
        }
        if (!is_readable($path)) {
            throw new RuntimeException("Private configuration is not readable: {$path}");
        }

        $values = [];
        foreach (PrivateConfig::fileValues($path) as $key => $value) {
            if (!in_array($key, self::KEYS, true) || $value === null || $value === '') {
                continue;
            }
            if (!is_string($value)) {
                throw new RuntimeException("{$key} in {$path} must be a string.");
            }
            if ($key !== 'FORUM_SITE_ID' && !str_starts_with($value, '/')) {
                throw new RuntimeException("{$key} in {$path} must be an absolute path.");
            }
            $values[$key] = $value;
        }

        return self::$fileCache[$cacheKey] = $values;
    }

    private static function root(?string $projectRoot): string
    {
        return $projectRoot ?? self::projectRoot();
    }

    private static function assertKey(string $key): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException('Not a deployment setting: ' . $key);
        }
    }
}
