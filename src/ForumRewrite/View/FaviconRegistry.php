<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\PresentationSlotRegistry;

final class FaviconRegistry
{
    /** @var array<string, string> */
    private const PATHS = [
        'default' => '/favicon.ico',
        'mitrapclub' => '/assets/favicon-mitrapclub.ico',
    ];

    /** @param array<string, mixed> $profile */
    public static function resolve(array $profile): string
    {
        $slotValue = PresentationSlotRegistry::resolve($profile, 'favicon');

        return self::PATHS[$slotValue];
    }
}
