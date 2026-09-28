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
        public readonly ?bool $dependencyParentEnabled = null,
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

    public function isBlockedByDependency(): bool
    {
        return $this->dependencyParentEnabled === false;
    }

    public function isLocked(): bool
    {
        return !$this->canChangeFromSite() && $this->source !== 'invalid-site-value';
    }

    public function lockReason(): ?string
    {
        if (!$this->isLocked()) {
            return null;
        }

        if ($this->environmentValue !== null) {
            return 'Set via environment variable; restart to change.';
        }

        if ($this->source === 'private-config') {
            return 'Set via private config file; restart to change.';
        }

        return 'Not configurable from the site.';
    }
}
