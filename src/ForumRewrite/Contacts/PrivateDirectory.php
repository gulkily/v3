<?php

declare(strict_types=1);

namespace ForumRewrite\Contacts;

use ForumRewrite\Support\DeploymentSettings;
use RuntimeException;

final class PrivateDirectory
{
    public static function prepare(string $path, string $checkout): string
    {
        $path = self::absolute($path);
        if ($path === '/') {
            throw new RuntimeException('Contacts data directory must be dedicated to Contacts.');
        }
        for ($parent = $path; $parent !== '/'; $parent = dirname($parent)) {
            if (is_link($parent)) {
                throw new RuntimeException('Contacts data directory cannot contain symlinks.');
            }
        }
        $excluded = [$checkout];
        foreach (['FORUM_REPOSITORY_ROOT', 'FORUM_STATIC_HTML_ROOT', 'FORUM_PUBLIC_ARTIFACT_ROOT',
            'FORUM_IMAGE_ROOT', 'FORUM_PUBLISHED_IMAGE_ROOT', 'FORUM_DATABASE_PATH'] as $key) {
            $setting = DeploymentSettings::value($key, $checkout);
            if ($setting !== null) {
                $excluded[] = $key === 'FORUM_DATABASE_PATH' ? dirname($setting) : $setting;
            }
        }
        foreach ($excluded as $directory) {
            $directory = self::resolve($directory);
            if ($path === $directory || str_starts_with($path, $directory . '/') || str_starts_with($directory, $path . '/')) {
                throw new RuntimeException('Contacts data directory must be outside checkout, forum, and web directories.');
            }
        }
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Contacts data directory could not be created.');
        }
        if (!is_writable($path) || (fileperms($path) & 0777) !== 0700
            || (function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid())) {
            throw new RuntimeException('Contacts data directory must be writable and owner-only (0700).');
        }
        return $path;
    }

    private static function absolute(string $path): string
    {
        $parts = [];
        if (!str_starts_with($path, '/')) {
            $path = getcwd() . '/' . $path;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        return '/' . implode('/', $parts);
    }

    private static function resolve(string $path): string
    {
        $path = self::absolute($path);
        $suffix = '';
        while (($resolved = realpath($path)) === false) {
            $suffix = '/' . basename($path) . $suffix;
            $path = dirname($path);
        }
        return rtrim($resolved, '/') . $suffix;
    }
}
