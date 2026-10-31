> **Feature plan:** [Step 1](./deployment_config_step1_solution_assessment.md) · [Step 2](./deployment_config_step2_feature_description.md) · [Step 3](./deployment_config_step3_development_plan.md) · [Step 4](./deployment_config_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/deployment_config; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Branch: `feat/deployment-config`; planning commit `e51751cf`
**Cycle estimate: 0.85**
**Retrospective cycle assessment: 0.85**

# Deployment Config Step 4 Implementation Summary

## Step 4 entry checkpoint

- Checkpoint: Step 4 entry, 2026-10-30 UTC; whole-cycle **0.85 reaffirmed** from Step 3.
- Scope v1.1 / revised Option A; planning baseline `4abda3db`; implementation starts from `origin/main` `2ee0d198` (17 later upstream commits, one touching `PrivateConfigSchema` by adding an unrelated flag). R1 / P2; Claude (claude-opus-5-5) single exposed rater.
- Original stage-work estimates S1–S6/v1: 0.10 / 0.15 / 0.15 / 0.15 / 0.10 / 0.10, copied from the approved plan, not additive.
- Baselines at `2ee0d198` (separate worktree, run concurrently with Stages 1–4):
  - Full suite: **1732 run, 1726 passed, 6 failed** — `ApprovalSeedCommandTest::testSeedRetiresCachedPagesAndReleaseBeforeServingUpdatedState`, `LocalAppSmokeTest::testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `LocalAppSmokeTest::testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`, `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest::testStaticReleaseIncludesCompleteInventoryAndSafeAssets`, `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`.
  - Unmodified `tests/high_traffic_apache_test.py`: **fails** at the native-import step (line 191, exit 255), confirming that failure predates this cycle.

## Stage 1 - One private file location rule

**Original estimate: 0.10**
**Retrospective stage work: 0.08**

- Changes:
  - `PrivateConfig::path(string $projectRoot)`: `FORUM_SECRETS_PATH`, else `../forum-private/<checkout folder>/secrets.php` when it exists, else `../forum-private/secrets.php`. `PrivateConfig::load()`, `scripts/write_private_config.php` (`./v3 private-config`) and `scripts/terminal_llm_editor.php` (TUI) all use it; the three duplicated rules are gone. Help text updated.
  - New `PrivateConfigCommandTest::testPrivateConfigPathPrefersPerCheckoutFileThenSharedDefault`: shared default; per-checkout file wins once it exists; trailing slash; sibling checkout keeps the shared file; values load from the right file; explicit override; and a copied `site-a` checkout's `./v3 private-config view` reports the per-checkout path.
- Verification (all passed): `PrivateConfigCommandTest` 8/8, `PrivateConfigSchemaTest` 5/5, `FeatureFlagEvaluatorTest` 19/19, `SiteProfileRegistryTest` 8/8, `tests/terminal_llm_editor_test.py` and `tests/private_config_review_test.py`.
- Notes: the per-checkout file is used only when it exists, so existing single-site deployments are unaffected.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 2 - Deployment settings lookup

**Original estimate: 0.15**
**Retrospective stage work: 0.12**

- Changes:
  - New `DeploymentSettings` with `value($key, ?$projectRoot)`, `source(...)` and `projectRoot()` for `FORUM_SITE_ID`, `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`, `FORUM_PUBLIC_ARTIFACT_ROOT` and `FORUM_IMAGE_ROOT`. A non-empty environment variable wins, then the checkout's private file, else `null` so the caller applies its existing default. File paths must be absolute strings. Without an explicit root, the lookup uses the checkout containing the code. The parsed file is cached per process, keyed by path, mtime and size.
  - The six keys were added to `PrivateConfigSchema` as non-template settings, so `./v3 private-config view` lists them with their source and generated templates are unchanged.
  - `SiteProfileRegistry::active()` reads through the lookup. An unknown profile set in the private file is an error naming the file and the known profiles; an unknown environment value keeps today's fallback to the default profile, for compatibility.
  - Plan deviation, same outcome: `LocalRepositoryBootstrap` and `PresentationPathResolver` stay unchanged because they produce the fallback defaults. Callers in Stages 3–4 use `DeploymentSettings::value(...) ?: <existing default>`, which keeps every caller's current default exactly.
  - New `DeploymentSettingsTest` (registered in `tests/run.php`): precedence and source for every key, empty environment values ignored, file-driven QDB profile, unknown file profile error, unknown environment fallback, relative and non-string path rejection, per-checkout selection, keys absent from the template.
- Verification (all passed): `DeploymentSettingsTest` 7/7, `SiteProfileRegistryTest` 8/8, `PrivateConfigSchemaTest` 5/5, `PrivateConfigCommandTest` 8/8.
- Notes: this developer machine's private file sets none of the new keys (checked), so local test behavior is unchanged. Baselines still running; recorded in Stage 3.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 3 - Web app and shared classes

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - `public/index.php`, `Application`, `RouteServices`, `HighTrafficSafety`, `RetractionSweepFactory`, `FastmodTaggingPolicy`, `LocalWriteService` and `ImageStore` read the six settings through `DeploymentSettings::value(..., <project root>)`, each keeping its existing default. The high-traffic worker still receives the resolved profile as an argument.
  - `tests/high_traffic_apache_test.py`: new scenario after the existing `SetEnv` one. It restores `.htaccess` without `SetEnv`, puts `FORUM_SITE_ID => 'qdb'` only in the private file, restarts Apache and the CLI without `FORUM_SITE_ID`, and checks that normal, cold, warm (no PHP) and CLI-refreshed homepages are QDB's.
- Verification:
  - Full Apache harness: **blocked** at its existing native-import step (line 191, exit 255) before reaching either profile scenario. This is the same pre-existing failure recorded in the QDB diagnosis, and it also fails at the baseline (see entry checkpoint).
  - Scratch copy running only setup plus the two profile scenarios (not committed): **passed** both the request-scoped `SetEnv` scenario and the new private-file-only scenario; the script stopped afterwards only on its summary line, which refers to a timing variable from the cut section.
  - `HighTrafficTest` 6/6, `DeploymentSettingsTest` 7/7, `SiteProfileRegistryTest` 8/8, `WriteApiSmokeTest` 193/193 passed. `LocalAppSmokeTest` 154/157; all three failures also occur in the baseline at `2ee0d198` (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`).
