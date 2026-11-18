<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\PresentationSlotRegistry;

final class FaviconRegistry
{
    /** @var array<string, string> */
    private const PATHS = [
        'default' => '/favicon.ico',
        'chouse' => '/assets/favicon-chouse.ico',
        'qdb' => '/assets/favicon-qdb.ico',
        'mitrapclub' => '/assets/favicon-mitrapclub.ico',
    ];

    /**
     * Home-screen icons drawn to match the favicon, by the same slot. Slots
     * without their own art keep the favicon-only manifest.
     *
     * @var array<string, array{touch: string, sizes: array<int, string>}>
     */
    private const APP_ICONS = [
        'default' => [
            'touch' => '/assets/app-icon-180.png',
            'sizes' => [192 => '/assets/app-icon-192.png', 512 => '/assets/app-icon-512.png'],
        ],
    ];

    /** @param array<string, mixed> $profile */
    public static function resolve(array $profile): string
    {
        $slotValue = PresentationSlotRegistry::resolve($profile, 'favicon');

        return self::PATHS[$slotValue];
    }

    /** @param array<string, mixed> $profile */
    public static function touchIcon(array $profile): ?string
    {
        return self::APP_ICONS[PresentationSlotRegistry::resolve($profile, 'favicon')]['touch'] ?? null;
    }

    /**
     * Manifest icon entries: the favicon, then the PNGs (the largest also as maskable;
     * the art keeps the mark inside the safe zone).
     *
     * @param array<string, mixed> $profile
     * @return list<array<string, string>>
     */
    public static function manifestIcons(array $profile): array
    {
        $icons = [['src' => self::resolve($profile), 'sizes' => '32x32', 'type' => 'image/x-icon']];
        $sizes = self::APP_ICONS[PresentationSlotRegistry::resolve($profile, 'favicon')]['sizes'] ?? [];
        foreach ($sizes as $size => $path) {
            $icons[] = ['src' => $path, 'sizes' => $size . 'x' . $size, 'type' => 'image/png'];
        }
        if ($sizes !== []) {
            $largest = max(array_keys($sizes));
            $icons[] = ['src' => $sizes[$largest], 'sizes' => $largest . 'x' . $largest, 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return $icons;
    }
}
