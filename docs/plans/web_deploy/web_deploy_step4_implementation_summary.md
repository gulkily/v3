> **Feature plan:** [Step 1](./web_deploy_step1_solution_assessment.md) · [Step 2](./web_deploy_step2_feature_description.md) · [Step 3](./web_deploy_step3_development_plan.md) · [Step 4](./web_deploy_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)
Effort study: `railspike-effort` / `v3/web_deploy` ([ledger](../effort_estimation_trial.md#cycle-v3web_deploy)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.50**

Branch `feature/web-deploy` from `main` `338790430a6a2bdcdb524d017de2a10993032f49`; planning commit `d3236374`.

## Step 4 entry

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 4 entry | 2026-11-02T22:15Z | 0.50 | v1.3 / R1 / P2, original baseline | Claude (claude-opus-5-5), single exposed rater | Step 3 projection reaffirmed after planning commit; no new scope |

Stage originals copied from Step 3 (not new ratings): S1 0.15, S2 0.08 (revised 0.10), S3 0.12, S4 0.12, S5 0.08, S6 0.05.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Drop the thread-level `Retracted:` header from `get_thread` | Separate, unrelated | Tabled by maintainer | Pending |
| Stop `/api/read_model_status` showing the server's full home path | Separate small privacy fix | Not requested | Pending |

## Stage 1 - Shared update routine and `./v3 deploy`

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `src/ForumRewrite/Deploy/AppUpdate.php`: `inspect()` (local only), `check()` (fetch), `deploy($actor, $mode)` (lock, refusal checks, fetch, `merge --ff-only`), `latestAttempt()`, `isBusy()`. Lock at `state/app_update.lock`; attempts appended to `state/logs/app_update.log` as `Key: value` blocks, rotated to `.1` past 256 KB.
  - Refusals: `not_a_checkout`, `wrong_branch` (not `main`), `local_changes` (tracked files only; untracked files are left to Git's own overwrite protection), `busy`, `diverged`; failures: `fetch_failed`, `fetch_timeout`, `no_remote_branch`, `merge_failed`. Git stderr is reduced to one line with `user:pass@` removed.
  - `scripts/deploy.php` and `./v3 deploy [--check|--status] [--timeout=N]`.
- Verification:
  - `php tests/run.php AppUpdateTest`: 13/13 passed (fast-forward and log, up to date, inspect with unreachable remote, check behind count, local changes, untracked allowed, wrong branch, diverged, lock held, fetch failure, credential redaction, non-checkout, CLI help/usage error). Each refusal asserts `HEAD` and files unchanged.
  - `./v3 deploy --status` on this checkout: reports commit, branch, local changes, behind/ahead and last fetch time; exit 0.
- Notes:
  - Git process handling follows `CheckoutManager::git()` (scrubbed environment, no prompts, kill on timeout) rather than sharing the class, which is registry-bound.
  - Last-checked time is `FETCH_HEAD`'s mtime, so manual `git fetch` over SSH also counts.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*
