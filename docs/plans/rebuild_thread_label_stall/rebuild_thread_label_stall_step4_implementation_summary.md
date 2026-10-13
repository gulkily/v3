> **Feature plan:** [Step 1](./rebuild_thread_label_stall_step1_solution_assessment.md) · [Step 2](./rebuild_thread_label_stall_step2_feature_description.md) · [Step 3](./rebuild_thread_label_stall_step3_development_plan.md) · [Step 4](./rebuild_thread_label_stall_step4_implementation_summary.md)

Debug mode: enabled

Planning commit: `686d8766`. Branch: `feature/rebuild-progress`.

## Stage 1 - Label history batching
- Changes: Bulk label provenance with resolution timing, isolated Git fixtures, and process-count/provenance regressions. Combined merge diffs preserve merge-resolution attribution for newly batched families; existing post behavior remains unchanged.
- Verification: `php tests/run.php ReadModelProvenanceTest ReadModelThreadLabelsTest` — 6 passed. Covers ordinary merges, conflicting merge resolution, untracked paths, invalid Git HEAD, no-Git label fixtures, scores and votes. Label history calls stay at one for 1 versus 110 generated records.
- Notes: Before edits, captured three runs on a fixed local 386-commit history with 210 generated labels, 135 reactions, 40 flag changes plus the minimal fixture. Label baseline: 7946.1 / 8484.1 / 11496.9 ms. First batched sample: 94.5 ms; all non-metadata/non-sequence tables matched baseline. Benchmark artifacts retained in `/tmp/rebuild-history-76baf7d9581b` during this run. A failing merge-resolution regression exposed the need for combined diffs; it passes after the correction. No production execution.

## Stage 2 - Reaction and flag history batching
- Changes: Extended bulk provenance/timing to reactions and signed feature-flag changes. Registered previously unlisted reaction regressions with the normal test runner.
- Verification: Provenance suite 6 passed; reaction moderation/deduplication 2 passed; signed flag evidence, history recovery, and duplicate activity regressions 3 passed. Process counts stay at one per family for 1 versus 105 records; missing flag history omits its activity and missing reaction history preserves null attribution. Full benchmark domain-table comparison passed; reactions 76.0 ms versus baseline 2351.0 / 2437.4 / 2267.9 ms, activity 51.3 ms versus 308.1 / 247.5 / 414.0 ms.
- Notes: Fixed a test-owned open SQLite cursor before rebuilding the same fixture; the resulting lock failure was test setup, not production code. Debug remains enabled. No schema or timestamp fallback changes.

## Stage 3 - Indexing progress and timings
- Changes: Timed parsing, sorting, derivation, summary/record/activity writes, approval passes, profile counting/linking, and commit-history processing. A shared keyed iterator reports completed work (including skipped records), empty batches, and at most 100 intermediate updates plus boundaries per loop. Blocking Git reads have explicit boundaries; failed measurements say failed. Repeated timing keys accumulate across passes; existing keys remain available.
- Verification: Timing/label/reaction/subject regressions 11 passed; timing/approval/legacy-timestamp regressions 15 passed. Covers parsing-before-derivation-before-writing order, 252-record counters, empty batches, silent optional reporters, parse-failure propagation, and unchanged approval/rename behavior. All benchmark domain tables still match baseline. `git diff --check` passed.
- Notes: Nested timings overlap and must not be summed as total rebuild time. Private iterator/duration helpers extend the existing reporter rather than adding external signatures. Debug remains enabled; transaction/promotion/snapshot reporting follows in Stage 4.

## Stage 4 - Lifecycle reporting and recovery
- Changes: Timed final transaction commit, initial and pre-promotion validation, lock acquisition/wait failure, replacement, and snapshot bootstrap. Shared lifecycle reporter preserves optional callbacks and rethrows original failures; existing interfaces, locking, promotion, and recovery guidance are retained.
- Verification: Candidate/snapshot/timing regressions 12 passed; new lock-timeout regression passed separately. Injected metadata mismatch cleans the candidate and preserves live bytes; sidecar rejection preserves live/candidate/sidecar bytes; timeout leaves candidate unpromoted; snapshot failure reports failed and retains post-promotion retry guidance. `git diff --check` passed.
- Notes: No heartbeat during blocking operations. Debug remains enabled. Validation and replacement timings are separate so a reported successful validation does not imply promotion succeeded.

## Stage 5 - End-to-end verification and evidence
- Changes: CLI reference explains parsing versus indexing, bounded counters, blocking-operation limits, fallback outcomes, and overlapping/accumulated timings. Snapshot command regressions now invoke normal `./v3 rebuild`; updated the smoke test's two renamed history-progress expectations.
- Verification: Normal CLI rebuild on the fixed Git fixture promoted a model whose domain tables exactly match baseline and produced a 40,960-byte public snapshot. CLI success/private-mode/post-promotion failure plus updated smoke assertion: 4 passed. Full `./v3 test`: 1,120 run, 1,110 passed, 10 failed; one failure was the old progress wording already corrected and verified by the targeted run. The other nine were reproduced on pre-change commit `686d8766`, including the same private configuration for environment-dependent checks. No feature regression remains identified. `git diff --check` passed.
- Benchmark: Linux/WSL, PHP 8.1.2, Git 2.34.1; identical 386-commit history, 212 labels (210 generated), 135 reactions, 40 signed flag changes, 3 posts, 2 threads, and 390 activity rows. Three local samples, milliseconds:

| Phase | Before | After |
| --- | --- | --- |
| Label indexing | 7946.1 / 8484.1 / 11496.9 | 161.6 / 140.4 / 158.1 |
| Reaction indexing | 2351.0 / 2437.4 / 2267.9 | 88.1 / 88.0 / 89.6 |
| Activity indexing | 308.1 / 247.5 / 414.0 | 68.2 / 51.3 / 61.3 |

- Query counts: label/reaction/flag history invocations fell from **212 / 135 / 40** to **1 / 1 / 1**. Each final sample and normal CLI output matched all baseline domain tables (metadata and SQLite sequence bookkeeping excluded). Measurements are local, with concurrent host activity and a small post/thread population; they establish the history-query improvement, not production latency or large-table write performance.
- Existing failures: `LocalAppSmokeTest::{testApplicationRendersCoreRoutes,testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags}`; `OfflineSnapshotBootstrapTest::{testPublishesWhenNoSnapshotIsServed,testRetainsAnExistingServedSnapshot}`; `OfflineSnapshotPublishCommandTest::{testPublishBuildsOnlyIndependentOfflineSnapshot,testPublishReportsSelectedProfileWithoutChangingExplicitRootPrecedence}`; `QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`; `WriteApiSmokeTest::{testTaskQueueProcessesQueuedAgentReplyOnce,testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote}`. These cover existing UI expectations, offline fixture schema omissions, and queue output; they remain outside this feature's scope.
- Environment limitation: A final clean suite rerun could not start (`php: command not found`); PHP and its versioned executable disappeared from the shared environment after the completed suite and targeted checks. No system package changes were made by this work. Logs: `/tmp/rebuild-progress-full-suite.log`, `/tmp/rebuild-progress-final-suite.log`; benchmark data: `/tmp/rebuild-history-76baf7d9581b` (ephemeral).
- Completion: All five implementation stages and feature-specific verification are complete. The repository-wide green-suite release gate remains unmet due to the nine reproduced baseline failures; no production deployment or production benchmark was performed. Debug remains enabled.
