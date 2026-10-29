<?php

declare(strict_types=1);
namespace ForumRewrite\Support\FeatureFlags;

final class RailspikeAvailability
{
    public static function matchesHtml(string $html, FeatureFlagEvaluator $flags): bool
    {
        // Legacy workflow pages must be regenerated to gain thread and viewer gating.
        if (!str_contains($html, 'data-railspike-enabled=')) return !str_contains($html, 'railspike_workflow');
        return str_contains($html, 'data-railspike-enabled="' . ($flags->isEnabled(FeatureFlagRegistry::RAILSPIKE_ENABLED) ? '1' : '0') . '"');
    }
}
