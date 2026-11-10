> **Feature plan:** [Step 1](./signed_post_speed_step1_solution_assessment.md) · [Step 2](./signed_post_speed_step2_feature_description.md) · [Step 3](./signed_post_speed_step3_development_plan.md) · [Step 4](./signed_post_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./signed_post_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/signed_post_speed`; [host ledger](../effort_estimation_trial.md#cycle-v3signed_post_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved on 2026-11-10; Stage 1 complete; Stage 2 next.

**Cycle estimate: 0.25** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/signed-post-speed` from `origin/main` at `a651718` (`main` has moved past the Step 1 starting revision `60ff7fd`; nothing in between touches the signed route). Planning commit `282c480` holds approved Steps 1–3 and the debug log in this folder, links repaired, plus the plan index entry.
- The Step 1–3 drafts were earlier pushed to `plans/signed_post_speed` at the session stop hook's request; this branch supersedes it.
- Whole-cycle **0.25** reaffirmed, 2026-11-10T07:09:37Z. Stage originals copied from Step 3: 0.04/0.15/0.06, non-additive. Claude, single exposed rater; copying is not a new rating.

## Stage 1 - Fallbacks to a full rebuild log why

**Original estimate: 0.04**
**Retrospective: 0.04**

- Changes:
  - `LocalWriteService::logRebuildFallback()` writes one `error_log()` line: `Read model: <update> update fell back to a full rebuild: <reason>`. `readModelNotCurrentReason()` names why the read model could not take a targeted update (no read model, stale marker, schema or root differs, metadata unreadable).
  - Called from the not-current branch and the error branch of all six `synchronize*DerivedState()` methods (post, thread label, thread title, post reaction, identity link, approval) and from the retraction fallback (cycle 2).
  - The line names the update rather than the write that called it (the plan said both); each update maps to one kind of write, and passing the write name through six methods added nothing.
  - New test `WriteApiSmokeTest::testFallbacksToAFullRebuildAreLogged`: a write with a stale marker and a write whose update throws each log exactly one line with the reason.
- Verification: `./v3 test --smoke WriteApiSmokeTest SingleRequestPostingTest`: 662 run, 662 passed. Full suite not run.
- Notes: fallback lines now also appear in test runs, where many tests deliberately force fallbacks.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
