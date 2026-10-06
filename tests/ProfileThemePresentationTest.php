<?php

declare(strict_types=1);

use ForumRewrite\View\ThemeRegistry;
use ForumRewrite\View\TemplateRenderer;

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ProfileRegressionContract.php';
require_once __DIR__ . '/Support/ProfileRegressionFixture.php';

final class ProfileThemePresentationTest
{
    public function testThemeMenuAndEarlyThemeContractAreProfileBound(): void
    {
        $previousCookie = $_COOKIE;
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');

        try {
            foreach (ProfileRegressionContract::all() as $profileId => $contract) {
                $themes = $contract['profile']['permittedThemes'];
                putenv('FORUM_SITE_ID=' . $profileId);
                $_COOKIE = [];
                $html = $renderer->renderLayout('Theme', '<main></main>', 'board');

                preg_match_all('/<button\\b[^>]*\\bdata-theme-option="([^"]+)"/', $html, $matches);
                assertSame($themes, $matches[1]);
                assertSame(array_values(array_filter($themes, static fn (string $theme): bool => $theme !== 'auto')), $this->allowedThemes($html));
                $brandedTheme = $contract['presentation']['brandedTheme'];
                assertSame($brandedTheme !== null, str_contains($html, 'data-theme-option="' . ($brandedTheme ?? 'no-branded-theme') . '"'));
            }
        } finally {
            $_COOKIE = $previousCookie;
            putenv('FORUM_SITE_ID');
        }
    }

    public function testUnavailableSavedThemeUsesTheProfileDefaultWithoutChangingStorageKeys(): void
    {
        $previousCookie = $_COOKIE;
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $publicRoot = dirname(__DIR__) . '/public';
        $themeNames = array_column(ThemeRegistry::all(), 'name');

        try {
            foreach (ProfileRegressionContract::all() as $profileId => $contract) {
                $profile = $contract['profile'];
                $unavailableTheme = current(array_values(array_diff($themeNames, $profile['permittedThemes'])));
                assertSame(true, is_string($unavailableTheme));
                putenv('FORUM_SITE_ID=' . $profileId);
                $_COOKIE = [ThemeRegistry::THEME_HINT_COOKIE => $unavailableTheme];
                $html = $renderer->renderLayout('Theme', '<main></main>', 'board');
                $initialTheme = $profile['defaultTheme'] === 'auto' ? 'light' : $profile['defaultTheme'];

                assertSame(true, str_contains($html, 'data-default-theme="' . $profile['defaultTheme'] . '"'));
                assertSame(true, str_contains($html, 'href="' . \ForumRewrite\Host\AssetFingerprint::fingerprintedPath($publicRoot, '/assets/theme-' . $initialTheme . '.css') . '"'));
                assertSame(false, str_contains($html, 'theme-' . $unavailableTheme));
                assertSame(true, str_contains($html, '"themeStorageKey":"' . $contract['runtime']['themeStorageKey'] . '"'));
            }

            $script = file_get_contents($publicRoot . '/assets/theme_toggle.js');
            assertSame(true, $script !== false);
            assertSame(true, str_contains((string) $script, 'var storageKey = runtime && runtime.themeStorageKey;'));
            assertSame(true, str_contains((string) $script, 'return themes.indexOf(storedTheme) === -1 ? defaultTheme() : storedTheme;'));
        } finally {
            $_COOKIE = $previousCookie;
            putenv('FORUM_SITE_ID');
        }
    }

    public function testFourthProfileFixtureUsesTheSameThemeMatrix(): void
    {
        ProfileRegressionFixture::withFourthProfile(function (): void {
            $this->testThemeMenuAndEarlyThemeContractAreProfileBound();
            $this->testUnavailableSavedThemeUsesTheProfileDefaultWithoutChangingStorageKeys();
        });
    }

    public function testLargeFragmentsSkipCosmeticIndentation(): void
    {
        $templateRoot = sys_get_temp_dir() . '/forum-rewrite-template-renderer-' . bin2hex(random_bytes(6));
        mkdir($templateRoot, 0777, true);
        $templatePath = $templateRoot . '/fragment.php';
        file_put_contents($templatePath, '<?= $indent($html, 2) ?>');
        $html = str_repeat("large fragment line\n", 60000);

        try {
            $rendered = (new TemplateRenderer($templateRoot))->renderFragment('fragment.php', ['html' => $html]);

            assertSame($html, $rendered);
        } finally {
            @unlink($templatePath);
            @rmdir($templateRoot);
        }
    }

    /** @return list<string> */
    private function allowedThemes(string $html): array
    {
        preg_match('/var allowed = (\[[^;]+\]);/', $html, $matches);

        return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }
}
