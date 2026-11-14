<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class ThemeRegistry
{
    public const THEME_HINT_COOKIE = 'theme-hint';

    public const CONSOLE_PALETTES = ['green' => 'Green', 'amber' => 'Amber', 'cyan' => 'Cyan', 'white' => 'White'];
    public const LCD_PALETTES = ['green' => 'Green', 'amber' => 'Amber', 'purple' => 'Purple', 'gray' => 'Gray'];
    public const GLOSS_PALETTES = ['violet' => 'Violet', 'blue' => 'Blue', 'teal' => 'Teal', 'rose' => 'Rose'];

    /** Retired theme names mapped to their replacements, for saved preferences. */
    public const LEGACY_ALIASES = ['windark' => 'midnight'];

    /**
     * Each theme's version in the other mode (light to dark, dark to light),
     * for showing a theme's look on a device or for a reader in the other
     * mode. The mapping is one-way and need not be one to one: Word 97 and
     * Chicago both go to Midnight, which comes back to Word 97. A theme with
     * no entry has no counterpart and shows as it is. Nothing reads this yet;
     * a caller must still check that the counterpart is permitted on the site.
     */
    public const COUNTERPARTS = [
        'light' => 'dark',
        'dark' => 'light',
        'gloss' => 'glossdark',
        'glossdark' => 'gloss',
        'engagify' => 'glossdark',
        'whitehot' => 'thermal',
        'thermal' => 'whitehot',
        'word97' => 'midnight',
        'chicago' => 'midnight',
        'midnight' => 'word97',
        'lcd' => 'console',
        'console' => 'lcd',
        'sticker' => 'arena',
        'arena' => 'sticker',
    ];

    /**
     * A theme may set 'script' => true to ship /assets/theme-<name>.js, which
     * runs only while that theme is active, 'stylesheet' => '<other>' to
     * load /assets/theme-<other>.css instead of its own, and 'cycle' => false
     * to stay out of the cycle button while remaining in the menu, and
     * 'palettes' => [key => label] for a colour picker beside the theme menu;
     * the first key is the default (see the theme development guide).
     *
     * @return list<array{name: string, label: string, mode: string, script?: bool, stylesheet?: string, cycle?: bool, palettes?: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'auto', 'label' => 'Auto/System', 'mode' => 'auto'],
            ['name' => 'light', 'label' => 'Light', 'mode' => 'light'],
            ['name' => 'dark', 'label' => 'Dark', 'mode' => 'dark'],
            ['name' => 'console', 'label' => 'Console', 'mode' => 'dark', 'palettes' => self::CONSOLE_PALETTES],
            ['name' => 'lcd', 'label' => 'LCD', 'mode' => 'light', 'palettes' => self::LCD_PALETTES],
            ['name' => 'chicago', 'label' => 'Chicago', 'mode' => 'light'],
            ['name' => 'vapor', 'label' => 'Vapor', 'mode' => 'dark'],
            ['name' => 'forge', 'label' => 'Forge', 'mode' => 'dark'],
            ['name' => 'sticker', 'label' => 'Sticker', 'mode' => 'light'],
            ['name' => 'bubblegum', 'label' => 'Bubblegum', 'mode' => 'light'],
            ['name' => 'arena', 'label' => 'Arena', 'mode' => 'dark'],
            ['name' => 'thermal', 'label' => 'Thermal', 'mode' => 'dark'],
            ['name' => 'whitehot', 'label' => 'Whitehot', 'mode' => 'light'],
            ['name' => 'word97', 'label' => 'Word 97', 'mode' => 'light'],
            ['name' => 'midnight', 'label' => 'Midnight', 'mode' => 'dark'],
            ['name' => 'gloss', 'label' => 'Gloss', 'mode' => 'light', 'palettes' => self::GLOSS_PALETTES],
            ['name' => 'glossdark', 'label' => 'Gloss Dark', 'mode' => 'dark', 'stylesheet' => 'gloss', 'palettes' => self::GLOSS_PALETTES],
            ['name' => 'engagify', 'label' => 'Engagify', 'mode' => 'light', 'script' => true, 'stylesheet' => 'gloss', 'cycle' => false],
            ['name' => 'chouse', 'label' => 'Chouse', 'mode' => 'dark'],
            ['name' => 'qdb', 'label' => 'QDB', 'mode' => 'light'],
            ['name' => 'mitrapclub', 'label' => 'MIT Rap Club', 'mode' => 'dark'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function explicitNames(): array
    {
        $names = [];
        foreach (self::all() as $theme) {
            if ($theme['name'] === 'auto') {
                continue;
            }
            $names[] = $theme['name'];
        }

        return $names;
    }

    /** The theme's counterpart in the other mode, or null when it has none. */
    public static function counterpart(string $name): ?string
    {
        return self::COUNTERPARTS[$name] ?? null;
    }

    /**
     * Lays the theme menu out in Light and Dark columns. Each row pairs a
     * theme with its counterpart in the other mode when the mapping is mutual
     * and both are in $themes. Other themes follow under a divider, packed by
     * mode. Auto spans both columns. A list whose themes are all one mode
     * keeps the single list ('columns' => false). Every theme carries 'order',
     * its position in $themes, so the cycle button keeps the list order.
     *
     * @param list<array{name: string, label: string, mode: string}> $themes
     * @return array{columns: bool, auto: ?array, rows: list<array{light: ?array, dark: ?array}>, unpaired: list<array{light: ?array, dark: ?array}>}
     */
    public static function menuLayout(array $themes): array
    {
        $byName = [];
        $auto = null;
        foreach (array_values($themes) as $order => $theme) {
            $theme['order'] = $order;
            if ($theme['name'] === 'auto') {
                $auto = $theme;
                continue;
            }
            $byName[$theme['name']] = $theme;
        }

        $modes = array_unique(array_map(static fn (array $theme): string => $theme['mode'], $byName));
        if (!in_array('light', $modes, true) || !in_array('dark', $modes, true)) {
            return ['columns' => false, 'auto' => $auto, 'rows' => [], 'unpaired' => []];
        }

        $rows = [];
        $placed = [];
        foreach ($byName as $name => $theme) {
            if (isset($placed[$name])) {
                continue;
            }
            $other = self::counterpart($name);
            if ($other === null || !isset($byName[$other]) || isset($placed[$other])
                || self::counterpart($other) !== $name || $byName[$other]['mode'] === $theme['mode']) {
                continue;
            }
            $row = ['light' => null, 'dark' => null];
            $row[$theme['mode']] = $theme;
            $row[$byName[$other]['mode']] = $byName[$other];
            $rows[] = $row;
            $placed[$name] = true;
            $placed[$other] = true;
        }

        $loose = ['light' => [], 'dark' => []];
        foreach ($byName as $name => $theme) {
            if (!isset($placed[$name])) {
                $loose[$theme['mode'] === 'dark' ? 'dark' : 'light'][] = $theme;
            }
        }
        $unpaired = [];
        $count = max(count($loose['light']), count($loose['dark']));
        for ($i = 0; $i < $count; $i++) {
            $unpaired[] = ['light' => $loose['light'][$i] ?? null, 'dark' => $loose['dark'][$i] ?? null];
        }

        return ['columns' => true, 'auto' => $auto, 'rows' => $rows, 'unpaired' => $unpaired];
    }

    public static function isKnownName(string $name): bool
    {
        foreach (self::all() as $theme) {
            if ($theme['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $names
     * @return list<array{name: string, label: string, mode: string}>
     */
    public static function permitted(array $names): array
    {
        $allowed = array_fill_keys($names, true);

        return array_values(array_filter(
            self::all(),
            static fn (array $theme): bool => isset($allowed[$theme['name']]),
        ));
    }

    public static function isExplicitName(string $name): bool
    {
        return in_array($name, self::explicitNames(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function stylesheetPaths(): array
    {
        $paths = [];
        foreach (self::all() as $theme) {
            if ($theme['name'] === 'auto') {
                continue;
            }
            $paths[$theme['name']] = '/assets/theme-' . ($theme['stylesheet'] ?? $theme['name']) . '.css';
        }

        return $paths;
    }

    /**
     * Colour palettes, keyed by theme name, for themes that declare them.
     *
     * @return array<string, array{label: string, palettes: array<string, string>}>
     */
    public static function palettes(): array
    {
        $palettes = [];
        foreach (self::all() as $theme) {
            if (isset($theme['palettes'])) {
                $palettes[$theme['name']] = ['label' => $theme['label'], 'palettes' => $theme['palettes']];
            }
        }

        return $palettes;
    }

    /**
     * Theme scripts, keyed by theme name, for themes that declare one.
     *
     * @return array<string, string>
     */
    public static function scriptPaths(): array
    {
        $paths = [];
        foreach (self::all() as $theme) {
            if (($theme['script'] ?? false) === true && $theme['name'] !== 'auto') {
                $paths[$theme['name']] = '/assets/theme-' . $theme['name'] . '.js';
            }
        }

        return $paths;
    }
}
