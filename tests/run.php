<?php

declare(strict_types=1);

$runStartedAt = hrtime(true);

require_once __DIR__ . '/Support/TestRunHistoryStore.php';

// Throwaway test repositories must not inherit the developer's global Git config:
// commit signing there costs ~0.1 s per commit (and can prompt), and maintenance adds more.
// Repository-level and `-c` settings still apply; tests that need no global config set their own.
$testGitConfig = sys_get_temp_dir() . '/v3-test-gitconfig-' . getmypid();
file_put_contents($testGitConfig, "[user]\n\tname = Forum Rewrite Tests\n\temail = tests@example.invalid\n"
    . "[commit]\n\tgpgsign = false\n[tag]\n\tgpgsign = false\n[maintenance]\n\tauto = false\n[gc]\n\tauto = 0\n");
putenv('GIT_CONFIG_GLOBAL=' . $testGitConfig);
register_shutdown_function(static fn () => @unlink($testGitConfig));

$testFiles = [
    __DIR__ . '/DeleteRecordCommandTest.php',
    __DIR__ . '/EventEndDateParserTest.php',
    __DIR__ . '/EventPhraseReaderTest.php',
    __DIR__ . '/EventPhraseComposeTest.php',
    __DIR__ . '/EventPhraseLlmReaderTest.php',
    __DIR__ . '/EventStatusTest.php',
    __DIR__ . '/ThreadThemeTest.php',
    __DIR__ . '/CalendarPageTest.php',
    __DIR__ . '/PersonIdentityOrderTest.php',
    __DIR__ . '/QdbPageTest.php',
    __DIR__ . '/QdbWelcomeSelectionTest.php',
    __DIR__ . '/CommitEvidenceLookupTest.php',
    __DIR__ . '/CommitHistoryReaderTest.php',
    __DIR__ . '/RecentPostTest.php',
    __DIR__ . '/ScoreVisibilityTest.php',
    __DIR__ . '/FeedbackItemCheckTest.php',
    __DIR__ . '/FastmodScoreTransferTest.php',
    __DIR__ . '/FastmodDerivedTagsTest.php',
    __DIR__ . '/FrmClientTest.php',
    __DIR__ . '/AgentPostingApiTest.php',
    __DIR__ . '/SingleRequestHttpTest.php',
    __DIR__ . '/ReadModelBusyPageTest.php',
    __DIR__ . '/ReadModelRebuildTaskHandlerTest.php',
    __DIR__ . '/ReadModelRequestRebuildTest.php',
    __DIR__ . '/SingleRequestPostingTest.php',
    __DIR__ . '/SignedPostTransactionTest.php',
    __DIR__ . '/SignedPostIdentityTest.php',
    __DIR__ . '/SignedPostBundleValidatorTest.php',
    __DIR__ . '/ActivityProjectionTest.php',
    __DIR__ . '/BackupFeatureFlagTest.php',
    __DIR__ . '/ProfilePublicDetailsTest.php',
    __DIR__ . '/RegistrationBenchmarkTest.php',
    __DIR__ . '/ImportDeadlineRecoveryTest.php',
    __DIR__ . '/InvocationSupervisorTest.php',
    __DIR__ . '/SignatureBudgetTest.php',
    __DIR__ . '/SignatureCheckCacheTest.php',
    __DIR__ . '/ImportAdmissionTest.php',
    __DIR__ . '/CanonicalTextBoundsTest.php',
    __DIR__ . '/BoundedProcessTest.php',
    __DIR__ . '/ArchiveExportTest.php',
    __DIR__ . '/ByteSizeTest.php',
    __DIR__ . '/RetractionMaintenanceTest.php',
    __DIR__ . '/AcceptedRetractionTest.php',
    __DIR__ . '/CommitMapTest.php',
    __DIR__ . '/HistoryPurgePlannerTest.php',
    __DIR__ . '/LegacyTimestampBakeTest.php',
    __DIR__ . '/RetractedLeafFilterTest.php',
    __DIR__ . '/HistoryPurgeTest.php',
    __DIR__ . '/SourceRouteCommitMapTest.php',
    __DIR__ . '/PurgeHistoryCommandTest.php',
    __DIR__ . '/HistoryRewriteTest.php',
    __DIR__ . '/RemovalReceiptTest.php',
    __DIR__ . '/RetractionReceiptTest.php',
    __DIR__ . '/RetractionTransactionTest.php',
    __DIR__ . '/DocsPublicationTest.php',
    __DIR__ . '/IndependentDocsPublicationTest.php',
    __DIR__ . '/LegacyDocsMigrationTest.php',
    __DIR__ . '/DocsPublicationFlowTest.php',
    __DIR__ . '/HighTrafficPublicationTest.php',
    __DIR__ . '/AbsentRetractionSnapshotTest.php',
    __DIR__ . '/AssetFingerprintTest.php',
    __DIR__ . "/HighTrafficTest.php",
    __DIR__ . "/HighTrafficPostRefreshTest.php",
    __DIR__ . "/AccessHistoryPresenterTest.php",
    __DIR__ . "/FastmodTaggingFlagTest.php",
    __DIR__ . "/FastmodMetaTest.php",
    __DIR__ . '/RetractionSweepTest.php',

    __DIR__ . '/TestingSelfApprovalTest.php',
    __DIR__ . '/ToolsRegistryTest.php',
    __DIR__ . '/BoardListingQueryTest.php',
    __DIR__ . '/TagsIndexMemoryTest.php',
    __DIR__ . '/TagPaginationTest.php',
    __DIR__ . '/BoardSearchTest.php',
    __DIR__ . '/FastmodCsvTest.php',
    __DIR__ . '/FastmodRubricsTest.php',
    __DIR__ . '/ImageUploadValidatorTest.php',
    __DIR__ . '/ImageContentTest.php',
    __DIR__ . '/ImageZipRestoreTest.php',
    __DIR__ . '/ImageZipBackupTest.php',
    __DIR__ . '/InstanceImageImportTest.php',
    __DIR__ . '/LegacyTimestampImportTest.php',
    __DIR__ . '/InstanceSourceResolverTest.php',
    __DIR__ . '/ImportInstanceCommandTest.php',
    __DIR__ . '/ImportedContentPublicationTest.php',
    __DIR__ . '/ContentImportRecoveryTest.php',
    __DIR__ . '/RepositoryArchiveValidationTest.php',
    __DIR__ . '/RepositoryContentExportTest.php',
    __DIR__ . '/ContentImportPlannerTest.php',
    __DIR__ . '/AgentReplyCommandTest.php',
    __DIR__ . '/AppNavigationPlacementTest.php',
    __DIR__ . '/BoardListTest.php',
    __DIR__ . '/GalleryPageTest.php',
    __DIR__ . '/InboxPageTest.php',
    __DIR__ . '/InboxFeedTest.php',
    __DIR__ . '/ApprovalAuditCommandTest.php',
    __DIR__ . '/ApprovalPolicyTest.php',
    __DIR__ . '/ApprovalAcceptanceTest.php',
    __DIR__ . '/ApprovalReceiptMaintenanceTest.php',
    __DIR__ . '/ApprovalHistoryTest.php',
    __DIR__ . '/UserAccessTest.php',
    __DIR__ . '/RootApprovalPageTest.php',
    __DIR__ . '/ApprovalSeedCommandTest.php',
    __DIR__ . '/ApprovedUserKeyResolverTest.php',
    __DIR__ . '/AgentIdentityServiceTest.php',
    __DIR__ . '/AgentReplyGenerationTest.php',
    __DIR__ . '/AgentReplyTaskTest.php',
    __DIR__ . '/AgentResponseGeneratorTest.php',
    __DIR__ . '/AgentResponseTaskTest.php',
    __DIR__ . '/AgentResponseMetadataTest.php',
    __DIR__ . '/AgentResponseFormatReadinessTest.php',
    __DIR__ . '/AgentParticipantContextTest.php',
    __DIR__ . '/ApplicationServerTimingTest.php',
    __DIR__ . '/AccountKeyPageHooksTest.php',
    __DIR__ . '/AuthNavigationTest.php',
    __DIR__ . '/AnthropicStructuredChatProviderTest.php',
    __DIR__ . '/BrowserSigningNormalizationTest.php',
    __DIR__ . '/CanonicalRecordParsersTest.php',
    __DIR__ . '/ArchiveThreadCommandTest.php',
    __DIR__ . '/DedalusPostAnalyzerTest.php',
    __DIR__ . '/DetachedTaskQueueLauncherTest.php',
    __DIR__ . '/ScriptureTaskLauncherTest.php',
    __DIR__ . '/ScriptureSourceTest.php',
    __DIR__ . '/ScriptureVerifiedReplyTest.php',
    __DIR__ . '/DevServerLogTest.php',
    __DIR__ . '/FeatureFlagEvaluatorTest.php',
    __DIR__ . '/FeatureFlagsBehaviorTest.php',
    __DIR__ . '/BandcampPlayerControllerTest.php',
    __DIR__ . '/BandcampPlayerResolverTest.php',
    __DIR__ . '/MusicMediaEmbedTest.php',
    __DIR__ . '/MediaEmbedDetectorTest.php',
    __DIR__ . '/MediaEmbedRendererTest.php',
    __DIR__ . '/MediaEmbedPreviewCacheStoreTest.php',
    __DIR__ . '/InstagramPagePreviewFetcherTest.php',
    __DIR__ . '/YoutubeOembedTitleFetcherTest.php',
    __DIR__ . '/MediaEmbedBeaconTemplatesTest.php',
    __DIR__ . '/ThreadRootCardRepeatedTitleTest.php',
    __DIR__ . '/MediaEmbedPreviewControllerTest.php',
    __DIR__ . '/MediaEmbedInlinePlayerScriptTest.php',
    __DIR__ . '/PostRetractionScriptTest.php',
    __DIR__ . '/TemplateRendererMediaEmbedsScriptTest.php',
    __DIR__ . '/FastScoringConfigTest.php',
    __DIR__ . '/FastScoreContextFactoryTest.php',
    __DIR__ . '/RailspikeSyncCommandTest.php',
    __DIR__ . '/FastPostScorerTest.php',
    __DIR__ . '/DeterministicFastScoreEvaluatorTest.php',
    __DIR__ . '/FastScoreWorkflowServiceTest.php',
    __DIR__ . '/ForteActivityReadModelRecoveryTest.php',
    __DIR__ . '/ForteBoardReaderTest.php',
    __DIR__ . '/SourceViewerTest.php',
    __DIR__ . '/IdentityBootstrapDiagnosticsTest.php',
    __DIR__ . '/ImportedQuoteSeedScoringTest.php',
    __DIR__ . '/InvitationIssuanceTest.php',
    __DIR__ . '/ActivityItemLinkTest.php',
    __DIR__ . '/StandardActivityPaginationTest.php',
    __DIR__ . '/FeatureFlagChangePageTest.php',
    __DIR__ . '/BackupPageTest.php',
    __DIR__ . '/LocalAppSmokeTest.php',
    __DIR__ . '/LlmProviderConfigTest.php',
    __DIR__ . '/LazyComposeSigningTest.php',
    __DIR__ . '/LlmExchangeDatabaseConfigTest.php',
    __DIR__ . '/ExchangeDependenciesTest.php',
    __DIR__ . '/LlmExchangeRecorderTest.php',
    __DIR__ . '/VisitorStatisticsDatabaseConfigTest.php',
    __DIR__ . '/VisitorStatisticsStoreTest.php',
    __DIR__ . '/VisitorStatisticsObserverTest.php',
    __DIR__ . '/VisitorStatisticsPageTest.php',
    __DIR__ . '/OpenPgpLoaderTest.php',
    __DIR__ . '/OpenPgpAssetSmokeProbeTest.php',
    __DIR__ . '/OpenPgpProductionCanaryCommandTest.php',
    __DIR__ . '/OpenPgpKeyInspectorTest.php',
    __DIR__ . '/GpgHomeCleanerTest.php',
    __DIR__ . '/OpenAiCompatibleStructuredChatProviderTest.php',
    __DIR__ . '/OperatorStatusCollectorTest.php',
    __DIR__ . '/FrontendProbeTest.php',
    __DIR__ . '/FrontendTargetResolverTest.php',
    __DIR__ . '/DatabaseLockProbeTest.php',
    __DIR__ . '/HealthIndicatorsTest.php',
    __DIR__ . '/ApiRouteCatalogTest.php',
    __DIR__ . '/LlmsTxtRendererTest.php',
    __DIR__ . '/AgentApiWorkedExampleTest.php',
    __DIR__ . '/PlatformDocsCatalogTest.php',
    __DIR__ . '/PlatformDocsPageTest.php',
    __DIR__ . '/MarkdownPipeTableTest.php',
    __DIR__ . '/MarkdownListStructureTest.php',
    __DIR__ . '/MarkdownTaskListTest.php',
    __DIR__ . '/PostMarkdownRendererTest.php',
    __DIR__ . '/ZoomButtonsTest.php',
    __DIR__ . '/PostTextTest.php',
    __DIR__ . '/MarkdownDetectTest.php',
    __DIR__ . '/PostFormatRecordTest.php',
    __DIR__ . '/PlatformDocsStaticTest.php',
    __DIR__ . '/PrivateMessageDatabaseConfigTest.php',
    __DIR__ . '/PrivateMessageApiRoutingTest.php',
    __DIR__ . '/PrivateMessageFeatureFlagTest.php',
    __DIR__ . '/PrivateMessageComposerTest.php',
    __DIR__ . '/UsersDirectorySortTest.php',
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
    __DIR__ . '/ReactionOutboxTest.php',
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
    __DIR__ . '/ReadModelProvenanceTest.php',
    __DIR__ . '/ReadModelPostReactionsTest.php',
    __DIR__ . '/ReactionRecordFormatParityTest.php',
    __DIR__ . '/ReadModelCandidateBuilderTest.php',
    __DIR__ . '/ReadModelMetadataTest.php',
    __DIR__ . '/ReadModelSchemaTest.php',
    __DIR__ . '/ReadModelHotIndexRebuildTest.php',
    __DIR__ . '/ReadModelDropSchemaTest.php',
    __DIR__ . '/ReadModelViewerTagsRebuildTest.php',
    __DIR__ . '/ReadModelViewerTagsProjectionTest.php',
    __DIR__ . '/ViewerTagLookupParityTest.php',
    __DIR__ . '/ReadModelThreadLabelsTest.php',
    __DIR__ . '/ReadModelThreadSubjectsTest.php',
    __DIR__ . '/ReadModelPostRetractionsTest.php',
    __DIR__ . '/LocalWriteServiceThreadSubjectTest.php',
    __DIR__ . '/ComposeAttemptReplayTest.php',
    __DIR__ . '/LocalWriteServiceTitleDirectiveTest.php',
    __DIR__ . '/ReadModelThreadTitleRebuildTest.php',
    __DIR__ . '/ReadModelSignalEligibilityTest.php',
    __DIR__ . '/PostSignalsTemplateTest.php',
    __DIR__ . '/ThreadTitleWithdrawalAndConsumersTest.php',
    __DIR__ . '/ResumeTargetTest.php',
    __DIR__ . '/DeploymentSettingsTest.php',
    __DIR__ . '/SiteProfileRegistryTest.php',
    __DIR__ . '/ThemeRegistryTest.php',
    __DIR__ . '/ThemeToggleScriptTest.php',
    __DIR__ . '/Word97ThemeCoverageTest.php',
    __DIR__ . '/NavIconSetsTest.php',
    __DIR__ . '/PostActionThemeTest.php',
    __DIR__ . '/TestRunnerBehaviorTest.php',
    __DIR__ . '/TagScoreTest.php',
    __DIR__ . '/TerminalOperatorUiCommandTest.php',
    __DIR__ . '/TerminalOperatorUiDashboardTest.php',
    __DIR__ . '/TaskQueueStoreTest.php',
    __DIR__ . '/ViewerSessionStoreTest.php',
    __DIR__ . '/TaskQueueCommandTest.php',
    __DIR__ . '/TaskQueueWorkerTest.php',
    __DIR__ . '/TestRunHistoryStoreTest.php',
    __DIR__ . '/ThreadTitleTest.php',
    __DIR__ . '/SqliteQueryCatalogTest.php',
    __DIR__ . '/SqliteLlmExchangeStoreTest.php',
    __DIR__ . '/SqliteFastScoreStoreTest.php',
    __DIR__ . '/StatusCommandTest.php',
    __DIR__ . '/CheckoutsCommandTest.php',
    __DIR__ . '/AppUpdateTest.php',
    __DIR__ . '/DeployPageTest.php',
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
    __DIR__ . '/UrlHintsTest.php',
    __DIR__ . '/StressProbeScriptTest.php',
    __DIR__ . '/WebServerRoutingTest.php',
    __DIR__ . '/WriteApiSmokeTest.php',
];

