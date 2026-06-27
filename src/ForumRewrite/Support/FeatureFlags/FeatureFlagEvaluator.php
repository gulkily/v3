<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagEvaluator
{
    public function __construct(
        private readonly FeatureFlagRegistry $registry = new FeatureFlagRegistry(),
    ) {
    }

    /**
     * @return list<FeatureFlagState>
     */
    public function all(): array
    {
        return array_map(
            fn (FeatureFlagDefinition $definition): FeatureFlagState => $this->evaluate($definition->key),
            $this->registry->all()
        );
    }

    public function isEnabled(string $key): bool
    {
        return $this->evaluate($key)->effectiveValue;
    }

    public function evaluate(string $key): FeatureFlagState
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new \InvalidArgumentException('Unknown feature flag: ' . $key);
        }

        $environmentValue = $this->environmentFlagValue($definition->environmentVariable);
        if ($environmentValue !== null) {
            return new FeatureFlagState(
                $definition,
                $environmentValue,
                'environment',
                $environmentValue,
            );
        }

        return new FeatureFlagState(
            $definition,
            $definition->defaultValue,
            'default',
        );
    }

    private function environmentFlagValue(string $name): ?bool
    {
        $value = getenv($name);
        if ($value === false) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
