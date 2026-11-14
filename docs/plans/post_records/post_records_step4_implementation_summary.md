> **Feature plan:** [Step 1](./post_records_step1_solution_assessment.md) · [Step 2](./post_records_step2_feature_description.md) · [Step 3](./post_records_step3_development_plan.md) · [Step 4](./post_records_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/post_records`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Split child `v3/retraction_target_id` merged in #189.

Extended estimates: enabled (inherited from Step 3)

**Cycle estimate: 0.95** (Step 4 entry; reaffirms the Step 3 projection unchanged)

Approval: Approved Step 3 by the maintainer, 2026-11-14T08:18:00Z. Branch `feat/post-records` from `523b8171`. Planning commit `ff7d755`.

Stage originals, copied from Step 3 (not new ratings): Stage 1 **0.12**, Stage 2 **0.15**, Stage 3 **0.10**, Stage 4 **0.10**, Stage 5 **0.15**, Stage 6 **0.15**, Stage 7 **0.15**, Stage 8 **0.05**. Stage work only; they don't add up to the cycle.

Tests: per the repository rules, only the smoke set plus the tests for what changed, through `./v3 test --smoke …`. The full suite was not run.

## Stage 1 - A retraction can name a reaction

**Original estimate: 0.12**
**Retrospective estimate: 0.12**

- Changes:
  - `RemovalTarget::family()` returns `post`, `post-reaction` or `thread-label`, and `RemovalTarget::sourceCandidates()` returns the target's own record paths.
  - `PostRetractionRecordParser` rejects a legacy `Post-ID` request that names a reaction.
  - New `AcceptedRetraction::prepareReaction()`. The receipt holds the reaction's thread in `Thread-ID`, the post it was on in `Parent-ID`, and its creation time in `Target-Created-At`.
  - `AcceptedRemoval::all()` now returns post removals only. A new `AcceptedRemoval::reactions()` returns reaction removals. This keeps reaction receipts out of rebuild placeholders, post suppression, label ownership and history-purge planning, all of which call `all()`.
  - `RetractionDeletion::commit` removes the target through `sourceCandidates()`, and its commit message names the family.
  - `ContentImportPlanner` won't import a reaction that was retracted locally.
  - New `tests/ReactionRetractionTest.php` (4 tests).
- Verification:
  - Passed: `./v3 test --smoke ReactionRetractionTest RetractionTargetIdTest RemovalReceiptTest RetractionReceiptTest AcceptedRetractionTest ReadModelPostRetractionsTest RetractionTransactionTest RetractionMaintenanceTest HistoryPurgePlannerTest ContentImportPlannerTest ContentImportRecoveryTest DeleteRecordCommandTest`, 509 run, 509 passed, 0 failed.
  - Not run: the full suite.
- Notes:
  - Step 3 planned to filter inside `ReadModelBuilder`, `AcceptedRetraction` and `RetractedPosts`. One filter in `AcceptedRemoval::all()` covers all of them, plus history purge and imports.
  - `RetractionSweep` still counts reaction receipts when choosing its artifact cutoff time, which is harmless.
- Estimate details: original scope as in Step 3, unchanged; no revisions. Retrospective: as planned. Rater: Claude, the same exposed rater.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 2 - Retract a like through the API

**Original estimate: 0.15**
**Retrospective estimate: 0.15**

- Changes:
  - `LocalWriteService::prepareRetraction` and `finalizePreparedRetraction` branch on `RemovalTarget::family()`.
  - New `requireRetractableReaction()` loads the like from `records/thread-labels/` or `records/post-reactions/`. It accepts only a like, meaning an `add` with exactly `like` and no source post. It resolves the like's thread, and allows the liker or a current approved operator (`RetractionAuthority::currentOperator`).
  - The request's `Author-Identity-ID` is the liker. An operator adds `Actor-Identity-ID` and `Retraction-Class: operator`, as for posts.
  - Finalize accepts the like through `AcceptedRetraction::prepareReaction`, commits through `RetractionDeletion`, and then runs a full derived-state refresh. Stage 3 replaces that refresh.
  - The replay and retry paths are shared with posts.
  - Tests: `WriteApiSmokeTest::testMembersRetractTheirOwnLikesAndOperatorsRetractAnyLike` covers a thread like: a stranger is refused, the liker retracts, the file is gone, the receipt and commit message are written, `viewer_tags` drops the like, no post row appears, re-liking writes a new like, a replay answers `already_retracted`, an operator retracts someone else's like, and a full `./v3 rebuild` agrees. `testReplyLikesRetractAndOtherReactionsAreRefused` covers a reply like (receipt `Thread-ID` and `Parent-ID`), re-liking, a flag being refused, and a missing record.
- Verification:
  - Passed: `./v3 test --smoke ReactionRetractionTest RetractionTargetIdTest RetractionReceiptTest AcceptedRetractionTest ReadModelPostRetractionsTest RetractionTransactionTest WriteApiSmokeTest SingleRequestPostingTest PostRetractionScriptTest`, 704 run, 704 passed, 0 failed.
  - Not run: the full suite.
- Notes:
  - The first draft of the test asserted on `vote_count`. That count includes only approved members, so it was replaced by checking `viewer_tags` directly.
- Estimate details: original scope as in Step 3, unchanged; no revisions. Retrospective: as planned. Rater: Claude, the same exposed rater.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 3 - Targeted update after retracting a like

**Original estimate: 0.10**
**Retrospective estimate: 0.08**

- Changes:
  - `refreshAfterReactionRetraction` now calls the like path's own helpers, `synchronizeThreadLabelDerivedState` or `synchronizePostReactionDerivedState`. These re-derive the thread's labels or the post's reactions from the remaining files, and fall back to a full rebuild if the read model isn't current or the update fails.
  - Page invalidation matches what a new like invalidates: the reply's page, or the thread for a root.
  - The thread-like test calls `finalizePreparedRetraction` directly, so it can assert that `read_model_incremental_update` ran and `read_model_rebuild` didn't. The full rebuild at the end of that test checks that the targeted result matches a rebuild: no like in `viewer_tags` and no reaction in `posts`.
- Verification:
  - Passed: `./v3 test --smoke WriteApiSmokeTest ReactionRetractionTest IncrementalReadModelUpdaterTest`, 667 run, 667 passed, 0 failed.
  - Not run: the full suite.
- Estimate details: original scope as in Step 3, unchanged. Retrospective 0.08, slightly under: the existing helpers needed no changes. Rater: Claude, the same exposed rater.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
