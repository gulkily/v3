<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\BrowserRuntimeProfile;
use ForumRewrite\View\FaviconRegistry;
use RuntimeException;

final class BrowserRuntimeAssetRenderer
{
    /** @param array<string, mixed> $profile */
    public static function manifest(array $profile): string
    {
        $runtime = BrowserRuntimeProfile::fromProfile($profile);

        return json_encode([
            // The id names the installed app; it keeps its first value so existing installs
            // update in place instead of becoming a second app.
            'id' => '/offline/?site=' . $runtime['namespace'],
            'name' => $runtime['manifestName'],
            'short_name' => $runtime['manifestShortName'],
            // The installed app opens the home page; offline, the worker answers it with the reader.
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f4f1e8',
            'theme_color' => '#f4f1e8',
            'icons' => FaviconRegistry::manifestIcons($profile),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @param array<string, mixed> $profile */
    public static function serviceWorker(string $source, array $profile): string
    {
        if ($source === '') {
            throw new RuntimeException('Offline worker source is empty.');
        }

        $runtime = BrowserRuntimeProfile::fromProfile($profile);

        return 'self.__forumBrowserRuntime = ' . json_encode($runtime, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ";\n" . $source;
    }
}
