<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/TestRunHistoryStore.php';

$testFiles = [
    __DIR__ . '/AgentReplyCommandTest.php',
    __DIR__ . '/ApprovedUserKeyResolverTest.php',
    __DIR__ . '/AgentIdentityServiceTest.php',
    __DIR__ . '/AgentReplyGenerationTest.php',
    __DIR__ . '/AgentReplyTaskTest.php',
    __DIR__ . '/AgentResponseGeneratorTest.php',
    __DIR__ . '/AgentResponseTaskTest.php',
    __DIR__ . '/ApplicationServerTimingTest.php',
    __DIR__ . '/AuthNavigationTest.php',
    __DIR__ . '/AnthropicStructuredChatProviderTest.php',
    __DIR__ . '/BrowserSigningNormalizationTest.php',
    __DIR__ . '/CanonicalRecordParsersTest.php',
    __DIR__ . '/ArchiveThreadCommandTest.php',
    __DIR__ . '/CodexHandoffDraftServiceTest.php',
    __DIR__ . '/CodexHandoffRunnerTest.php',
    __DIR__ . '/CodexHandoffStoreTest.php',
    __DIR__ . '/DedalusPostAnalyzerTest.php',
    __DIR__ . '/DetachedTaskQueueLauncherTest.php',
    __DIR__ . '/DevServerLogTest.php',
    __DIR__ . '/FeatureFlagEvaluatorTest.php',
    __DIR__ . '/FeatureFlagsBehaviorTest.php',
    __DIR__ . '/MediaEmbedDetectorTest.php',
    __DIR__ . '/MediaEmbedRendererTest.php',
    __DIR__ . '/MediaEmbedPreviewCacheStoreTest.php',
    __DIR__ . '/InstagramPagePreviewFetcherTest.php',
    __DIR__ . '/YoutubeOembedTitleFetcherTest.php',
    __DIR__ . '/MediaEmbedBeaconTemplatesTest.php',
    __DIR__ . '/MediaEmbedPreviewControllerTest.php',
    __DIR__ . '/MediaEmbedInlinePlayerScriptTest.php',
    __DIR__ . '/TemplateRendererMediaEmbedsScriptTest.php',
    __DIR__ . '/FastScoringConfigTest.php',
    __DIR__ . '/FastScoreContextFactoryTest.php',
    __DIR__ . '/FdpSyncCommandTest.php',
    __DIR__ . '/FastPostScorerTest.php',
    __DIR__ . '/DeterministicFastScoreEvaluatorTest.php',
    __DIR__ . '/FastScoreWorkflowServiceTest.php',
    __DIR__ . '/ForteActivityReadModelRecoveryTest.php',
    __DIR__ . '/ForteBoardReaderTest.php',
    __DIR__ . '/IdentityBootstrapDiagnosticsTest.php',
    __DIR__ . '/ImportedQuoteSeedScoringTest.php',
    __DIR__ . '/InvitationIssuanceTest.php',
    __DIR__ . '/LocalAppSmokeTest.php',
    __DIR__ . '/LlmProviderConfigTest.php',
    __DIR__ . '/LazyComposeSigningTest.php',
    __DIR__ . '/LlmExchangeDatabaseConfigTest.php',
    __DIR__ . '/LlmExchangeRecorderTest.php',
    __DIR__ . '/VisitorStatisticsDatabaseConfigTest.php',
    __DIR__ . '/VisitorStatisticsStoreTest.php',
    __DIR__ . '/VisitorStatisticsObserverTest.php',
    __DIR__ . '/VisitorStatisticsPageTest.php',
    __DIR__ . '/OpenPgpLoaderTest.php',
    __DIR__ . '/OpenPgpAssetSmokeProbeTest.php',
    __DIR__ . '/OpenPgpProductionCanaryCommandTest.php',
    __DIR__ . '/OpenPgpKeyInspectorTest.php',
    __DIR__ . '/OpenAiCompatibleStructuredChatProviderTest.php',
    __DIR__ . '/OperatorStatusCollectorTest.php',
    __DIR__ . '/PlatformDocsCatalogTest.php',
    __DIR__ . '/PlatformDocsPageTest.php',
    __DIR__ . '/PlatformDocsStaticTest.php',
    __DIR__ . '/PrivateMessageDatabaseConfigTest.php',
    __DIR__ . '/PrivateMessageApiRoutingTest.php',
    __DIR__ . '/PrivateMessageComposerTest.php',
    __DIR__ . '/PrivateMessageEnvelopeTest.php',
    __DIR__ . '/PrivateMessageHistoryCryptoTest.php',
    __DIR__ . '/PrivateMessageHistorySyncTest.php',
    __DIR__ . '/PrivateMessageMailboxServiceTest.php',
    __DIR__ . '/PrivateMessageListTest.php',
    __DIR__ . '/PrivateMessagePageControllerTest.php',
    __DIR__ . '/PrivateMessageReaderTest.php',
    __DIR__ . '/PrivateMessageReleaseIsolationTest.php',
    __DIR__ . '/PrivateMessageStoreTest.php',
    __DIR__ . '/PrivateMessageReadStateTest.php',
    __DIR__ . '/OfflineReadingDiagnosticCommandTest.php',
    __DIR__ . '/OfflineNavigationWorkerTest.php',
    __DIR__ . '/OfflineOutboxStateTest.php',
    __DIR__ . '/OfflineOutboxComposeTest.php',
    __DIR__ . '/OfflineOutboxIntentTest.php',
    __DIR__ . '/OfflineOutboxPresentationTest.php',
    __DIR__ . '/OfflineOutboxSendTest.php',
    __DIR__ . '/OfflineOutboxStorageTest.php',
    __DIR__ . '/OfflineSnapshotPresentationTest.php',
    __DIR__ . '/OfflineSnapshotThreadPresentationTest.php',
    __DIR__ . '/OfflineSnapshotBootstrapTest.php',
    __DIR__ . '/OfflineSnapshotBootstrapCommandTest.php',
    __DIR__ . '/OfflineSnapshotPublishCommandTest.php',
    __DIR__ . '/OfflineSnapshotPublisherTest.php',
    __DIR__ . '/PrivateConfigCommandTest.php',
    __DIR__ . '/PrivateConfigSchemaTest.php',
    __DIR__ . '/PrivateSiteAuthTest.php',
    __DIR__ . '/PublicOfflineSnapshotBuilderTest.php',
    __DIR__ . '/PublicOfflineSnapshotManifestTest.php',
    __DIR__ . '/OfflineSnapshotLocatorTest.php',
    __DIR__ . '/QuoteCardDisplayNumberTest.php',
    __DIR__ . '/QdbQuoteNumbersTest.php',
    __DIR__ . '/QdbCapacityProbeTest.php',
    __DIR__ . '/QdbExperienceRoutingTest.php',
    __DIR__ . '/QdbBoardPolicyTest.php',
    __DIR__ . '/QdbVoteCaptionStoreTest.php',
    __DIR__ . '/PostSignatureAuditCommandTest.php',
    __DIR__ . '/PostAnalyzerFactoryTest.php',
    __DIR__ . '/PresentationPathResolverTest.php',
    __DIR__ . '/ProfilePresentationContentTest.php',
    __DIR__ . '/PresentationProfileMatrixTest.php',
    __DIR__ . '/ProfileThemePresentationTest.php',
    __DIR__ . '/PresentationSlotRegistryTest.php',
    __DIR__ . '/RelatedContentSearchServiceTest.php',
    __DIR__ . '/RepositoryArchiveImportCommandTest.php',
    __DIR__ . '/ReadModelBuilderTimingTest.php',
    __DIR__ . '/ReadModelCandidateBuilderTest.php',
    __DIR__ . '/ReadModelMetadataTest.php',
    __DIR__ . '/ReadModelSchemaTest.php',
    __DIR__ . '/ReadModelThreadLabelsTest.php',
    __DIR__ . '/ReadModelThreadSubjectsTest.php',
    __DIR__ . '/LocalWriteServiceThreadSubjectTest.php',
    __DIR__ . '/ResumeTargetTest.php',
    __DIR__ . '/SiteProfileRegistryTest.php',
    __DIR__ . '/ThemeRegistryTest.php',
    __DIR__ . '/TestRunnerBehaviorTest.php',
    __DIR__ . '/TagScoreTest.php',
    __DIR__ . '/TerminalOperatorUiCommandTest.php',
    __DIR__ . '/TerminalOperatorUiDashboardTest.php',
    __DIR__ . '/TaskQueueStoreTest.php',
    __DIR__ . '/TaskQueueCommandTest.php',
    __DIR__ . '/TaskQueueWorkerTest.php',
    __DIR__ . '/TestRunHistoryStoreTest.php',
    __DIR__ . '/ThreadTitleTest.php',
    __DIR__ . '/SqliteQueryCatalogTest.php',
    __DIR__ . '/SqliteLlmExchangeStoreTest.php',
    __DIR__ . '/SqliteFastScoreStoreTest.php',
    __DIR__ . '/StatusCommandTest.php',
    __DIR__ . '/FastScoreSweepServiceTest.php',
    __DIR__ . '/FastmodHistoricalAuditServiceTest.php',
    __DIR__ . '/FastmodCostEstimatorTest.php',
    __DIR__ . '/FastmodAuditCommandTest.php',
    __DIR__ . '/FastmodBackfillRequestServiceTest.php',
    __DIR__ . '/FastScoringRubricRevisionTest.php',
    __DIR__ . '/GeneratedReplyTextNormalizerTest.php',
    __DIR__ . '/UnicodeRiskInspectorTest.php',
    __DIR__ . '/UnicodeRiskStoreTest.php',
    __DIR__ . '/UnicodeTextPolicyTest.php',
    __DIR__ . '/VersionCheckBehaviorTest.php',
    __DIR__ . '/WebServerRoutingTest.php',
    __DIR__ . '/WriteApiSmokeTest.php',
];

