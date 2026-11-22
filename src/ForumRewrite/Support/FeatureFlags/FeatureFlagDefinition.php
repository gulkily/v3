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
        public readonly ?string $requiresEnabledFlag = null,
        public readonly ?string $group = null,
        /** YYYY-MM-DD the flag shipped; set when adding a flag (feature flags page, Recently released). */
        public readonly ?string $releasedOn = null,
    ) {
    }

    public function groupKey(): string
    {
        return $this->group ?? explode('_', $this->key, 2)[0];
    }
}
