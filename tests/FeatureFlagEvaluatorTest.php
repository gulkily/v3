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

    public function testRepositoryValuesOverrideDefaultsButNotEnvironment(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_APP_VERSION_NOTIFICATION: false\nFORUM_UNICODE_AUTHORED_TEXT: true\n");
            $evaluator = FeatureFlagEvaluator::forRepository($repositoryRoot);

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);

            assertSame(true, $unicode->effectiveValue);
            assertSame('site', $unicode->source);
            assertSame(true, $unicode->siteValue);
            assertSame(false, $notification->effectiveValue);
            assertSame('site', $notification->source);
            assertSame(false, $notification->siteValue);
        });

        $this->withEnvironment([
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT => 'false',
        ], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: true\n");
            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);

            assertSame(false, $unicode->effectiveValue);
            assertSame('environment', $unicode->source);
            assertSame(true, $unicode->siteValue);
        });
    }

    public function testInvalidRepositoryRecordIsReportedAndFallsBackToDefault(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: yes\n");
            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);

            assertSame(false, $unicode->effectiveValue);
            assertSame('invalid-site-value', $unicode->source);
            assertSame('Invalid site feature flag line: FORUM_UNICODE_AUTHORED_TEXT: yes', $unicode->siteError);
        });
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

    private function repositoryWithFeatureFlags(string $contents): string
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-feature-flags-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot . '/records/instance', 0777, true);
        file_put_contents($repositoryRoot . '/records/instance/feature-flags.txt', $contents);

        return $repositoryRoot;
    }
}