// Per-task runs use the curated smoke set (tests/smoke.txt) plus the tests for what changed:
//   ./v3 test                        smoke set
//   ./v3 test --smoke FooTest ...    smoke set plus the named tests
//   ./v3 test FooTest Bar::testX     only the named tests
//   ./v3 test --all                  full suite (the maintainer runs it periodically)
//   --jobs=N                         run any selection in N worker processes (./v3 fulltest uses every core)
// Set only for parallel workers; removed so runners started by tests are not mistaken for workers.
$workerResultsPath = (string) getenv('FORUM_TEST_WORKER_RESULTS');
putenv('FORUM_TEST_WORKER_RESULTS');
$failures = [];
$arguments = array_slice($argv, 1);
$runAll = in_array('--all', $arguments, true);
$withSmoke = in_array('--smoke', $arguments, true);
$jobs = 1;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--jobs=')) {
        $jobs = (int) substr($argument, 7);
        if ($jobs < 1) {
            fwrite(STDERR, "--jobs needs a positive number.\n");
            exit(1);
        }
    }
}
$filters = array_values(array_filter($arguments, static fn (string $argument): bool => !in_array($argument, ['--all', '--smoke'], true) && !str_starts_with($argument, '--jobs=')));
$smokeEntries = [];
if (!$runAll && ($filters === [] || $withSmoke)) {
    $smokeEntries = readSmokeList(smokeListPath());
    $filters = array_values(array_unique(array_merge($smokeEntries, $filters)));
}
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
            foreach ($GLOBALS['testWorkers'] ?? [] as $worker) {
                proc_terminate($worker['process']);
            }
            printSlowTestsOverThreshold($testDurations, $currentTest, $currentTestStartedAt, true, STDERR);
            exit(128 + $receivedSignal);
        });
    }
}

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

