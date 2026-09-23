<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\ThemeRegistry;

final class ThemeRegistryTest
{
    public function testThemeNamesAreUnique(): void
    {
        $names = array_map(static fn (array $theme): string => $theme['name'], ThemeRegistry::all());

        assertSame($names, array_values(array_unique($names)));
    }

    public function testAutoIsFirstWithAutoMode(): void
    {
        $themes = ThemeRegistry::all();

        assertSame('auto', $themes[0]['name']);
        assertSame('auto', $themes[0]['mode']);
    }

    public function testEveryThemeHasLabelAndKnownMode(): void
    {
        foreach (ThemeRegistry::all() as $theme) {
            assertSame(true, $theme['label'] !== '');
            assertSame(true, in_array($theme['mode'], ['auto', 'light', 'dark'], true));
        }
    }

    public function testExplicitNamesAreAllNamesExceptAuto(): void
    {
        $allNames = array_map(static fn (array $theme): string => $theme['name'], ThemeRegistry::all());

        assertSame(
            array_values(array_filter($allNames, static fn (string $name): bool => $name !== 'auto')),
            ThemeRegistry::explicitNames()
        );
    }

    public function testExplicitThemeAssetsAndHintCookieHaveCanonicalContracts(): void
    {
        $paths = ThemeRegistry::stylesheetPaths();

        assertSame('theme-hint', ThemeRegistry::THEME_HINT_COOKIE);
        assertSame(ThemeRegistry::explicitNames(), array_keys($paths));
        assertSame(false, array_key_exists('auto', $paths));

        foreach ($paths as $name => $path) {
            assertSame(true, ThemeRegistry::isExplicitName($name));
            assertSame('/assets/theme-' . $name . '.css', $path);
        }

        assertSame(false, ThemeRegistry::isExplicitName('auto'));
        assertSame(false, ThemeRegistry::isExplicitName('unknown'));
    }

    public function testEveryExplicitThemeHasAnIsolatedStylesheet(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';
        $baseStyles = file_get_contents($publicRoot . '/assets/site.css');

        assertSame(true, $baseStyles !== false);
        assertSame(true, str_contains((string) $baseStyles, '/* critical-css-end */'));

        foreach (ThemeRegistry::stylesheetPaths() as $name => $path) {
            $styles = file_get_contents($publicRoot . $path);

            assertSame(true, $styles !== false);
            assertSame(true, str_contains((string) $styles, 'Theme: ' . $name));
            assertSame(false, str_contains((string) $baseStyles, ':root[data-theme="' . $name . '"]'));
            assertSame(false, str_contains((string) $baseStyles, 'html[data-theme="' . $name . '"]'));
            assertSame(false, str_contains((string) $baseStyles, '.theme-swatch[data-theme="' . $name . '"]'));
            assertSame(false, str_contains((string) $baseStyles, 'data-theme-option="' . $name . '"'));
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
