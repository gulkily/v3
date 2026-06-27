<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class FeatureFlagEvaluatorTest
{
    public function testDefaultsMatchExistingSiteFlags(): void
    {
        $this->withEnvironment([], function (): void {
            $evaluator = new FeatureFlagEvaluator();

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);

            assertSame(false, $unicode->effectiveValue);
            assertSame('default', $unicode->source);
            assertSame(true, $unicode->isDefault());
            assertSame(true, $notification->effectiveValue);
            assertSame('default', $notification->source);
            assertSame(true, $notification->isDefault());
        });
    }

    public function testEnvironmentOverridesDefaults(): void
    {
        $this->withEnvironment([
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT => 'true',
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION => 'false',
        ], function (): void {
            $evaluator = new FeatureFlagEvaluator();

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);

            assertSame(true, $unicode->effectiveValue);
            assertSame('environment', $unicode->source);
            assertSame(true, $unicode->environmentValue);
            assertSame(false, $notification->effectiveValue);
            assertSame('environment', $notification->source);
            assertSame(false, $notification->environmentValue);
        });
    }

    public function testRegistryListsCurrentPublicFlags(): void
    {
        $states = (new FeatureFlagEvaluator())->all();
        $keys = array_map(
            static fn ($state): string => $state->definition->key,
            $states
        );

        assertSame([
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT,
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION,
        ], $keys);
    }

    /**
     * @param array<string, string> $values
     * @param callable(): void $callback
     */
    private function withEnvironment(array $values, callable $callback): void
    {
        $keys = [
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT,
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION,
        ];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = getenv($key);
            putenv($key);
        }

        foreach ($values as $key => $value) {
            putenv($key . '=' . $value);
        }

        try {
            $callback();
        } finally {
            foreach ($keys as $key) {
                if ($previous[$key] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $previous[$key]);
                }
            }
        }
    }
}