$declared = get_declared_classes();
foreach ($smokeEntries as $entry) {
    [$entryClass, $entryMethod] = array_pad(explode('::', $entry, 2), 2, null);
    if (!in_array($entryClass, $declared, true) || ($entryMethod !== null && !method_exists($entryClass, $entryMethod))) {
        fwrite(STDERR, "Smoke list entry not found: {$entry} (" . smokeListPath() . ")\n");
        exit(1);
    }
}
if ($jobs > 1) {
    $workerUnits = planParallelUnits($declared, $filters, openTestHistoryStore()->durations());
    [$runCount, $testResults, $testDurations, $failures] = runParallelUnits($workerUnits, $jobs);
    $declared = [];
}
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
        $failureMessage = null;

        try {
            $testObject->{$method}();
            $testResults[$testName] = true;
        } catch (Throwable $throwable) {
            $failureMessage = $throwable->getMessage();
            $failures[] = "{$testName} - {$failureMessage}";
            $testResults[$testName] = false;
        } finally {
            $completedAt = hrtime(true);
            $testDurations[$testName] = ($completedAt - $currentTestStartedAt) / 1_000_000_000;
            $currentTest = null;
            $currentTestStartedAt = null;
        }

        $result = ($testResults[$testName] ? 'PASS ' : 'FAIL ') . $testName;
        if ($failureMessage !== null) {
            $result .= ' - ' . $failureMessage;
        }
        // Keep multiline diagnostics intact below the timed result line.
        $lines = explode("\n", $result, 2);
        fwrite(
            $testResults[$testName] ? STDOUT : STDERR,
            sprintf("[%.2Fs] %s [%.2Fs]", ($completedAt - $runStartedAt) / 1_000_000_000, $lines[0], $testDurations[$testName])
                . (isset($lines[1]) ? "\n" . $lines[1] : '') . "\n"
        );
    }
}