- Notes: an unknown profile in the private file makes web requests fail with an error in the server log; status in Stage 5 surfaces the same message to the operator.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Stage 4 - CLI scripts and cron

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - 71 direct reads in 26 scripts now go through `DeploymentSettings::value()`; explicit command-line options still take precedence and each script keeps its existing default. `check_static_artifacts.php` now loads the autoloader.
  - Correction found here: `./v3 rebuild` with no arguments ignored `FORUM_REPOSITORY_ROOT` and always rebuilt the local default repository, so the CLI could disagree with the web app. It now uses the configured repository, and the local default is only initialized when none is configured.
  - The TUI home placeholder (`terminal_operator_ui.sh`), shown if the first status call fails, resolves the repository through the same lookup instead of `${FORUM_REPOSITORY_ROOT:-...}`.
  - The cron worker needs no change: it runs `php scripts/task_queue.php` from the checkout, which now reads the private file.
  - Tests: `testNoCodeReadsDeploymentSettingsDirectlyFromTheEnvironment` scans `src`, `scripts`, `public` and `v3` for `getenv`, `$_SERVER`/`$_ENV` and shell `${...}` reads of the six keys (checked against the pre-change `status.php` and TUI script: both caught). `testCliUsesPrivateFileRepositoryAndDatabaseWithoutEnvironment` runs `rebuild` and `status` with all six variables unset and only the private file set, and checks they use its repository and database.
