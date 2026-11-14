> **Feature plan:** [Step 1](./thread_activity_drawer_step1_solution_assessment.md) · [Step 2](./thread_activity_drawer_step2_feature_description.md) · [Step 3](./thread_activity_drawer_step3_development_plan.md) · [Step 4](./thread_activity_drawer_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_activity_drawer`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.55**

Branch: `feat/thread-activity-drawer`. Planning commits `0f456b8` (Steps 1–2) and `4007172` (Step 3, approved 2026-11-14T20:04:00Z, "approved step 3").

## Step 4 entry

2026-11-14T20:05:00Z, `railspike-effort` / `v3/thread_activity_drawer`, Step 4 entry. Scope v1, unchanged. Revision `4007172`. R1, P2, single exposed Claude rater. Step 3 projection reaffirmed at **0.55** before implementation. Original stage estimates are copied from Step 3, not re-rated: Stage 1 0.10, Stage 2 0.15, Stage 3 0.20, Stage 4 0.06.

Baseline: `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` fails in the smoke set on `4007172` before any change. It is unrelated to this cycle.

## Stage 1 - Shared hashtag rule and tag-only test

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - New `Support/ThreadLabels.php`: `fromBody()` holds the label rule that `LocalWriteService::extractThreadLabelsFromBody` used, and `isTagOnly()` decides whether a reply says nothing but labels.
  - `LocalWriteService::extractThreadLabelsFromBody` now delegates to `ThreadLabels::fromBody`, so writes and the thread page share one rule.
  - New `tests/ThreadLabelsTest.php`, registered in `tests/run.php`, with the table of reply bodies from Step 3.
- Verification:
  - Passed: `./v3 test ThreadLabelsTest` (2 tests).
  - Passed: `WriteApiSmokeTest::testCreateThreadWithHashtagsWritesThreadLabelRecordAndRendersLabels` and `::testCreateReplyWithHashtagsWritesThreadLabelRecordForTargetThread`. Label writes are unchanged.
  - Smoke set: 454 of 455 passed. The one failure is the baseline failure above, reproduced on `4007172` without this change.
  - Full suite not run, per `AGENTS.md`.
- Notes:
  - Events live on the `threads` row, so a reply cannot carry one. `thread_type` covers typed roots. The plan's "event" check therefore needs no extra field.
  - `#Bug` and `#bug, #ux` count as text. The first is not a valid label; the comma is left over.
- Estimate details: original scope unchanged; no revision. Retrospective: a smaller refactor than expected, because the rule moved without changes.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 2 - Flag and tag-only leaf filter

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - New flag `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` ("Thread activity drawer"), off by default, site-mutable, appended to the `RENDERING` group per `CONTRIBUTING.md`. Added to the env-reset lists in `FeatureFlagEvaluatorTest`.
  - New `ReadModel/TagOnlyLeafFilter.php`: `split()` returns kept and moved replies. It follows `RetractedLeafFilter`: only unanswered replies move, and it repeats so a chain of tag-only replies goes whole.
  - `ThreadAndPostPageController::thread` passes `threadActivityDrawerEnabled`. `templates/pages/thread.php` splits replies after `RetractedLeafFilter` and before continuation flags; the moved replies wait in `$movedTagOnlyReplies` for Stage 3.
  - Tests: new `TagOnlyLeafFilterTest` (registered in `tests/run.php`); new `WriteApiSmokeTest::testThreadActivityDrawerFlagMovesTagOnlyLeavesOutOfTheConversation`.
- Verification:
  - Passed: `TagOnlyLeafFilterTest`, `RetractedLeafFilterTest`, `PostSignalsTemplateTest`, `FeatureFlagEvaluatorTest` (23 tests) and the new page test.
  - The page test covers: flag off shows every reply; flag on removes the unanswered tag-only reply; an answered tag-only reply and its answer stay; the reply after the moved one is still a continuation.
  - Smoke set: 452 of 453 passed; the one failure is the baseline failure.
  - Full suite not run, per `AGENTS.md`.
- Notes:
  - Between Stage 2 and Stage 3, turning the flag on hides tag-only leaves with nowhere to show them. The flag is off by default and the branch is not merged, so no reader sees this.
  - The root card's reply count now counts shown replies only, as planned.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
