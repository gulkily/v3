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

    /**
     * iPhone launch images (owa_instant_launch): the app icon on the manifest background,
     * shown from a tap on the home-screen icon until the first page paints. iPhone uses one
     * only when it matches the screen exactly, so each entry is a portrait screen size in CSS
     * pixels and its pixel ratio. Slots without home-screen icon art get none.
     */
    private const LAUNCH_SCREENS = [
        [375, 667, 2], [414, 736, 3], [375, 812, 3], [414, 896, 2], [414, 896, 3], [390, 844, 3],
        [428, 926, 3], [393, 852, 3], [430, 932, 3], [402, 874, 3], [440, 956, 3],
    ];

    /**
     * @param array<string, mixed> $profile
     * @return list<array{href: string, media: string}>
     */
    public static function launchImages(array $profile): array
    {
        if (!isset(self::APP_ICONS[PresentationSlotRegistry::resolve($profile, 'favicon')])) {
            return [];
        }
        $images = [];
        foreach (self::LAUNCH_SCREENS as [$width, $height, $ratio]) {
            $images[] = [
                'href' => '/assets/launch/launch-' . ($width * $ratio) . 'x' . ($height * $ratio) . '.png',
                'media' => '(device-width: ' . $width . 'px) and (device-height: ' . $height . 'px) and (-webkit-device-pixel-ratio: ' . $ratio . ') and (orientation: portrait)',
            ];
        }

        return $images;
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
