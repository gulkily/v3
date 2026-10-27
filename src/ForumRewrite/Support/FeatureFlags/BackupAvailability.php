<?php

declare(strict_types=1);
namespace ForumRewrite\Support\FeatureFlags;

/** Cached HTML must have been rendered with the currently effective backup policy. */
final class BackupAvailability
{
    public static function matchesHtml(string $html, FeatureFlagEvaluator $flags): bool
    {
        $state = ($flags->isEnabled(FeatureFlagRegistry::TEXT_BACKUP_ENABLED) ? '1' : '0')
            . ($flags->isEnabled(FeatureFlagRegistry::IMAGE_BACKUP_ENABLED) ? '1' : '0');
        // Pre-flag artifacts exposed both categories; they remain valid only at the legacy defaults.
        if (!str_contains($html, 'data-backup-state=')) return $state === '11';
        return str_contains($html, 'data-backup-state="' . $state . '"');
    }
}
