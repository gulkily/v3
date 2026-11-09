> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](../effort_estimation_trial.md#cycle-v3rebuild_availability). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved on 2026-11-09; Stage 1 waiting on host output from the maintainer.

**Cycle estimate: 0.65** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/rebuild-availability` from `origin/main` at `6ff90b5`; planning commit `331ea1d` holds approved Steps 1–3 and the debug log, moved into this folder with links repaired, plus the plan index entry and a checklist link.
- The Step 1–3 drafts were earlier pushed to `plans/rebuild_availability` at the session stop hook's request. That branch is superseded by this one.
- Whole-cycle **0.65** reaffirmed, 2026-11-09T18:50:25Z. Stage originals copied from Step 3: 0.04/0.08/0.08/0.12/0.14/0.10/0.07/0.10, non-additive. Claude, single exposed rater; copying is not a new rating.
- Checkpoint times in the Step 1–3 artifacts were estimated and run late by about 10 minutes; the ledger records corrected approximate times.
- Related feedback items, not claimed by this cycle: PF-20261021-B05 (QDB "temporarily busy", cause unconfirmed), PF-20261030-B16 (busy API returns HTML; API responses are out of scope here).

## Stage 1 - Host preflight

**Original estimate: 0.04**

- Changes: none in code.
- Verification: pending. The maintainer runs, on each host from `~/v3`:
  - `./v3 status` (read-model status, schema, executor heartbeat, automatic recovery and database path)
  - `du -h` on the database path that `./v3 status` reports (default `state/cache/post_index.sqlite3`) and `df -h` on its directory
  - a search of the web server environment (for example `public/.htaccess`) for `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED`, since the launcher reads it from the environment, not from private config
- Notes: Stage 3 needs room for a second copy of the read model on each host. If qdb lacks it, the plan falls back to fail fast only on that host, after asking the maintainer.
- Estimate details: original scope as in Step 3; no revision; retrospective pending.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