- Verification:
  - `DeploymentSettingsTest` 9/9. Passed: `StatusCommandTest` 7, `OperatorStatusCollectorTest` 6, `ImportInstanceCommandTest` 9, `RepositoryArchiveImportCommandTest` 1, `FastmodAuditCommandTest` 5, `ApprovalReceiptMaintenanceTest` 6, `ArchiveThreadCommandTest` 3, `PostSignatureAuditCommandTest` 2, `AgentReplyCommandTest` 5, `AgentResponseFormatReadinessTest` 4, `RetractionSweepTest` 10, `TerminalOperatorUiCommandTest` 3, `TerminalOperatorUiDashboardTest` 1, `DevServerLogTest` 9, `HighTrafficPublicationTest` 4; Python terminal UI navigation, PTY and acceptance tests.
  - `ApprovalSeedCommandTest::testSeedRetiresCachedPagesAndReleaseBeforeServingUpdatedState` failed and also fails in the baseline. `TaskQueueCommandTest::testRebuildWorkerRecordsPrivateProgressCheckpoints` and `DocsPublicationFlowTest::testReportedResumeCompletesWithChangedAndMissingDamagedOrLegacyDocs` ("HTTP server failed to start") failed once while the baseline suite was running concurrently, then passed twice each when rerun alone; treated as intermittent.
- Notes: `backfill_unicode_risk.php` still initializes the local default repository eagerly (pre-existing; unchanged).

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Stage 5 - Status shows where settings come from

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes:
  - `./v3 status` ends with a **Deployment settings** section: the private file in use (marked `missing` when absent) and each of the six settings with its effective value and source (`environment`, `private file <path>`, `--repository-root`/`--database-path`, or `default`). An unknown environment profile is shown as falling back to the default. A setting that can't be resolved (for example an unknown profile in the private file) shows `ERROR` with the message instead of aborting the section. Only these six non-secret settings are printed.
  - Warns when tracked `public/.htaccess` or `public/index.php` has local edits (via `git status`), pointing to the private file. `--summary` (the TUI home) shows the warning line only when there is one.
  - `./v3 task-queue cron` prints the private file the worker will read (`Site settings: <path>`).
  - Tests: `testStatusReportsEachSourceAndWarnsAboutTrackedDeploymentEdits` (copied checkout named `qdb` in a temporary Git repository; per-checkout file, environment and default sources; a secret in the same file not printed; `.htaccess` edit warning; missing file falls back to default); `TaskQueueCommandTest` asserts the cron output's settings path.
