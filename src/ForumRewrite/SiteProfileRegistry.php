<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\View\ThemeRegistry;

final class SiteProfileRegistry
{
    private const DEFAULT_SITE_ID = 'zenmemes';

    /**
     * @return array<string, array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string}>
     */
    public static function all(): array
    {
        $profiles = [
            'zenmemes' => [
                'name' => 'zenmemes',
                'displayName' => 'zenmemes',
                'defaultTheme' => 'auto',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97'],
                'browserNamespace' => 'zenmemes',
                'editorialContentKey' => 'zenmemes',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'default', 'editorial' => 'zenmemes', 'brandedStylesheet' => 'site'],
            ],
            'chouse' => [
                'name' => 'chouse',
                'displayName' => 'chouse',
                'defaultTheme' => 'chouse',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'chouse'],
                'browserNamespace' => 'chouse',
                'editorialContentKey' => 'chouse',
                'enabledExperienceKeys' => ['forum'],
                'composerPrompt' => 'Start a thread...',
                'presentationSlots' => ['navigation' => 'forum', 'boardCard' => 'thread', 'compose' => 'thread', 'about' => 'chouse', 'editorial' => 'boston', 'brandedStylesheet' => 'chouse'],
            ],
            'qdb' => [
                'name' => 'qdb',
                'displayName' => 'qdb',
                'defaultTheme' => 'qdb',
                'permittedThemes' => ['auto', 'light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'qdb'],
                'browserNamespace' => 'qdb',
                'editorialContentKey' => 'qdb',
                'enabledExperienceKeys' => ['qdb'],
                'composerPrompt' => 'Submit a quote...',
                'presentationSlots' => ['navigation' => 'qdb', 'boardCard' => 'quote', 'compose' => 'qdb', 'about' => 'default', 'editorial' => 'qdb', 'brandedStylesheet' => 'qdb'],
            ],
        ];

        self::validate($profiles);

        return $profiles;
    }

    /**
     * @return array{name: string, displayName: string, defaultTheme: string, permittedThemes: list<string>, browserNamespace: string, editorialContentKey: string, enabledExperienceKeys: list<string>, composerPrompt: string}
     */
    public static function active(): array
    {
        $profiles = self::all();
        $siteId = getenv('FORUM_SITE_ID');

        if ($siteId === false || $siteId === '' || !isset($profiles[$siteId])) {
            $siteId = self::DEFAULT_SITE_ID;
        }

        return $profiles[$siteId];
    }

    /**
     * @param array<string, array<string, mixed>> $profiles
     */
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
