> **Feature plan:** [Step 1](./retraction_speed_step1_solution_assessment.md) · [Step 2](./retraction_speed_step2_feature_description.md) · [Step 3](./retraction_speed_step3_development_plan.md) · [Step 4](./retraction_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./retraction_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/retraction_speed`; [host ledger](../effort_estimation_trial.md#cycle-v3retraction_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved on 2026-11-09; Stage 1 complete; Stage 2 next.

**Cycle estimate: 0.25** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/retraction-speed` from `origin/main` at `0822944` (`main` has moved past the Step 1 starting revision `f046bd8`; nothing in between touches retractions). Planning commit `9c94d3c` holds approved Steps 1–3 and the debug log in this folder, links repaired, plus the plan index entry.
- The Step 1–3 drafts were earlier pushed to `plans/retraction_speed` at the session stop hook's request; this branch supersedes it.
- Whole-cycle **0.25** reaffirmed, 2026-11-09T23:40:20Z. Stage originals copied from Step 3: 0.04/0.14/0.07, non-additive. Claude, single exposed rater; copying is not a new rating.

## Stage 1 - Removal context check is shared

**Original estimate: 0.04**
**Retrospective: 0.03**

- Changes: new `ForumRewrite\Write\RemovalUpdateContext::forPost()`, moved unchanged from the private `SourceLabelRemoval::incrementalContext()`, which now calls it.
- Verification: `./v3 test --smoke DeleteRecordCommandTest HistoryPurgePlannerTest LocalAppSmokeTest ThreadTitleWithdrawalAndConsumersTest WriteApiSmokeTest`: 666 run, 666 passed. Full suite not run.
- Notes: no behaviour change.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
