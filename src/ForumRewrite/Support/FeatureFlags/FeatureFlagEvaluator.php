<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;

final class FeatureFlagEvaluator
{
    /**
     * @param array<string, bool> $siteValues
     */
    public function __construct(
        private readonly FeatureFlagRegistry $registry = new FeatureFlagRegistry(),
        private readonly array $siteValues = [],
        private readonly ?string $siteError = null,
    ) {
    }

    public static function forRepository(string $repositoryRoot): self
    {
        $registry = new FeatureFlagRegistry();
        try {
            $record = (new CanonicalRecordRepository($repositoryRoot))->loadFeatureFlags(CanonicalPathResolver::featureFlags());

            return new self($registry, $record->values);
        } catch (CanonicalRecordParseException $exception) {
            return new self($registry, [], $exception->getMessage());
        }
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
                $this->siteValues[$definition->key] ?? null,
                $this->siteError,
            );
        }

        if ($this->siteError !== null) {
            return new FeatureFlagState(
                $definition,
                $definition->defaultValue,
                'invalid-site-value',
                null,
                null,
                $this->siteError,
            );
        }

        if (array_key_exists($definition->key, $this->siteValues)) {
            return new FeatureFlagState(
                $definition,
                $this->siteValues[$definition->key],
                'site',
                null,
                $this->siteValues[$definition->key],
            );
        }

        return new FeatureFlagState(
            $definition,
            $definition->defaultValue,
            'default',
            null,
            null,
            $this->siteError,
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