if ($filters !== [] && $runCount === 0) {
    fwrite(STDERR, "No tests matched the supplied filters.\n");
    exit(1);
}

// A parallel worker hands its results to the coordinator, which records history once.
if ($workerResultsPath !== '') {
    $lines = '';
    foreach ($testResults as $testName => $passed) {
        $message = '';
        foreach ($failures as $failure) {
            if (str_starts_with($failure, $testName . ' - ')) {
                $message = substr($failure, strlen($testName) + 3);
            }
        }
        $lines .= ($passed ? 'pass' : 'fail') . "\t{$testName}\t" . sprintf('%.6F', $testDurations[$testName]) . "\t" . base64_encode($message) . "\n";
    }
    file_put_contents($workerResultsPath, $lines);
    $timingReportPrinted = true;
    exit($failures === [] ? 0 : 1);
}

$historyStore = openTestHistoryStore();
$testClassifications = $historyStore->recordResults($testResults, date('c'), $testDurations);

$prunedCount = 0;
// Only a complete run may forget tests that no longer exist.
if ($filters === []) {
    $prunedCount = $historyStore->pruneStaleEntries(array_keys($testResults));
}

printRunSummary($runCount, $failures, $testDurations, $testClassifications, $prunedCount, STDOUT);
if ($smokeEntries !== []) {
    fwrite(STDOUT, "Smoke set from tests/smoke.txt; the full suite is ./v3 fulltest.\n");
}
fwrite(STDOUT, sprintf("Wall time: %.2fs\n", (hrtime(true) - $runStartedAt) / 1_000_000_000));

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
function openTestHistoryStore(): TestRunHistoryStore
{
    $path = getenv('FORUM_TEST_HISTORY_DB_PATH');
    if ($path === false || trim($path) === '') {
        $path = __DIR__ . '/../state/test_run_history.sqlite';
    }
    $store = new TestRunHistoryStore($path, recoveryHighlightWindowRuns());
    $store->ensureSchema();
    return $store;
}

