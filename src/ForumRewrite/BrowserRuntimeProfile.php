<?php

declare(strict_types=1);

namespace ForumRewrite;

use InvalidArgumentException;

final class BrowserRuntimeProfile
{
    private const OFFLINE_CACHE_VERSION = 14;

    /**
     * @param array<string, mixed> $profile
     * @return array{namespace:string,themeStorageKey:string,threadDensityStorageKey:string,offlineCachePrefix:string,offlineCacheName:string,offlineDiagnosticKey:string,manifestName:string,manifestShortName:string}
     */
    public static function fromProfile(array $profile): array
    {
        $namespace = $profile['browserNamespace'] ?? null;
        $displayName = $profile['displayName'] ?? null;
        if (!is_string($namespace) || preg_match('/^[a-z][a-z0-9-]*$/', $namespace) !== 1 || !is_string($displayName) || $displayName === '') {
            throw new InvalidArgumentException('Profile requires a valid browser runtime identity.');
        }

        $offlineCachePrefix = $namespace . '-offline-reader-';

        return [
            'namespace' => $namespace,
            'themeStorageKey' => $namespace . '-theme',
            'threadDensityStorageKey' => $namespace . '-thread-density',
            'offlineCachePrefix' => $offlineCachePrefix,
            'offlineCacheName' => $offlineCachePrefix . 'v' . self::OFFLINE_CACHE_VERSION,
            'offlineDiagnosticKey' => $namespace . '-offline-registration-error',
            'manifestName' => ucfirst($displayName) . ' Offline Reading',
            'manifestShortName' => ucfirst($displayName),
        ];
    }
}
