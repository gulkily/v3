<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\BrowserRuntimeProfile;

final class SiteProfileRegistryTest
{
    public function testActiveDefaultsToZenmemesWhenUnset(): void
    {
        putenv('FORUM_SITE_ID');

        $profile = SiteProfileRegistry::active();

        assertSame('zenmemes', $profile['name']);
    }

    public function testActiveHonorsKnownOverride(): void
    {
        putenv('FORUM_SITE_ID=chouse');

        try {
            $profile = SiteProfileRegistry::active();
            assertSame('chouse', $profile['name']);
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testActiveHonorsQdbOverride(): void
    {
        putenv('FORUM_SITE_ID=qdb');

        try {
            $profile = SiteProfileRegistry::active();
            assertSame('qdb', $profile['name']);
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testActiveFallsBackToZenmemesForUnknownValue(): void
    {
        putenv('FORUM_SITE_ID=not-a-real-site');

        try {
            $profile = SiteProfileRegistry::active();
            assertSame('zenmemes', $profile['name']);
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testAllProfilesHaveRequiredFields(): void
    {
        $browserNamespaces = [];
        foreach (SiteProfileRegistry::all() as $siteId => $profile) {
            assertSame(true, preg_match('/^[a-z][a-z0-9-]*$/', $siteId) === 1);
            assertSame(true, $profile['name'] !== '');
            assertSame(true, $profile['displayName'] !== '');
            assertSame(true, $profile['defaultTheme'] !== '');
            assertSame(true, $profile['permittedThemes'] !== []);
            assertSame(true, in_array($profile['defaultTheme'], $profile['permittedThemes'], true));
            assertSame(true, preg_match('/^[a-z][a-z0-9-]*$/', $profile['browserNamespace']) === 1);
            assertSame(false, isset($browserNamespaces[$profile['browserNamespace']]));
            $browserNamespaces[$profile['browserNamespace']] = true;
            assertSame(true, $profile['editorialContentKey'] !== '');
            assertSame(true, $profile['enabledExperienceKeys'] !== []);
            assertSame(true, $profile['composerPrompt'] !== '');
        }
    }

    public function testValidationRejectsDuplicateOrUnsafeBrowserIdentifiers(): void
    {
        $duplicateNamespaceProfiles = SiteProfileRegistry::all();
        $duplicateNamespaceProfiles['chouse']['browserNamespace'] = 'zenmemes';

        assertThrowsRuntime(
            static function () use ($duplicateNamespaceProfiles): void {
                SiteProfileRegistry::validate($duplicateNamespaceProfiles);
            },
            'Site profile browser namespace is invalid or duplicated: zenmemes',
        );

        $unsafeSiteIdProfiles = SiteProfileRegistry::all();
        $unsafeProfile = $unsafeSiteIdProfiles['chouse'];
        unset($unsafeSiteIdProfiles['chouse']);
        $unsafeSiteIdProfiles['Chouse!'] = $unsafeProfile;

        assertThrowsRuntime(
            static function () use ($unsafeSiteIdProfiles): void {
                SiteProfileRegistry::validate($unsafeSiteIdProfiles);
            },
            'Site profile ID is not browser-safe: Chouse!',
        );

        $unknownThemeProfiles = SiteProfileRegistry::all();
        $unknownThemeProfiles['chouse']['permittedThemes'][] = 'untrusted-theme';

        assertThrowsRuntime(
            static function () use ($unknownThemeProfiles): void {
                SiteProfileRegistry::validate($unknownThemeProfiles);
            },
            'Site profile chouse has an unknown permitted theme: untrusted-theme',
        );
    }

    public function testBrowserRuntimeProfilesAreDerivedOnlyFromValidatedNamespaces(): void
    {
        $runtimeProfiles = [];
        foreach (SiteProfileRegistry::all() as $siteId => $profile) {
            $runtime = BrowserRuntimeProfile::fromProfile($profile);
            $runtimeProfiles[$siteId] = $runtime;

            assertSame($profile['browserNamespace'], $runtime['namespace']);
            assertSame('forum-' . $profile['browserNamespace'] . '-theme', $runtime['themeStorageKey']);
            assertSame('forum-' . $profile['browserNamespace'] . '-thread-density', $runtime['threadDensityStorageKey']);
            assertSame($profile['browserNamespace'] . '-offline-reader-', $runtime['offlineCachePrefix']);
            assertSame($runtime['offlineCachePrefix'] . 'v14', $runtime['offlineCacheName']);
        }

        assertSame('forum-zenmemes-theme', $runtimeProfiles['zenmemes']['themeStorageKey']);
        assertSame('forum-chouse-theme', $runtimeProfiles['chouse']['themeStorageKey']);
        assertSame('forum-qdb-theme', $runtimeProfiles['qdb']['themeStorageKey']);
    }

    public function testBrowserRuntimeProfileRejectsInvalidIdentity(): void
    {
        assertThrowsInvalidArgument(
            static fn (): array => BrowserRuntimeProfile::fromProfile(['browserNamespace' => 'unsafe!', 'displayName' => 'Unsafe']),
            'Profile requires a valid browser runtime identity.',
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

if (!function_exists('assertThrowsRuntime')) {
    function assertThrowsRuntime(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (RuntimeException $exception) {
            assertSame($expectedMessage, $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected RuntimeException was not thrown.');
    }
}

if (!function_exists('assertThrowsInvalidArgument')) {
    function assertThrowsInvalidArgument(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException $exception) {
            assertSame($expectedMessage, $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected InvalidArgumentException was not thrown.');
    }
}