/**
 * Splits the selected tests into worker units, slowest first. A class is one unit unless its recorded
 * time is long; then its methods are grouped into chunks so one class does not set the run's floor.
 *
 * @param array<string, float> $durations
 * @return list<array{label: string, args: list<string>, estimate: float}>
 */
function planParallelUnits(array $declared, array $filters, array $durations): array
{
    $chunkSeconds = 20.0;
    $unknownSeconds = 0.2;
    $units = [];
    foreach ($declared as $class) {
        if (!str_ends_with($class, 'Test') || !shouldRunClass($class, $filters)) {
            continue;
        }
        $names = [];
        foreach (get_class_methods($class) as $method) {
            if (str_starts_with($method, 'test') && shouldRunMethod($class, $method, $filters)) {
                $names[] = "{$class}::{$method}";
            }
        }
        if ($names === []) {
            continue;
        }
        $total = array_sum(array_map(static fn (string $name): float => $durations[$name] ?? $unknownSeconds, $names));
        $wholeClass = $filters === [] || in_array($class, $filters, true);
        if ($total <= $chunkSeconds * 1.5 || count($names) === 1) {
            $units[] = ['label' => $class, 'args' => $wholeClass ? [$class] : $names, 'estimate' => $total];
            continue;
        }
        $chunk = [];
        $chunkTotal = 0.0;
        $part = 0;
        foreach ($names as $name) {
            $chunk[] = $name;
            $chunkTotal += $durations[$name] ?? $unknownSeconds;
            if ($chunkTotal >= $chunkSeconds) {
                $units[] = ['label' => $class . ' part ' . ++$part, 'args' => $chunk, 'estimate' => $chunkTotal];
                $chunk = [];
                $chunkTotal = 0.0;
            }
        }
        if ($chunk !== []) {
            $units[] = ['label' => $class . ' part ' . ++$part, 'args' => $chunk, 'estimate' => $chunkTotal];
        }
    }
    usort($units, static fn (array $a, array $b): int => $b['estimate'] <=> $a['estimate']);
    return $units;
}

