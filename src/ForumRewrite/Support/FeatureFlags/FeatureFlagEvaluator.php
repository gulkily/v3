<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\PrivateConfig;

final class FeatureFlagEvaluator
{
    /**
     * @param array<string, bool> $siteValues
     */
    public function __construct(
        private readonly FeatureFlagRegistry $registry = new FeatureFlagRegistry(),
        private readonly array $siteValues = [],
        private readonly ?string $siteError = null,
        private readonly array $privateConfigValues = [],
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

    public static function forApplication(string $repositoryRoot, string $projectRoot): self
    {
        $registry = new FeatureFlagRegistry();
        try {
            $record = (new CanonicalRecordRepository($repositoryRoot))->loadFeatureFlags(CanonicalPathResolver::featureFlags());

            return new self($registry, $record->values, privateConfigValues: PrivateConfig::load($projectRoot));
        } catch (CanonicalRecordParseException $exception) {
            return new self($registry, [], $exception->getMessage(), PrivateConfig::load($projectRoot));
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
        $state = $this->evaluateWithoutDependencies($key);
        $requiresEnabledFlag = $state->definition->requiresEnabledFlag;
        if ($requiresEnabledFlag !== null && $state->effectiveValue && !$this->isEnabled($requiresEnabledFlag)) {
            return new FeatureFlagState(
                $state->definition,
                false,
                'dependency',
                $state->environmentValue,
                $state->siteValue,
                $state->siteError,
            );
        }

        return $state;
    }

    private function evaluateWithoutDependencies(string $key): FeatureFlagState
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

        if ($definition->category === 'private' && array_key_exists($definition->environmentVariable, $this->privateConfigValues)) {
            $privateValue = $this->configFlagValue(
                $this->privateConfigValues[$definition->environmentVariable],
                $definition->defaultValue
            );

            return new FeatureFlagState(
                $definition,
                $privateValue,
                'private-config',
                null,
                null,
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

        if ($definition->siteMutable && array_key_exists($definition->key, $this->siteValues)) {
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

    private function configFlagValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return $default;
    }
}
