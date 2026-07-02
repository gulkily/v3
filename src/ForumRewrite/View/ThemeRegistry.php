<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class ThemeRegistry
{
    /**
     * @return list<array{name: string, label: string, mode: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'auto', 'label' => 'Auto/System', 'mode' => 'auto'],
            ['name' => 'light', 'label' => 'Light', 'mode' => 'light'],
            ['name' => 'dark', 'label' => 'Dark', 'mode' => 'dark'],
            ['name' => 'console', 'label' => 'Console', 'mode' => 'dark'],
            ['name' => 'lcd', 'label' => 'LCD', 'mode' => 'light'],
            ['name' => 'chicago', 'label' => 'Chicago', 'mode' => 'light'],
            ['name' => 'vapor', 'label' => 'Vapor', 'mode' => 'dark'],
            ['name' => 'forge', 'label' => 'Forge', 'mode' => 'dark'],
            ['name' => 'sticker', 'label' => 'Sticker', 'mode' => 'light'],
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
}
