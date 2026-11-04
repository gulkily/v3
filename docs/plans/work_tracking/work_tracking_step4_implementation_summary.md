> **Feature plan:** [Step 1](./work_tracking_step1_solution_assessment.md) · [Step 2](./work_tracking_step2_feature_description.md) · [Step 3](./work_tracking_step3_development_plan.md) · [Step 4](./work_tracking_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_tracking`; [host ledger](../effort_estimation_trial.md#cycle-v3work_tracking). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved; implementation in progress on `feat/work-tracking`.

**Cycle estimate: 1.00** (Step 4 entry, reaffirmed)

## Study entry

2026-11-04T00:55:14Z; planning commit `be2990e`; scope v2/A, original baseline `561f7dd1113c3021ba0d7d1b28361865c40de2e2`, R1/P2. Step 3 whole-cycle projection **1.00 reaffirmed** before implementation. Stage-work originals copied from the approved plan (Claude, single exposed rater; copying is not a new rating): S1 0.15, S2 0.12, S3 0.15, S4 0.06, S5 0.12, S6 0.15, S7 0.12, S8 0.10. Non-additive.

## Stage 1 - Thread status from `status:` replies

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.12**

- Changes:
  - New `src/ForumRewrite/Work/WorkStatusDirective.php`: `extract(string $body): ?array{status, note}`; same line rules as `TitleDirective` (quoted lines skipped, first valid match wins, case-insensitive), values `open|active|done|dropped`, optional note up to 200 characters.
  - New `src/ForumRewrite/Work/WorkItems.php`: `isWorkThread()`, `item()`, `list(words, status, now, staleDays)`. Status is derived at read time from posts by verified, approved, unretired members; no directive means `open`; staleness is `active` with no thread activity for 7 days.
  - Thread page shows a single `Status:` line on `#work` threads only (`ThreadAndPostPageController::thread`, `templates/pages/thread.php`).
  - New `tests/WorkItemsTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php WorkItemsTest`: 4/4 passed (directive parsing; latest-valid-wins ignoring unapproved, guest, unverified and retracted lines; staleness, filters, stable order, retraction; thread page renders status only for work threads).
  - `php tests/run.php LocalWriteServiceTitleDirectiveTest RailspikeFeatureFlagTest RailspikeSyncCommandTest RailspikeWorkflowTest ThreadTitleTest ThreadTitleWithdrawalAndConsumersTest`: 53/53 passed.
  - Rebuild parity holds by construction: nothing is stored beyond existing post rows. Not separately tested.
  - Phone/desktop visual check: not done in this stage; the line reuses the existing `meta` style. Deferred to the Stage 8 production check.
- Notes:
  - Deviation from plan wording: derivation happens at read time rather than in a new read-model column, which avoids a schema change. Same behavior.
  - Static thread artifacts refresh when a reply is posted, so the status line follows the existing invalidation.
- Estimate details: original scope as planned; no revision; retrospective lower because no schema or write-path change was needed.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
