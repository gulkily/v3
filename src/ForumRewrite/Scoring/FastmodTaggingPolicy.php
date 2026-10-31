<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/** Shared capability policy for every automatic Fastmod tag mapping. */
final class FastmodTaggingPolicy
{
    public static function enabled(string $projectRoot, ?string $repositoryRoot = null): bool
    {
        $repositoryRoot ??= \ForumRewrite\Support\DeploymentSettings::value('FORUM_REPOSITORY_ROOT', $projectRoot) ?: $projectRoot.'/state/local_repository';
        return FeatureFlagEvaluator::forApplication($repositoryRoot,$projectRoot)
            ->isEnabled(FeatureFlagRegistry::FASTMOD_AUTOMATIC_TAGGING_ENABLED);
    }
}
