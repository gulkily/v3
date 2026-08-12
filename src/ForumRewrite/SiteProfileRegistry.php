<?php

declare(strict_types=1);

namespace ForumRewrite;

final class SiteProfileRegistry
{
    private const DEFAULT_SITE_ID = 'zenmemes';

    /**
     * @return array<string, array{name: string, defaultTheme: string, composerPrompt: string}>
     */
    public static function all(): array
    {
        return [
            'zenmemes' => [
                'name' => 'zenmemes',
                'defaultTheme' => 'auto',
                'composerPrompt' => 'Start a thread...',
            ],
            'chouse' => [
                'name' => 'chouse',
                'defaultTheme' => 'auto',
                'composerPrompt' => 'Start a thread...',
            ],
        ];
    }

    /**
     * @return array{name: string, defaultTheme: string, composerPrompt: string}
     */
    public static function active(): array
    {
        $profiles = self::all();
        $siteId = getenv('FORUM_SITE_ID');

        if ($siteId === false || $siteId === '' || !isset($profiles[$siteId])) {
            $siteId = self::DEFAULT_SITE_ID;
        }

        return $profiles[$siteId];
    }
}
