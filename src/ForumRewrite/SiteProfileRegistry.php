<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\View\ThemeRegistry;

final class SiteProfileRegistry
{
    private const DEFAULT_SITE_ID = 'zenmemes';

    /** @var null|array<string, array<string, mixed>> */
    private static ?array $profilesForTesting = null;

    /**
     * @return array<string, array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string}>
     */
    public static function all(): array
    {
        $profiles = self::$profilesForTesting ?? [
            'zenmemes' => [
                'name' => 'zenmemes',
                'displayName' => 'zenmemes',
                'defaultTheme' => 'auto',
                // What Auto/System shows for a light or dark device setting.
                'autoThemes' => ['light' => 'gloss', 'dark' => 'glossdark'],
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'gloss', 'glossdark', 'engagify'],
                'browserNamespace' => 'zenmemes',
                'editorialContentKey' => 'zenmemes',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                // Page themes: a page path and the theme it shows by default.
                'pageThemes' => [
                    // "Our cookie notice (there isn't one)"
                    '/threads/thread-20261102152258-25e82c97' => 'engagify',
                ],
                'presentationSlots' => ['navigation' => 'app', 'boardCard' => 'list', 'compose' => 'thread', 'about' => 'default', 'editorial' => 'zenmemes', 'brandedStylesheet' => 'site'],
            ],
            'chouse' => [
                'name' => 'chouse',
                'displayName' => 'chouse',
                'defaultTheme' => 'chouse',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'chouse'],
                'browserNamespace' => 'chouse',
                'editorialContentKey' => 'chouse',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'chouse', 'editorial' => 'boston', 'brandedStylesheet' => 'chouse', 'favicon' => 'chouse'],
            ],
            'qdb' => [
                'name' => 'qdb',
                'displayName' => 'qdb',
                'defaultTheme' => 'qdb',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'qdb'],
                'browserNamespace' => 'qdb',
                'editorialContentKey' => 'qdb',
                'enabledExperienceKeys' => ['qdb'],
                'composerPrompt' => 'Submit a quote...',
                'presentationSlots' => ['navigation' => 'qdb', 'boardCard' => 'quote', 'compose' => 'qdb', 'about' => 'default', 'editorial' => 'qdb', 'brandedStylesheet' => 'qdb', 'favicon' => 'qdb'],
            ],
            'mitrapclub' => [
                'name' => 'mitrapclub',
                'displayName' => 'MIT Rap Club',
                'defaultTheme' => 'mitrapclub',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'mitrapclub'],
                'browserNamespace' => 'mitrapclub',
                'editorialContentKey' => 'mitrapclub',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Drop a verse, a cypher clip, or an announcement...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'default', 'editorial' => 'mitrapclub', 'brandedStylesheet' => 'mitrapclub', 'toolsNav' => 'hidden', 'favicon' => 'mitrapclub'],
            ],
        ];

        self::validate($profiles);

        return $profiles;
    }

    /**
     * @internal Test hook: replaces the loaded profiles; null restores the real ones.
     * @param null|array<string, array<string, mixed>> $profiles
     */
    public static function setProfilesForTesting(?array $profiles): void
    {
        if ($profiles !== null) {
            self::validate($profiles);
        }

        self::$profilesForTesting = $profiles;
    }

    /**
     * @return array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string}
     */
    public static function active(): array
    {
        $profiles = self::all();
        $siteId = \ForumRewrite\Support\DeploymentSettings::value('FORUM_SITE_ID');
        if ($siteId !== null && !isset($profiles[$siteId])
            && str_starts_with(\ForumRewrite\Support\DeploymentSettings::source('FORUM_SITE_ID'), 'private file')) {
            // A deliberate file setting must not silently become another site.
            throw new \RuntimeException('Unknown FORUM_SITE_ID "' . $siteId . '" in '
                . substr(\ForumRewrite\Support\DeploymentSettings::source('FORUM_SITE_ID'), strlen('private file ')) . '; known profiles: '
                . implode(', ', array_keys($profiles)) . '.');
        }

        if ($siteId === null || !isset($profiles[$siteId])) {
            $siteId = self::DEFAULT_SITE_ID;
        }

        return $profiles[$siteId];
    }

    /**
     * @param array<string, array<string, mixed>> $profiles
     */
    /**
     * The form of a request path that page themes are keyed by: no query and
     * no trailing slash, except for the root.
     */
    public static function pageThemePath(string $path): string
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** The page theme for a request path on the active site, or null. */
    public static function pageThemeFor(string $path): ?string
    {
        return self::active()['pageThemes'][self::pageThemePath($path)] ?? null;
    }

    /**
     * The explicit themes Auto/System resolves to for a light and a dark
     * device setting. Profiles without autoThemes use Light and Dark.
     *
     * @param array<string, mixed> $profile
     * @return array{light: string, dark: string}
     */
    public static function autoThemes(array $profile): array
    {
        return ($profile['autoThemes'] ?? []) + ['light' => 'light', 'dark' => 'dark'];
    }

    public static function validate(array $profiles): void
    {
        if (!isset($profiles[self::DEFAULT_SITE_ID])) {
            throw new \RuntimeException('Site profiles must include the zenmemes fallback.');
        }

        $browserNamespaces = [];
        foreach ($profiles as $siteId => $profile) {
            if (!self::isBrowserSafeIdentifier($siteId)) {
                throw new \RuntimeException("Site profile ID is not browser-safe: {$siteId}");
            }

            foreach (['name', 'displayName', 'defaultTheme', 'browserNamespace', 'editorialContentKey', 'composerPrompt'] as $field) {
                if (!isset($profile[$field]) || !is_string($profile[$field]) || $profile[$field] === '') {
                    throw new \RuntimeException("Site profile {$siteId} requires a non-empty {$field}.");
                }
            }

            foreach (['permittedThemes', 'enabledExperienceKeys'] as $field) {
                if (!isset($profile[$field]) || !is_array($profile[$field]) || $profile[$field] === []) {
                    throw new \RuntimeException("Site profile {$siteId} requires non-empty {$field}.");
                }
                foreach ($profile[$field] as $value) {
                    if (!is_string($value) || $value === '') {
                        throw new \RuntimeException("Site profile {$siteId} has an invalid {$field} value.");
                    }
                }
            }

            if (count($profile['permittedThemes']) !== count(array_unique($profile['permittedThemes']))) {
                throw new \RuntimeException("Site profile {$siteId} has duplicate permitted themes.");
            }
            foreach ($profile['permittedThemes'] as $theme) {
                if (!ThemeRegistry::isKnownName($theme)) {
                    throw new \RuntimeException("Site profile {$siteId} has an unknown permitted theme: {$theme}");
                }
            }

            if (!in_array($profile['defaultTheme'], $profile['permittedThemes'], true)) {
                throw new \RuntimeException("Site profile {$siteId} default theme must be permitted.");
            }

            if (isset($profile['autoThemes'])) {
                if (!is_array($profile['autoThemes']) || array_keys($profile['autoThemes']) !== ['light', 'dark']) {
                    throw new \RuntimeException("Site profile {$siteId} auto themes must name a light and a dark theme.");
                }
                foreach ($profile['autoThemes'] as $autoTheme) {
                    if (!is_string($autoTheme) || $autoTheme === 'auto' || !in_array($autoTheme, $profile['permittedThemes'], true)) {
                        throw new \RuntimeException("Site profile {$siteId} auto themes must be permitted explicit themes.");
                    }
                }
            }

            foreach (($profile['pageThemes'] ?? []) as $pagePath => $pageTheme) {
                if (!is_string($pagePath) || !str_starts_with($pagePath, '/') || $pagePath !== self::pageThemePath($pagePath)) {
                    throw new \RuntimeException("Site profile {$siteId} has an invalid page theme path: {$pagePath}");
                }
                if (!is_string($pageTheme) || $pageTheme === 'auto' || !in_array($pageTheme, $profile['permittedThemes'], true)) {
                    throw new \RuntimeException("Site profile {$siteId} page theme must be a permitted explicit theme: {$pagePath}");
                }
            }

            $browserNamespace = $profile['browserNamespace'];
            if (!self::isBrowserSafeIdentifier($browserNamespace) || isset($browserNamespaces[$browserNamespace])) {
                throw new \RuntimeException("Site profile browser namespace is invalid or duplicated: {$browserNamespace}");
            }
            $browserNamespaces[$browserNamespace] = true;

            if (!isset($profile['presentationSlots']) || !is_array($profile['presentationSlots'])) {
                throw new \RuntimeException("Site profile {$siteId} requires presentation slots.");
            }
            PresentationSlotRegistry::validate($profile['presentationSlots'], $siteId);
        }
    }

    private static function isBrowserSafeIdentifier(string $identifier): bool
    {
        return preg_match('/^[a-z][a-z0-9-]*$/', $identifier) === 1;
    }
}
