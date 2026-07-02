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
