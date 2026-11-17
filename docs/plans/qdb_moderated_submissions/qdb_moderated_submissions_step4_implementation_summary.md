# QDB moderated submissions Step 4 implementation summary

> **Feature plan:** [Step 1](./qdb_moderated_submissions_step1_solution_assessment.md) · [Step 2](./qdb_moderated_submissions_step2_feature_description.md) · [Step 3](./qdb_moderated_submissions_step3_development_plan.md) · [Step 4](./qdb_moderated_submissions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/qdb_moderated_submissions`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.65**

Branch `feat/qdb-moderated-submissions` from `6bfe1243`; planning commit `3b34a30`.

## Step 4 entry

- Step 3 approved 2026-11-17. Whole-cycle projection **0.65** reaffirmed after the planning commit. Stage originals 0.15/0.12/0.08/0.12/0.08/0.06 copied from Step 3, non-additive. Claude single exposed rater; R1 hashes not re-verified.

## Stage 1 - Pending rule in shared listings

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - New flag `FORUM_QDB_QUEUE_ENABLED` (site-mutable, off).
  - New `src/ForumRewrite/Qdb/QdbModeration.php`: the queue state, its SQL conditions (`listedSqlSuffix`, `queueSql`) and `state()` for one quote.
  - `ScoreVisibility::listedSql` appends the moderation condition, so every public listing that already hides flagged posts also hides pending and rejected quotes. On other sites, or with the queue off, the condition is empty.
  - `Application` registers the repository with `QdbModeration::useRepository`, beside `SignatureCheckCache::useBeside`.
  - New `src/ForumRewrite/Security/FeatureFlagSwitchOn.php`: the first operator-signed switch-on of a flag. `AttributionCutoff` now uses it instead of its own copy of the loop.
- Verification:
  - `./v3 test --smoke QdbModerationTest QdbPageTest ScoreVisibilityTest PublicOfflineSnapshotBuilderTest QdbWelcomeSelectionTest QdbExperienceRoutingTest ReadModelProvenanceTest FeatureFlagChangePageTest FeatureFlagEvaluatorTest`: 475 passed. Full suite not run.
  - New `QdbModerationTest`: archive, anonymous, visitor, operator, news, approved (both key notations), self-approved, rejected, old rejected, approved-then-rejected and non-quote rows; inactive queue; empty operator list; unsigned switch-on.
  - `FeatureFlagEvaluatorTest::testRegistryListsCurrentPublicFlags` updated for the new flag.
  - Timing on 20,000 quotes with 60,000 tag rows: Latest 47 ms off, 52 ms on. A first version with correlated lookups took 222 ms; the tagged sets are now built once per query.
- Notes:
  - **Deviation from Step 3:** no `QDB_QUEUE_STARTED_AT` private setting. The start time is the first operator-signed switch-on record of the flag, as `AttributionCutoff` already does. It lives in Git and no one but an operator can set or move it. Without a signed switch-on, nothing waits. New understanding, not changed scope.
  - **Deviation from Step 3:** the rule sits in `ScoreVisibility::listedSql` rather than separately in `QdbPage`, Random and news. That one fragment reaches `ThreadRepository::fetchThreads` (board, RSS, Random, news), `QdbPage`, `QdbWelcomeSelection`, `ThreadKeysetPage`, `AuthoredContentRepository` and the offline snapshot.
  - Readers that bypass the rule, by design: `ThreadRepository::byId`, `Application::fetchThread`, `QdbQuoteNumbers::resolve` (permalinks) and `StaticArtifactBuilder::fetchVisibleThreadIds` (static permalink pages). Stage 3 marks pending permalinks; Stage 5 rechecks static builds.
  - Operator approval reads `viewer_tags` from active operators. `approved` and `rejected` are not yet writable tags; Stage 4 adds them as signed, operator-only tags. Until then nobody can write them.
  - The signed switch-on path is covered only for the unsigned case; no test fixture signs feature-flag changes today. The qdb.us replay in Stage 6 covers the signed path.
- Estimate details: original scope as planned; no revision. Retrospective matches: the main extra was the performance fix.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
