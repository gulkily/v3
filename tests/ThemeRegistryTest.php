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

    public function testPermittedThemesKeepRegistryOrderAndExcludeUnknownNames(): void
    {
        $themes = ThemeRegistry::permitted(['qdb', 'auto', 'light', 'unknown']);

        assertSame(['auto', 'light', 'qdb'], array_column($themes, 'name'));
        assertSame(true, ThemeRegistry::isKnownName('qdb'));
        assertSame(false, ThemeRegistry::isKnownName('unknown'));
    }

    public function testExplicitThemeAssetsAndHintCookieHaveCanonicalContracts(): void
    {
        $paths = ThemeRegistry::stylesheetPaths();

        assertSame('theme-hint', ThemeRegistry::THEME_HINT_COOKIE);
        assertSame(ThemeRegistry::explicitNames(), array_keys($paths));
        assertSame(false, array_key_exists('auto', $paths));

        $publicRoot = dirname(__DIR__) . '/public';
        $declared = array_column(ThemeRegistry::all(), 'stylesheet', 'name');
        foreach ($paths as $name => $path) {
            assertSame(true, ThemeRegistry::isExplicitName($name));
            // A theme may share another explicit theme's stylesheet.
            $owner = $declared[$name] ?? $name;
            assertSame(true, ThemeRegistry::isExplicitName($owner));
            assertSame(false, isset($declared[$owner]), $name . ' shares a stylesheet that is itself shared');
            assertSame('/assets/theme-' . $owner . '.css', $path);
            assertSame(true, is_file($publicRoot . $path));
        }

        assertSame(false, ThemeRegistry::isExplicitName('auto'));
        assertSame(false, ThemeRegistry::isExplicitName('unknown'));
    }

    public function testThemeScriptsBelongToExplicitThemesAndExist(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';

        foreach (ThemeRegistry::scriptPaths() as $name => $path) {
            assertSame(true, ThemeRegistry::isExplicitName($name));
            assertSame('/assets/theme-' . $name . '.js', $path);
            assertSame(true, is_file($publicRoot . $path));
        }

        foreach (ThemeRegistry::all() as $theme) {
            assertSame(
                ($theme['script'] ?? false) === true && $theme['name'] !== 'auto',
                array_key_exists($theme['name'], ThemeRegistry::scriptPaths()),
            );
        }
    }

    public function testLegacyThemeAliasesPointToCurrentExplicitThemes(): void
    {
        foreach (ThemeRegistry::LEGACY_ALIASES as $legacy => $current) {
            assertSame(false, ThemeRegistry::isKnownName($legacy));
            assertSame(true, ThemeRegistry::isExplicitName($current));
        }
    }

    public function testPalettesBelongToExplicitThemesThatShowTheirPicker(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';
        $palettes = ThemeRegistry::palettes();
        assertSame(['console', 'lcd', 'gloss', 'glossdark'], array_keys($palettes));
        foreach ($palettes as $name => $entry) {
            assertSame(true, ThemeRegistry::isExplicitName($name));
            assertSame(true, count($entry['palettes']) >= 2);
            $css = (string) file_get_contents($publicRoot . ThemeRegistry::stylesheetPaths()[$name]);
            // The theme's own stylesheet reveals its picker and styles every palette but the first.
            assertStringContains(':root[data-theme="' . $name . '"] [data-role="' . $name . '-palette"] { display: block; }', $css);
            foreach (array_slice(array_keys($entry['palettes']), 1) as $palette) {
                assertStringContains(':root[data-theme="' . $name . '"][data-' . $name . '-palette="' . $palette . '"]', $css);
            }
        }
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

    /**
     * Theme assets are loaded by names built from the registry, so a plain
     * search never finds them. This keeps every file accounted for, so a
     * dead-code pass can trust that a theme file on disk is in use.
     */
    public function testEveryThemeAssetOnDiskBelongsToTheRegistry(): void
    {
        $publicRoot = dirname(__DIR__) . '/public';
        // Shared by several themes and loaded directly by TemplateRenderer.
        $sharedAssets = ['/assets/theme-nav-icons.css'];
        $known = array_merge(
            array_values(ThemeRegistry::stylesheetPaths()),
            array_values(ThemeRegistry::scriptPaths()),
            $sharedAssets,
        );

        $unknown = [];
        foreach (glob($publicRoot . '/assets/theme-*.{css,js}', GLOB_BRACE) ?: [] as $file) {
            $path = '/assets/' . basename($file);
            if (!in_array($path, $known, true)) {
                $unknown[] = $path;
            }
        }

        assertSame([], $unknown);
    }

    public function testSystemDarkColorsAreInTheCriticalBlock(): void
    {
        // Auto/System theme sets no data-theme, so its dark colors come only from
        // site.css. If they sit after the critical marker, the inlined light
        // defaults paint first and dark-mode reloads flash white until the
        // async site.css arrives.
        $baseStyles = (string) file_get_contents(dirname(__DIR__) . '/public/assets/site.css');
        $endOffset = strpos($baseStyles, '/* critical-css-end */');
        assertSame(true, $endOffset !== false);

        $criticalCss = substr($baseStyles, 0, (int) $endOffset);
        assertSame(1, preg_match(
            '/@media \(prefers-color-scheme: dark\)\s*\{\s*:root:not\(\[data-theme\]\)\s*\{[^}]*--page-bg:/',
            $criticalCss
        ));
        assertSame(0, preg_match('/prefers-color-scheme: dark/', substr($baseStyles, (int) $endOffset)));
    }

    public function testQdbQuoteHeaderActionsHaveThemeNeutralCompactLayout(): void
    {
        $styles = file_get_contents(dirname(__DIR__) . '/public/assets/site.css');

        assertSame(true, $styles !== false);
        assertSame(1, preg_match(
            '/\\.quote-card-header-actions\\s*\\{[^}]*display:\\s*inline-flex;[^}]*flex-wrap:\\s*wrap;[^}]*max-width:\\s*100%;[^}]*\\}/s',
            (string) $styles,
        ));
        assertSame(1, preg_match(
            '/\\.quote-card-header-actions \\.thread-reaction-button\\s*\\{[^}]*width:\\s*auto;[^}]*margin-top:\\s*0;[^}]*\\}/s',
            (string) $styles,
        ));
        assertSame(1, preg_match(
            '/\\.quote-card-header-actions \\.thread-reaction-button\\.quote-card-vote-button\\[data-action\\]\\s*\\{[^}]*padding:\\s*0 0\\.25rem;[^}]*line-height:\\s*inherit;[^}]*\\}/s',
            (string) $styles,
        ));
    }

    public function testQdbDisabledVoteButtonsAreGrayAndOnlyTheChosenOneIsUnderlined(): void
    {
        $styles = file_get_contents(dirname(__DIR__) . '/public/assets/theme-qdb.css');

        assertSame(true, $styles !== false);
        assertSame(1, preg_match(
            '/:root\\[data-theme="qdb"\\] \\.quote-card-vote-button\\[disabled\\]\\s*\\{[^}]*color:\\s*#666666;[^}]*opacity:\\s*1;[^}]*\\}/s',
            (string) $styles,
        ));
        assertSame(1, preg_match(
            '/:root\\[data-theme="qdb"\\] \\.quote-card-vote-button\\[disabled\\]\\[data-vote-chosen\\]\\s*\\{[^}]*text-decoration:\\s*underline;[^}]*\\}/s',
            (string) $styles,
        ));
    }

    public function testQdbThemeHidesOnlyTheGenericBoardComposer(): void
    {
        $styles = file_get_contents(dirname(__DIR__) . '/public/assets/theme-qdb.css');

        assertSame(true, $styles !== false);
        assertSame(true, str_contains(
            (string) $styles,
            ':root[data-theme="qdb"] .thread-list > .card.compact-thread-compose {'
        ));
        assertSame(false, str_contains(
            (string) $styles,
            ':root[data-theme="qdb"] .thread-list > .card:not(.quote-card) {'
        ));
    }

    public function testCounterpartsAreKnownThemesInTheOtherMode(): void
    {
        $modes = array_column(ThemeRegistry::all(), 'mode', 'name');
        foreach (ThemeRegistry::COUNTERPARTS as $theme => $counterpart) {
            assertSame(true, isset($modes[$theme], $modes[$counterpart]));
            assertSame(true, in_array($modes[$theme], ['light', 'dark'], true));
            assertSame(true, $modes[$theme] !== $modes[$counterpart]);
        }
        assertSame('midnight', ThemeRegistry::counterpart('chicago'));
        assertSame('word97', ThemeRegistry::counterpart('midnight'));
        assertSame(null, ThemeRegistry::counterpart('vapor'));
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
