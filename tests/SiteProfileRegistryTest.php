<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\SiteProfileRegistry;

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
        foreach (SiteProfileRegistry::all() as $siteId => $profile) {
            assertSame(true, is_string($siteId) && $siteId !== '');
            assertSame(true, $profile['name'] !== '');
            assertSame(true, $profile['defaultTheme'] !== '');
            assertSame(true, $profile['composerPrompt'] !== '');
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