$failures = [];
$filters = array_slice($argv, 1);
$runCount = 0;
$testDurations = [];
$testResults = [];
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
            $testResults[$testName] = true;
        } catch (Throwable $throwable) {
            $failures[] = "{$testName} - {$throwable->getMessage()}";
            fwrite(STDERR, "FAIL {$testName} - {$throwable->getMessage()}\n");
            $testResults[$testName] = false;
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

$historyDbPath = getenv('FORUM_TEST_HISTORY_DB_PATH');
if ($historyDbPath === false || trim($historyDbPath) === '') {
    $historyDbPath = __DIR__ . '/../state/test_run_history.sqlite';
}

$historyStore = new TestRunHistoryStore($historyDbPath, recoveryHighlightWindowRuns());
$historyStore->ensureSchema();
$testClassifications = $historyStore->recordResults($testResults, date('c'));

$prunedCount = 0;
if ($filters === []) {
    $prunedCount = $historyStore->pruneStaleEntries(array_keys($testResults));
}

printRunSummary($runCount, $failures, $testDurations, $testClassifications, $prunedCount, STDOUT);

if ($failures !== []) {
    exit(1);
}

/**
 * @param list<string> $failures
 * @param array<string, float> $testDurations
 * @param array<string, array{classification: string, consecutiveFailCount: int, firstFailedAt: ?string, consecutivePassCount: int, recoveredAt: ?string}> $classifications
 */
function printRunSummary(
    int $runCount,
    array $failures,
    array $testDurations,
    array $classifications,
    int $prunedCount,
    mixed $stream,
): void {
    $passedCount = $runCount - count($failures);

    fwrite($stream, "\n");
    fwrite($stream, sprintf("Summary: %d run, %d passed, %d failed\n", $runCount, $passedCount, count($failures)));

    if ($failures !== []) {
        fwrite($stream, "\nFailing tests:\n");
        foreach ($failures as $failure) {
            $lines = explode("\n", $failure);
            $firstLine = $lines[0];
            $extraLineCount = count($lines) - 1;
            $suffix = $extraLineCount > 0
                ? sprintf(' (+%d more line%s, see FAIL output above)', $extraLineCount, $extraLineCount === 1 ? '' : 's')
                : '';
            fwrite($stream, "  - {$firstLine}{$suffix}\n");
        }
    }

    printSlowTestsOverThreshold($testDurations, null, null, false, $stream);

    $newFailures = [];
    $longStandingFailures = [];
    $recovered = [];
    $firstSeenFailures = [];
    foreach ($classifications as $testName => $info) {
        match ($info['classification']) {
            'new_failure' => $newFailures[] = $testName,
            'long_standing_failure' => $longStandingFailures[] = sprintf(
                '%s (failing %d runs, since %s)',
                $testName,
                $info['consecutiveFailCount'],
                $info['firstFailedAt'] ?? 'unknown'
            ),
            'recovered' => $recovered[] = $testName,
            'recently_recovered' => $recovered[] = sprintf(
                '%s (fixed %d run%s ago)',
                $testName,
                $info['consecutivePassCount'] - 1,
                ($info['consecutivePassCount'] - 1) === 1 ? '' : 's'
            ),
            'first_seen_failure' => $firstSeenFailures[] = $testName,
            default => null,
        };
    }

    if ($newFailures !== []) {
        fwrite($stream, "\nNew failures:\n");
        foreach ($newFailures as $testName) {
            fwrite($stream, "  - {$testName}\n");
        }
    }

    if ($longStandingFailures !== []) {
        fwrite($stream, "\nLong-standing failures:\n");
        foreach ($longStandingFailures as $entry) {
            fwrite($stream, "  - {$entry}\n");
        }
    }

    if ($recovered !== []) {
        fwrite($stream, "\nNewly recovered:\n");
        foreach ($recovered as $testName) {
            fwrite($stream, "  - {$testName}\n");
        }
    }

    if ($firstSeenFailures !== []) {
        fwrite($stream, "\nFailing with no prior history (can't tell if new or long-standing):\n");
        foreach ($firstSeenFailures as $testName) {
            fwrite($stream, "  - {$testName}\n");
        }
    }

    if ($prunedCount > 0) {
        fwrite($stream, sprintf(
            "\nPruned %d stale history row%s for tests no longer in the suite.\n",
            $prunedCount,
            $prunedCount === 1 ? '' : 's'
        ));
    }
}

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

function recoveryHighlightWindowRuns(): int
{
    $rawWindow = getenv('FORUM_TEST_RECOVERY_HIGHLIGHT_WINDOW_RUNS');
    if ($rawWindow === false || trim($rawWindow) === '' || !is_numeric($rawWindow)) {
        return 5;
    }

    return max(1, (int) $rawWindow);
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
