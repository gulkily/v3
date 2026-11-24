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
     * @return array<string, array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string, appName?: string, calendarTimeZone?: string}>
     */
    public static function all(): array
    {
        $profiles = self::$profilesForTesting ?? [
            'zenmemes' => [
                'name' => 'zenmemes',
                'displayName' => 'zenmemes',
                // The installed app's name on a home screen (BrowserRuntimeProfile); defaults to displayName.
                'appName' => 'ZenMemes',
                'calendarTimeZone' => 'America/New_York',
                'defaultTheme' => 'auto',
                // What Auto/System shows for a light or dark device setting.
                'autoThemes' => ['light' => 'gloss', 'dark' => 'glossdark'],
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'spearmint', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'zalgo', 'vaporgeist', 'gloss', 'glossdark', 'engagify', 'ochre', 'underground', 'undergrounddark'],
                'browserNamespace' => 'zenmemes',
                'editorialContentKey' => 'zenmemes',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                // Page themes: a page path and the theme it shows by default.
                'pageThemes' => [
                    // "Our cookie notice (there isn't one)"
                    '/threads/thread-20261102152258-25e82c97' => 'engagify',
                ],
                // The thread whose top-level replies /guestbook/ shows as a book; null until it exists.
                'guestbookThread' => null,
                // The thread whose replies carry backlog claims (`claim:` lines); null until it exists, and then the backlog shows no holders.
                'backlogClaimsThread' => null,
                // Threads an approved member tagged with every one of these open the board as Zen memes.
                'zenStripTags' => ['zen', 'meme'],
                'presentationSlots' => ['navigation' => 'app', 'boardCard' => 'list', 'compose' => 'thread', 'about' => 'default', 'editorial' => 'zenmemes', 'brandedStylesheet' => 'site'],
            ],
            'chouse' => [
                'name' => 'chouse',
                'displayName' => 'chouse',
                'defaultTheme' => 'chouse',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'zalgo', 'vaporgeist', 'chouse'],
                'browserNamespace' => 'chouse',
                'editorialContentKey' => 'chouse',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'chouse', 'editorial' => 'boston', 'brandedStylesheet' => 'chouse', 'favicon' => 'chouse'],
            ],
            'qdb' => [
                'name' => 'qdb',
                'displayName' => 'qdb',
                'appName' => 'QDB',
                'defaultTheme' => 'qdb',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'zalgo', 'vaporgeist', 'qdb'],
                'browserNamespace' => 'qdb',
                'editorialContentKey' => 'qdb',
                'enabledExperienceKeys' => ['qdb'],
                'composerPrompt' => 'Submit a quote...',
                'presentationSlots' => ['navigation' => 'qdb', 'boardCard' => 'quote', 'compose' => 'qdb', 'about' => 'default', 'editorial' => 'qdb', 'brandedStylesheet' => 'qdb', 'favicon' => 'qdb'],
            ],
            'mitrapclub' => [
                'name' => 'mitrapclub',
                'displayName' => 'MIT Rap Club',
                'defaultTheme' => 'underground',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'bubblegum', 'arena', 'thermal', 'whitehot', 'word97', 'midnight', 'zalgo', 'vaporgeist', 'mitrapclub', 'underground', 'undergrounddark'],
                'browserNamespace' => 'mitrapclub',
                'editorialContentKey' => 'mitrapclub',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Drop a verse, a cypher clip, or an announcement...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'socials', 'editorial' => 'mitrapclub', 'brandedStylesheet' => 'underground', 'toolsNav' => 'hidden', 'favicon' => 'mitrapclub'],
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
     * @return array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string, appName?: string, calendarTimeZone?: string}
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

    public static function calendarTimeZone(): ?\DateTimeZone
    {
        $zone = self::active()['calendarTimeZone'] ?? null;

        return $zone === null ? null : new \DateTimeZone($zone);
    }

    /** The active site's backlog claims thread ID, or null when the site has none. */
    public static function backlogClaimsThread(): ?string
    {
        $threadId = self::active()['backlogClaimsThread'] ?? null;

        return is_string($threadId) && $threadId !== '' ? $threadId : null;
    }

    /** The active site's guestbook thread ID, or null when the site has none. */
    public static function guestbookThread(): ?string
    {
        $threadId = self::active()['guestbookThread'] ?? null;

        return is_string($threadId) && $threadId !== '' ? $threadId : null;
    }

    /**
     * The tags a thread needs, all of them, to open the board in the Zen strip; empty when the
     * active site has no strip.
     *
     * @return list<string>
     */
    public static function zenStripTags(): array
    {
        $tags = self::active()['zenStripTags'] ?? [];

        return is_array($tags) ? array_values($tags) : [];
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

            if (array_key_exists('appName', $profile) && (!is_string($profile['appName']) || trim($profile['appName']) === '')) {
                throw new \RuntimeException("Site profile {$siteId} has an empty appName.");
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

            if (isset($profile['guestbookThread']) && (!is_string($profile['guestbookThread']) || preg_match('/^[A-Za-z0-9._-]+$/', $profile['guestbookThread']) !== 1)) {
                throw new \RuntimeException("Site profile {$siteId} has an invalid guestbook thread ID.");
            }
            if (isset($profile['backlogClaimsThread']) && (!is_string($profile['backlogClaimsThread']) || preg_match('/^[A-Za-z0-9._-]+$/', $profile['backlogClaimsThread']) !== 1)) {
                throw new \RuntimeException("Site profile {$siteId} has an invalid backlog claims thread ID.");
            }

            if (isset($profile['zenStripTags'])) {
                $stripTags = $profile['zenStripTags'];
                if (!is_array($stripTags) || $stripTags === [] || !array_is_list($stripTags) || count(array_unique($stripTags)) !== count($stripTags)) {
                    throw new \RuntimeException("Site profile {$siteId} Zen strip tags must be a list of different tags.");
                }
                foreach ($stripTags as $stripTag) {
                    if (!is_string($stripTag) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $stripTag) !== 1) {
                        throw new \RuntimeException("Site profile {$siteId} has an invalid Zen strip tag.");
                    }
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
