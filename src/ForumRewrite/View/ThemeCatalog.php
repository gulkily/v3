<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\SiteProfileRegistry;

/**
 * The /themes catalog: every explicit theme this site permits, each with the
 * custom properties its stylesheet gives the theme menu's option for that theme
 * (`.theme-menu__option[data-theme-option="<name>"]`). Those blocks already exist
 * so the menu can preview a theme without loading it; the catalog reuses them, so
 * a card shows a theme's own colours and fonts while only the active theme's
 * stylesheet is loaded.
 */
final class ThemeCatalog
{
    /**
     * @return list<array{name: string, label: string, mode: string, palettes: list<string>, properties: array<string, string>}>
     */
    public static function entries(string $projectRoot): array
    {
        $entries = [];
        foreach (ThemeRegistry::permitted(SiteProfileRegistry::active()['permittedThemes']) as $theme) {
            if (!ThemeRegistry::isExplicitName($theme['name'])) {
                continue;
            }
            $stylesheet = $projectRoot . '/public/assets/theme-' . ($theme['stylesheet'] ?? $theme['name']) . '.css';
            $css = is_file($stylesheet) ? (string) file_get_contents($stylesheet) : '';
            $entries[] = [
                'name' => $theme['name'],
                'label' => $theme['label'],
                'mode' => $theme['mode'],
                'palettes' => array_values($theme['palettes'] ?? []),
                'properties' => self::previewProperties($css, $theme['name']),
            ];
        }

        return $entries;
    }

    /**
     * Custom properties declared in rules whose selector list includes the theme
     * menu option for $name; later declarations win, as in the cascade.
     *
     * @return array<string, string>
     */
    public static function previewProperties(string $css, string $name): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $option = '.theme-menu__option[data-theme-option="' . $name . '"]';
        $properties = [];
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        foreach ($rules as $rule) {
            $selectors = array_map('trim', explode(',', $rule[1]));
            if (!in_array($option, $selectors, true)) {
                continue;
            }
            preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);?/i', $rule[2], $declarations, PREG_SET_ORDER);
            foreach ($declarations as $declaration) {
                $value = trim(preg_replace('/\s+/', ' ', $declaration[2]) ?? '');
                if ($value !== '' && strpbrk($value, '<>{}') === false) {
                    $properties[$declaration[1]] = $value;
                }
            }
        }

        return $properties;
    }

    /** The properties as one inline style declaration list. */
    public static function style(array $properties): string
    {
        $parts = [];
        foreach ($properties as $property => $value) {
            $parts[] = $property . ': ' . $value;
        }

        return implode('; ', $parts);
    }
}
