<?php

declare(strict_types=1);

namespace ForumRewrite;

use InvalidArgumentException;

final class BrowserRuntimeProfile
{
    private const OFFLINE_CACHE_VERSION = 16;

    /**
     * @param array<string, mixed> $profile
     * @return array{namespace:string,themeStorageKey:string,threadDensityStorageKey:string,readingMarginsStorageKey:string,windowControlsStorageKey:string,openBesideStorageKey:string,openBesidePaneStorageKey:string,collapsedWindowsStorageKey:string,offlineCachePrefix:string,offlineCacheName:string,offlineDiagnosticKey:string,manifestName:string,manifestShortName:string}
     */
    public static function fromProfile(array $profile): array
    {
        $namespace = $profile['browserNamespace'] ?? null;
        $displayName = $profile['displayName'] ?? null;
        if (!is_string($namespace) || preg_match('/^[a-z][a-z0-9-]*$/', $namespace) !== 1 || !is_string($displayName) || $displayName === '') {
            throw new InvalidArgumentException('Profile requires a valid browser runtime identity.');
        }

        $offlineCachePrefix = $namespace . '-offline-reader-';
        // The installed app's name: the profile's appName, or the display name capitalized.
        $appName = is_string($profile['appName'] ?? null) && $profile['appName'] !== '' ? $profile['appName'] : ucfirst($displayName);

        return [
            'namespace' => $namespace,
            'themeStorageKey' => 'forum-' . $namespace . '-theme',
            'threadDensityStorageKey' => 'forum-' . $namespace . '-thread-density',
            'readingMarginsStorageKey' => 'forum-' . $namespace . '-reading-margins',
            // Window controls (theme-bubblegum.js): 'off' when turned off in Preferences; the posts collapsed in this browser.
            'windowControlsStorageKey' => 'forum-' . $namespace . '-window-controls',
            'collapsedWindowsStorageKey' => 'forum-' . $namespace . '-collapsed-windows',
            // Open beside (open_beside.js): 'on' when the reader turned the mode on; the page shown in the pane.
            'openBesideStorageKey' => 'forum-' . $namespace . '-open-beside',
            'openBesidePaneStorageKey' => 'forum-' . $namespace . '-open-beside-pane',
            'offlineCachePrefix' => $offlineCachePrefix,
            'offlineCacheName' => $offlineCachePrefix . 'v' . self::OFFLINE_CACHE_VERSION,
            'offlineDiagnosticKey' => $namespace . '-offline-registration-error',
            'manifestName' => $appName,
            'manifestShortName' => $appName,
        ];
    }
}