/**
 * Runs units in up to $jobs worker processes. Each finished unit's output is printed whole, so lines
 * from different workers never interleave.
 *
 * @param list<array{label: string, args: list<string>, estimate: float}> $units
 * @return array{int, array<string, bool>, array<string, float>, list<string>}
 */
function runParallelUnits(array $units, int $jobs): array
{
    $runCount = 0;
    $results = [];
    $durations = [];
    $failures = [];
    $scratch = sys_get_temp_dir() . '/v3-test-workers-' . getmypid();
    @mkdir($scratch, 0700, true);
    $GLOBALS['testWorkers'] = [];
    $queue = $units;
    $index = 0;
    // Re-run the script that started this run (run.php, or a fixture that includes it).
    $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: __FILE__;
    fwrite(STDOUT, sprintf("Running %d units in up to %d workers.\n", count($units), $jobs));
    while ($queue !== [] || $GLOBALS['testWorkers'] !== []) {
        while ($queue !== [] && count($GLOBALS['testWorkers']) < $jobs) {
            $unit = array_shift($queue);
            $id = ++$index;
            $files = ['out' => "{$scratch}/{$id}.out", 'err' => "{$scratch}/{$id}.err", 'results' => "{$scratch}/{$id}.results"];
            $environment = getenv() + [];
            $environment['FORUM_TEST_WORKER_RESULTS'] = $files['results'];
            $process = proc_open(
                [PHP_BINARY, $script, ...$unit['args']],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', $files['out'], 'w'], 2 => ['file', $files['err'], 'w']],
                $pipes,
                dirname(__DIR__),
                $environment,
            );
            if (!is_resource($process)) {
                $failures[] = "{$unit['label']} - could not start a test worker";
                continue;
            }
            $GLOBALS['testWorkers'][$id] = ['process' => $process, 'unit' => $unit, 'files' => $files];
        }
        foreach ($GLOBALS['testWorkers'] as $id => $worker) {
            $status = proc_get_status($worker['process']);
            if ($status['running']) {
                continue;
            }
            proc_close($worker['process']);
            unset($GLOBALS['testWorkers'][$id]);
            fwrite(STDOUT, (string) @file_get_contents($worker['files']['out']));
            fwrite(STDERR, (string) @file_get_contents($worker['files']['err']));
            $lines = @file($worker['files']['results'], FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $line) {
                [$state, $name, $seconds, $message] = explode("\t", $line, 4);
                $runCount++;
                $results[$name] = $state === 'pass';
                $durations[$name] = (float) $seconds;
                if ($state !== 'pass') {
                    $failures[] = "{$name} - " . base64_decode($message);
                }
            }
            // A worker that died before reporting (fatal error, exit) loses its unit; say so.
            if ($lines === [] || !in_array($status['exitcode'], [0, 1], true)) {
                $failures[] = "{$worker['unit']['label']} - test worker exited with code {$status['exitcode']}" . ($lines === [] ? ' before reporting results' : '');
            }
            foreach ($worker['files'] as $file) {
                @unlink($file);
            }
        }
        if ($GLOBALS['testWorkers'] !== []) {
            usleep(20000);
        }
    }
    @rmdir($scratch);
    return [$runCount, $results, $durations, $failures];
}

function smokeListPath(): string
{
    $path = getenv('FORUM_TEST_SMOKE_LIST');
    return $path === false || trim($path) === '' ? __DIR__ . '/smoke.txt' : $path;
}

/** @return list<string> Class or Class::method names, one per line; # starts a comment. */
function readSmokeList(string $path): array
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Cannot read smoke list: {$path}\n");
        exit(1);
    }
    $entries = [];
    foreach ($lines as $line) {
        $line = trim(explode('#', $line, 2)[0]);
        if ($line !== '') {
            $entries[] = $line;
        }
    }
    return $entries;
}

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