- Verification (all passed): `DeploymentSettingsTest` 10/10, `TaskQueueCommandTest` 12/12, `StatusCommandTest` 7/7, `TerminalOperatorUiCommandTest` 3/3, `TerminalOperatorUiDashboardTest` 1/1, Python terminal UI acceptance test.
- Notes: no web UI change; navigation icons and UI copy audit are not applicable.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Stage 6 - Documentation, operator checklist and release verification

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes:
  - Production deploy runbook: the `Environment Variables` and `Site Profile` sections are replaced by one [Deployment Settings](../../runbooks/production_deploy.md#deployment-settings) section: the six settings and their defaults; the private file as their home; precedence (environment, private file, default, with command options first); which private file a checkout uses and the folder-rename caveat; the `./v3 status` and `./v3 task-queue cron` checks; moving existing `SetEnv` lines step by step; recovery; the QDB operator acceptance checklist with exact commands; and local development of another profile. The lock-timeout setting and deployment checklist step 6 link to it.
  - CLI reference: path precedence lists the private file between the environment and the bootstrapped default; status and `private-config --path` describe the per-checkout lookup. The README private-config paragraph says the same.
  - `docs/examples/apache_vhost.conf` and `docs/examples/env.production.example` point to the private file and keep the `SetEnv`/environment lines as a documented alternative that still takes precedence.
  - New concept entry `docs/concepts/deployment_configuration.md` (purpose, QDB scenario, sources: `DeploymentSettings`, `PrivateConfig`, runbook section). Private-config help text needed no change (updated in Stage 1).
  - Study ledger: Step 4 entry and closeout appended under `Cycle v3/deployment_config` in `docs/plans/effort_estimation_trial.md`.
  - Review corrections in this stage: the runbook profile list adds `mitrapclub`, which `SiteProfileRegistry` registers; the acceptance `curl` line no longer asserts an exact `<title>` string.
- Verification:
  - Full suite at HEAD (`./v3 test`): **1743 run, 1734 passed, 9 failed**, against the baseline's 1732 run / 6 failed. The 11 added tests are this cycle's. The six baseline failures reproduce identically (`ApprovalSeedCommandTest`, three `LocalAppSmokeTest`, `PlatformDocsStaticTest`, `ReadModelBuilderTimingTest`). The three other failures (`BoundedProcessTest::testNoisyAndStalledDescendantsAreBounded`, `HighTrafficTest::testRendererLimitErrorsNameTheLimitAndLastStage`, `PrivateMessageHistorySyncTest::testConcurrentSyncWritesLeaveForegroundMessageStoreResponsive`) are timing-sensitive and occurred while another test suite ran concurrently on this machine (load average about 10 on 12 cores); rerun alone, all three classes pass 24/24. Passed, with no regression attributable to this cycle.
  - Links and anchors: every relative link in the four changed documents resolves; `#deployment-settings` exists; no links to the removed `#environment-variables` or `#site-profile` anchors remain outside historical plans. `git diff --check` clean. Passed.
  - Stale guidance: `SetEnv FORUM` and `../forum-private/secrets.php` searched across README, docs, `src` and `scripts`; remaining mentions are the lookup rule itself, historical plans and the QDB diagnosis record. Passed.
  - Help text: `./v3 private-config --help` states the lookup rule. Passed.
  - QDB host acceptance: **pending**. It is an operator-run step on the QDB host (checklist in the runbook); nothing here replays that host, and the Apache harness remains blocked at its pre-existing native-import failure.
- Notes: documentation-only stage; no runtime change. Navigation icons, UI copy audit and migrations are not applicable.

*— Claude · 2026-10-31 UTC · Model: claude-fable-5-1*

## Closeout

Delivered scope v1.1 / revised Option A, complete as planned: one private file location rule with per-checkout lookup; a `DeploymentSettings` lookup with environment, private file, default precedence for the six site and path settings; the web app, every `./v3` command, the TUI and cron reading through it with a guard test against direct environment reads; `./v3 status` reporting each value's source and warning about local edits to tracked deployment files; and runbook, CLI reference, examples and concept entry. Six planned stages, six completed. Commits: planning `e51751cf`; S1 `06f903e7`; S2 `5536338c`; S3 `6a464910`; S4 `40fd290f`; S5 `53b3f5a2`; S6 is this documentation closeout commit (hash recorded in the debug handoff). Implementation started from `origin/main` `2ee0d198`; planning baseline `4abda3db`.

Verification and omissions: every stage's focused suites passed and are recorded above; the Step 4 full-suite comparison is in Stage 6. Pre-existing failures reproduced at the baseline and unchanged here: six full-suite tests listed in the entry checkpoint and the Apache harness native-import step. Not verified: the QDB host (operator acceptance pending, runbook checklist provided) and the effect of a stray pre-existing path key in a real deployment's private file (this machine's file has none). Corrective work versus new requests: one plan deviation in Stage 2 (defaults left in their existing classes, same outcome) and two documentation corrections in Stage 6; no new requests, splits or follow-up cycles. No merge or push in this cycle.

Study: `v3/deployment_config`, scope v1.1, baseline `4abda3db`, R1 / P2. Prospective 0.80 (intake and Step 1), 0.85 (Step 1 revision 1, reaffirmed at Steps 2, 3 and Step 4 entry) retained. **Retrospective cycle assessment: 0.85** by Claude (claude-fable-5-1), a different model from the prospective rater (claude-opus-5-5); it is a scope assessment, not measured labor or a replacement projection. Stage originals 0.10/0.15/0.15/0.15/0.10/0.10 and retrospectives 0.08/0.12/0.15/0.15/0.10/0.10 are non-additive. Maintainer comfortable-fit judgment and study acceptance pending; no accepted-outcome increment.

*— Claude · 2026-10-31 UTC · Model: claude-fable-5-1*
