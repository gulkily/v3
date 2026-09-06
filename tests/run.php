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
    __DIR__ . '/CodexHandoffRunnerTest.php',
    __DIR__ . '/CodexHandoffStoreTest.php',
    __DIR__ . '/DedalusPostAnalyzerTest.php',
    __DIR__ . '/FeatureFlagEvaluatorTest.php',
    __DIR__ . '/FeatureFlagsBehaviorTest.php',
    __DIR__ . '/LocalAppSmokeTest.php',
    __DIR__ . '/LlmProviderConfigTest.php',
    __DIR__ . '/LazyComposeSigningTest.php',
    __DIR__ . '/LlmExchangeDatabaseConfigTest.php',
    __DIR__ . '/LlmExchangeRecorderTest.php',
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
    __DIR__ . '/SiteProfileRegistryTest.php',
    __DIR__ . '/ThemeRegistryTest.php',
    __DIR__ . '/TestRunnerBehaviorTest.php',
    __DIR__ . '/ThreadTitleTest.php',
    __DIR__ . '/SqliteQueryCatalogTest.php',
    __DIR__ . '/UnicodeRiskInspectorTest.php',
    __DIR__ . '/UnicodeRiskStoreTest.php',
    __DIR__ . '/UnicodeTextPolicyTest.php',
    __DIR__ . '/VersionCheckBehaviorTest.php',
    __DIR__ . '/WriteApiSmokeTest.php',
];

$failures = [];
$filters = array_slice($argv, 1);
$runCount = 0;
$testDurations = [];
$currentTest = null;
$currentTestStartedAt = null;
$timingReportPrinted = false;

register_shutdown_function(
    static function () use (&$testDurations, &$currentTest, &$currentTestStartedAt, &$timingReportPrinted): void {
        if ($timingReportPrinted) {
            return;
        }

        printSlowTestsOverThreshold($testDurations, $currentTest, $currentTestStartedAt, true, STDERR);
    }
);

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function (int $receivedSignal) use (&$testDurations, &$currentTest, &$currentTestStartedAt, &$timingReportPrinted): void {
            fwrite(STDERR, "\nInterrupted by signal {$receivedSignal}.\n");
            printSlowTestsOverThreshold($testDurations, $currentTest, $currentTestStartedAt, true, STDERR);
            exit(128 + $receivedSignal);
        });
    }
}

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
        $testName = "{$class}::{$method}";
        $currentTest = $testName;
        $currentTestStartedAt = hrtime(true);

        try {
            $testObject->{$method}();
            fwrite(STDOUT, "PASS {$testName}\n");
        } catch (Throwable $throwable) {
            $failures[] = "{$testName} - {$throwable->getMessage()}";
            fwrite(STDERR, "FAIL {$testName} - {$throwable->getMessage()}\n");
        } finally {
            $testDurations[$testName] = (hrtime(true) - $currentTestStartedAt) / 1_000_000_000;
            $currentTest = null;
            $currentTestStartedAt = null;
        }
    }
}

if ($filters !== [] && $runCount === 0) {
    fwrite(STDERR, "No tests matched the supplied filters.\n");
    exit(1);
}

if ($failures !== []) {
    printSlowTestsOverThreshold($testDurations, null, null, false, STDOUT);
    exit(1);
}

fwrite(STDOUT, "All tests passed.\n");
printSlowTestsOverThreshold($testDurations, null, null, false, STDOUT);

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
 * @param array<string, float> $testDurations
 */
function printSlowTestsOverThreshold(
    array $testDurations,
    ?string $currentTest,
    ?int $currentTestStartedAt,
    bool $partial,
    mixed $stream,
): void {
    global $timingReportPrinted;

    if ($testDurations === [] && $currentTest === null) {
        return;
    }

    $timingReportPrinted = true;
    $thresholdSeconds = slowTestReportThresholdSeconds();
    $rows = [];
    foreach ($testDurations as $testName => $seconds) {
        if ($seconds < $thresholdSeconds) {
            continue;
        }

        $rows[] = [
            'name' => $testName,
            'seconds' => $seconds,
            'running' => false,
        ];
    }

    if ($currentTest !== null && $currentTestStartedAt !== null) {
        $currentSeconds = (hrtime(true) - $currentTestStartedAt) / 1_000_000_000;
        if ($currentSeconds >= $thresholdSeconds) {
            $rows[] = [
                'name' => $currentTest,
                'seconds' => $currentSeconds,
                'running' => true,
            ];
        }
    }

    if ($rows === []) {
        return;
    }

    usort(
        $rows,
        static fn (array $left, array $right): int => $right['seconds'] <=> $left['seconds']
    );

    $title = $partial
        ? sprintf('Tests at or above %.2f seconds so far:', $thresholdSeconds)
        : sprintf('Tests at or above %.2f seconds:', $thresholdSeconds);
    fwrite($stream, $title . "\n");
    foreach ($rows as $index => $row) {
        $suffix = $row['running'] ? ' (running when interrupted)' : '';
        fwrite(
            $stream,
            sprintf(
                "%2d. %8.2f ms %s%s\n",
                $index + 1,
                $row['seconds'] * 1000,
                $row['name'],
                $suffix
            )
        );
    }
}

function slowTestReportThresholdSeconds(): float
{
    $rawThreshold = getenv('FORUM_TEST_SLOW_REPORT_THRESHOLD_SECONDS');
    if ($rawThreshold === false || trim($rawThreshold) === '') {
        return 5.0;
    }

    if (!is_numeric($rawThreshold)) {
        return 5.0;
    }

    return max(0.0, (float) $rawThreshold);
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
