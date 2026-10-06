<?php

declare(strict_types=1);

use ForumRewrite\View\ThemeRegistry;
use ForumRewrite\View\TemplateRenderer;

require __DIR__ . '/../autoload.php';

final class ProfileThemePresentationTest
{
    public function testThemeMenuAndEarlyThemeContractAreProfileBound(): void
    {
        $expectedThemes = [
            'zenmemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97'],
            'chouse' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'chouse'],
            'qdb' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'qdb'],
        ];
        $previousCookie = $_COOKIE;
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');

        try {
            foreach ($expectedThemes as $profileId => $themes) {
                putenv('FORUM_SITE_ID=' . $profileId);
                $_COOKIE = [];
                $html = $renderer->renderLayout('Theme', '<main></main>', 'board');

                preg_match_all('/<button\\b[^>]*\\bdata-theme-option="([^"]+)"/', $html, $matches);
                assertSame($themes, $matches[1]);
                assertSame(array_values(array_filter($themes, static fn (string $theme): bool => $theme !== 'auto')), $this->allowedThemes($html));
                assertSame($profileId === 'chouse', str_contains($html, 'data-theme-option="chouse"'));
                assertSame($profileId === 'qdb', str_contains($html, 'data-theme-option="qdb"'));
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

        try {
            putenv('FORUM_SITE_ID=zenmemes');
            $_COOKIE = [ThemeRegistry::THEME_HINT_COOKIE => 'qdb'];
            $zenmemesHtml = $renderer->renderLayout('Theme', '<main></main>', 'board');
            assertSame(true, str_contains($zenmemesHtml, 'data-default-theme="auto"'));
            assertSame(true, str_contains($zenmemesHtml, 'href="' . \ForumRewrite\Host\AssetFingerprint::fingerprintedPath($publicRoot, '/assets/theme-light.css') . '"'));
            assertSame(false, str_contains($zenmemesHtml, 'theme-qdb'));

            putenv('FORUM_SITE_ID=qdb');
            $_COOKIE = [ThemeRegistry::THEME_HINT_COOKIE => 'chouse'];
            $qdbHtml = $renderer->renderLayout('Theme', '<main></main>', 'board');
            assertSame(true, str_contains($qdbHtml, 'data-default-theme="qdb"'));
            assertSame(true, str_contains($qdbHtml, 'href="' . \ForumRewrite\Host\AssetFingerprint::fingerprintedPath($publicRoot, '/assets/theme-qdb.css') . '"'));
            assertSame(false, str_contains($qdbHtml, 'theme-chouse'));

            $script = file_get_contents($publicRoot . '/assets/theme_toggle.js');
            assertSame(true, $script !== false);
            assertSame(true, str_contains((string) $script, 'var storageKey = "zenmemes-theme";'));
            assertSame(true, str_contains((string) $script, 'return themes.indexOf(storedTheme) === -1 ? defaultTheme() : storedTheme;'));
        } finally {
            $_COOKIE = $previousCookie;
            putenv('FORUM_SITE_ID');
        }
    }

    /** @return list<string> */
    private function allowedThemes(string $html): array
    {
        preg_match('/var allowed = (\[[^;]+\]);/', $html, $matches);

        return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    }
}
