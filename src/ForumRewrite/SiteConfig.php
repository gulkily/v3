<?php

declare(strict_types=1);

namespace ForumRewrite;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class SiteConfig
{
    public const SITE_NAME = 'zenmemes';

    public static function unicodeAuthoredTextEnabled(): bool
    {
        return self::featureFlags()->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
    }

    public static function appVersionNotificationEnabled(): bool
    {
        return self::featureFlags()->isEnabled(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
    }

    public static function featureFlags(): FeatureFlagEvaluator
    {
        return new FeatureFlagEvaluator();
    }
}
