<?php

declare(strict_types=1);

use ForumRewrite\SiteProfileRegistry;

final class ProfileRegressionFixture
{
    public static function withFourthProfile(callable $callback): void
    {
        $profiles = SiteProfileRegistry::all();
        $template = current($profiles);
        if (!is_array($template)) {
            throw new RuntimeException('Profile fixture requires a registered template profile.');
        }

        $profileId = 'future-site';
        $fixture = $template;
        $fixture['name'] = $profileId;
        $fixture['displayName'] = 'Future Site';
        $fixture['browserNamespace'] = $profileId;
        $profiles[$profileId] = $fixture;

        SiteProfileRegistry::setProfilesForTesting($profiles);
        try {
            $callback();
        } finally {
            SiteProfileRegistry::setProfilesForTesting(null);
        }
    }
}
