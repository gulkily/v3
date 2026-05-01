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

        foreach (['DEDALUS_API_KEY', 'DEDALUS_API_BASE_URL', 'DEDALUS_MODEL', 'DEDALUS_TIMEOUT_SECONDS', 'DEDALUS_ANALYSIS_MODE'] as $key) {
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
