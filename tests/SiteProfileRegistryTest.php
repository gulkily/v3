<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ProfileRegressionContract.php';

use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\BrowserRuntimeProfile;

final class SiteProfileRegistryTest
{
    public function testActiveDefaultsToZenmemesWhenUnset(): void
    {
        putenv('FORUM_SITE_ID');

        $profile = SiteProfileRegistry::active();

        assertSame(array_key_first(SiteProfileRegistry::all()), $profile['name']);
    }

    public function testActiveHonorsRegisteredOverrides(): void
    {
        foreach (SiteProfileRegistry::all() as $profileId => $expected) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                assertSame($expected['name'], SiteProfileRegistry::active()['name']);
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testActiveFallsBackToZenmemesForUnknownValue(): void
    {
        putenv('FORUM_SITE_ID=not-a-real-site');

        try {
            $profile = SiteProfileRegistry::active();
            assertSame(array_key_first(SiteProfileRegistry::all()), $profile['name']);
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

    public function testPageThemesUseNormalizedPathsAndPermittedThemes(): void
    {
        assertSame('/threads/abc', SiteProfileRegistry::pageThemePath('/threads/abc/?x=1'));
        assertSame('/', SiteProfileRegistry::pageThemePath('/'));
        assertSame('engagify', SiteProfileRegistry::all()['zenmemes']['pageThemes']['/threads/thread-20261102152258-25e82c97']);

        $cases = [
            'trailing slash' => [['/threads/abc/' => 'light'], 'Site profile chouse has an invalid page theme path: /threads/abc/'],
            'relative path' => [['threads/abc' => 'light'], 'Site profile chouse has an invalid page theme path: threads/abc'],
            'unpermitted theme' => [['/threads/abc' => 'engagify'], 'Site profile chouse page theme must be a permitted explicit theme: /threads/abc'],
            'auto' => [['/threads/abc' => 'auto'], 'Site profile chouse page theme must be a permitted explicit theme: /threads/abc'],
        ];
        foreach ($cases as [$pageThemes, $message]) {
            $profiles = SiteProfileRegistry::all();
            $profiles['chouse']['pageThemes'] = $pageThemes;
            assertThrowsRuntime(
                static function () use ($profiles): void {
                    SiteProfileRegistry::validate($profiles);
                },
                $message,
            );
        }
    }

    public function testAutoThemesDefaultToLightAndDarkAndMustBePermitted(): void
    {
        $profiles = SiteProfileRegistry::all();
        assertSame(['light' => 'gloss', 'dark' => 'glossdark'], SiteProfileRegistry::autoThemes($profiles['zenmemes']));
        assertSame(['light' => 'light', 'dark' => 'dark'], SiteProfileRegistry::autoThemes($profiles['chouse']));

        $message = 'Site profile chouse auto themes must be permitted explicit themes.';
        $cases = [
            'unpermitted theme' => [['light' => 'gloss', 'dark' => 'dark'], $message],
            'auto' => [['light' => 'auto', 'dark' => 'dark'], $message],
            'missing dark' => [['light' => 'light'], 'Site profile chouse auto themes must name a light and a dark theme.'],
        ];
        foreach ($cases as [$autoThemes, $expected]) {
            $profiles = SiteProfileRegistry::all();
            $profiles['chouse']['autoThemes'] = $autoThemes;
            assertThrowsRuntime(
                static function () use ($profiles): void {
                    SiteProfileRegistry::validate($profiles);
                },
                $expected,
            );
        }
    }

    public function testBrowserRuntimeProfilesAreDerivedOnlyFromValidatedNamespaces(): void
    {
        foreach (ProfileRegressionContract::all() as $contract) {
            $profile = $contract['profile'];
            $runtime = $contract['runtime'];

            assertSame($profile['browserNamespace'], $runtime['namespace']);
            assertSame('forum-' . $profile['browserNamespace'] . '-theme', $runtime['themeStorageKey']);
            assertSame('forum-' . $profile['browserNamespace'] . '-thread-density', $runtime['threadDensityStorageKey']);
            assertSame('forum-' . $profile['browserNamespace'] . '-reading-margins', $runtime['readingMarginsStorageKey']);
            assertSame($profile['browserNamespace'] . '-offline-reader-', $runtime['offlineCachePrefix']);
            assertSame($runtime['offlineCachePrefix'] . 'v16', $runtime['offlineCacheName']);
        }
    }

    public function testBrowserRuntimeProfileRejectsInvalidIdentity(): void
    {
        assertThrowsInvalidArgument(
            static fn (): array => BrowserRuntimeProfile::fromProfile(['browserNamespace' => 'unsafe!', 'displayName' => 'Unsafe']),
            'Profile requires a valid browser runtime identity.',
        );
    }

    public function testFourthProfileFixturePassesValidationAndRejectsInvalidIdentity(): void
    {
        $profiles = SiteProfileRegistry::all();
        $fixture = current($profiles);
        assertSame(true, is_array($fixture));
        $fixture['name'] = 'future-site';
        $fixture['displayName'] = 'Future Site';
        $fixture['browserNamespace'] = 'future-site';
        $profiles['future-site'] = $fixture;

        SiteProfileRegistry::validate($profiles);
        $profiles['future-site']['browserNamespace'] = 'invalid!';

        assertThrowsRuntime(
            static fn (): mixed => SiteProfileRegistry::validate($profiles),
            'Site profile browser namespace is invalid or duplicated: invalid!',
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
