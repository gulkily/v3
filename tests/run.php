<?php

declare(strict_types=1);

$testFiles = [
    __DIR__ . '/AgentReplyCommandTest.php',
    __DIR__ . '/AgentIdentityServiceTest.php',
    __DIR__ . '/AgentReplyGenerationTest.php',
    __DIR__ . '/ApplicationServerTimingTest.php',
    __DIR__ . '/AnthropicStructuredChatProviderTest.php',
    __DIR__ . '/BrowserSigningNormalizationTest.php',
    __DIR__ . '/CanonicalRecordParsersTest.php',
    __DIR__ . '/ArchiveThreadCommandTest.php',
    __DIR__ . '/CodexHandoffDraftServiceTest.php',
    __DIR__ . '/CodexHandoffStoreTest.php',
    __DIR__ . '/DedalusPostAnalyzerTest.php',
    __DIR__ . '/FeatureFlagEvaluatorTest.php',
    __DIR__ . '/FeatureFlagsBehaviorTest.php',
    __DIR__ . '/LocalAppSmokeTest.php',
    __DIR__ . '/LlmProviderConfigTest.php',
    __DIR__ . '/LazyComposeSigningTest.php',
    __DIR__ . '/OpenPgpLoaderTest.php',
    __DIR__ . '/OpenPgpKeyInspectorTest.php',
    __DIR__ . '/OpenAiCompatibleStructuredChatProviderTest.php',
    __DIR__ . '/PrivateConfigCommandTest.php',
    __DIR__ . '/PostSignatureAuditCommandTest.php',
    __DIR__ . '/PostAnalyzerFactoryTest.php',
    __DIR__ . '/RelatedContentSearchServiceTest.php',
    __DIR__ . '/RepositoryArchiveImportCommandTest.php',
    __DIR__ . '/ReadModelBuilderTimingTest.php',
    __DIR__ . '/ReadModelThreadLabelsTest.php',
    __DIR__ . '/ThemeRegistryTest.php',
    __DIR__ . '/ThreadTitleTest.php',
    __DIR__ . '/UnicodeRiskInspectorTest.php',
    __DIR__ . '/UnicodeRiskStoreTest.php',
    __DIR__ . '/UnicodeTextPolicyTest.php',
    __DIR__ . '/VersionCheckBehaviorTest.php',
    __DIR__ . '/WriteApiSmokeTest.php',
];

$failures = [];
$filters = array_slice($argv, 1);
$runCount = 0;

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

$declared = get_declared_classes();
foreach ($declared as $class) {
    if (!str_ends_with($class, 'Test')) {
        continue;
    }

    $testObject = new $class();
    if (!shouldRunClass($class, $filters)) {
        continue;
    }

    $methods = get_class_methods($testObject);

    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        if (!shouldRunMethod($class, $method, $filters)) {
            continue;
        }
        $runCount++;

        try {
            $testObject->{$method}();
            fwrite(STDOUT, "PASS {$class}::{$method}\n");
        } catch (Throwable $throwable) {
            $failures[] = "{$class}::{$method} - {$throwable->getMessage()}";
            fwrite(STDERR, "FAIL {$class}::{$method} - {$throwable->getMessage()}\n");
        }
    }
}

if ($filters !== [] && $runCount === 0) {
    fwrite(STDERR, "No tests matched the supplied filters.\n");
    exit(1);
}

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All tests passed.\n");

/**
 * @param list<string> $filters
 */
function shouldRunClass(string $class, array $filters): bool
{
    if ($filters === []) {
        return true;
    }

    foreach ($filters as $filter) {
        $filterClass = explode('::', $filter, 2)[0];
        if ($filterClass === $class) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $filters
 */
function shouldRunMethod(string $class, string $method, array $filters): bool
{
    if ($filters === []) {
        return true;
    }

    foreach ($filters as $filter) {
        if ($filter === $class) {
            return true;
        }

        if ($filter === $class . '::' . $method) {
            return true;
        }
    }

    return false;
}
