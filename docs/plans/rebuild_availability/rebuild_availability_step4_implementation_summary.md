> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](../effort_estimation_trial.md#cycle-v3rebuild_availability). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved on 2026-11-09; Stage 1 complete (qdb heartbeat pending); Stage 2 in progress.

**Cycle estimate: 0.65** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/rebuild-availability` from `origin/main` at `6ff90b5`; planning commit `331ea1d` holds approved Steps 1–3 and the debug log, moved into this folder with links repaired, plus the plan index entry and a checklist link.
- The Step 1–3 drafts were earlier pushed to `plans/rebuild_availability` at the session stop hook's request. That branch is superseded by this one.
- Whole-cycle **0.65** reaffirmed, 2026-11-09T18:50:25Z. Stage originals copied from Step 3: 0.04/0.08/0.08/0.12/0.14/0.10/0.07/0.10, non-additive. Claude, single exposed rater; copying is not a new rating.
- Checkpoint times in the Step 1–3 artifacts were estimated and run late by about 10 minutes; the ledger records corrected approximate times.
- Related feedback items, not claimed by this cycle: PF-20261021-B05 (QDB "temporarily busy", cause unconfirmed), PF-20261030-B16 (busy API returns HTML; API responses are out of scope here).

## Stage 1 - Host preflight

**Original estimate: 0.04**
**Retrospective: 0.04**

- Changes: none in code.
- Verification (operator output pasted 2026-11-09, about 19:07 UTC; host names left out on request):
  - zenmemes.com: read model ready, schema 53/53, no stale marker, no rebuild task; task executor fresh (cron ran within the last minute); automatic recovery idle. Read model 12M at the default path; 83G free. No `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` in the searched files. Passed.
  - qdb.us: read model 60M at the default path; 4.7T free. The flag search printed nothing. The `./v3 status` line did not run in the pasted session, so the executor heartbeat there is pending; Stage 8 rechecks it.
- Notes:
  - Disk-space risk resolved: a second copy fits easily on both hosts, so Stage 3 proceeds as planned.
  - Detached launch is off on both hosts, but cron runs the worker every minute on zenmemes.com. Queued rebuilds therefore start within about a minute there without any detached launch. Stage 6 should rely on the queue first and use the detached launch only where it is enabled; that is a refinement inside Stage 6's approved goal.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
