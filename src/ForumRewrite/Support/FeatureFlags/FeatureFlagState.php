<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagState
{
    public function __construct(
        public readonly FeatureFlagDefinition $definition,
        public readonly bool $effectiveValue,
        public readonly string $source,
        public readonly ?bool $environmentValue = null,
        public readonly ?bool $siteValue = null,
        public readonly ?string $siteError = null,
    ) {
    }

    public function isDefault(): bool
    {
        return $this->effectiveValue === $this->definition->defaultValue;
    }

    public function canChangeFromSite(): bool
    {
        return $this->definition->siteMutable && $this->environmentValue === null && $this->siteError === null;
    }
}
