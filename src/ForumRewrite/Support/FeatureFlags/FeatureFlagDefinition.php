<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly bool $defaultValue,
        public readonly string $environmentVariable,
        public readonly string $category = 'site',
        public readonly bool $siteMutable = false,
    ) {
    }
}
