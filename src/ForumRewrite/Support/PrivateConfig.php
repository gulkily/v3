<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class PrivateConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $projectRoot): array
    {
        $config = [];
        foreach (self::candidatePaths($projectRoot) as $path) {
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $loaded = require $path;
            if (is_array($loaded)) {
                foreach ($loaded as $key => $value) {
                    if (is_string($key)) {
                        $config[$key] = $value;
                    }
                }
            }
        }

        foreach (PrivateConfigSchema::environmentKeys() as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * @return string[]
     */
    /** @return array<string, mixed> */
    public static function fileValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException('Private config must return an array.');
        }
        return array_filter($values, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** @return array{file:array<string,mixed>,revision:string} */
    public static function snapshot(string $path): array
    {
        clearstatcache(true, $path);
        $bytes = is_file($path) ? file_get_contents($path) : null;
        if ($bytes === false) {
            throw new \RuntimeException('Cannot read private configuration.');
        }
        $file = self::fileValues($path);
        clearstatcache(true, $path);
        $after = is_file($path) ? file_get_contents($path) : null;
        if ($bytes !== $after) {
            throw new \RuntimeException('Private configuration changed while reading; retry.');
        }
        $effective = [];
        foreach (PrivateConfigSchema::resolve($file) as $key => $field) {
            if (in_array($key, PrivateConfigSchema::llmEditableKeys(), true)) {
                $effective[$key] = [$field['value'], $field['source']];
            }
        }
        return ['file' => $file, 'revision' => hash('sha256', serialize([$path, $bytes, $effective]))];
    }

    private static function candidatePaths(string $projectRoot): array
    {
        $paths = [];
        $explicitPath = getenv('FORUM_SECRETS_PATH');
        if ($explicitPath !== false && trim($explicitPath) !== '') {
            return [$explicitPath];
        }

        $paths[] = dirname($projectRoot) . '/forum-private/secrets.php';

        return array_values(array_unique($paths));
    }
}
