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

## Stage 2 - Queued mode with deploy priority

**Original estimate: 0.08**
**Revised (Step 3, 2026-11-02): 0.10** for deploy priority
**Retrospective: 0.10**

- Changes:
  - `SqliteTaskQueueStore::APP_UPDATE` (`app_update`), allowed type; `claimNext()` orders `app_update` first, then the existing `requested_at, id` order. No schema change.
  - `TaskQueueWorker` gains an optional `runAppUpdate` callable and a `runAppUpdateTask()` arm: deployed or up to date completes the task; `busy` (an instant deploy holds the lock) is retried on a later run; any other refusal or failure is final with the refusal reason as the failure code.
  - `AppUpdate::queue($store, $actor)` enqueues with deduplication key `origin/main`, so repeated taps coalesce; it records the requester in `state/app_update_queued.txt` and appends a `queued` entry to the attempt log, which the page will show. `AppUpdate::runQueued()` deploys as that requester.
  - `scripts/task_queue.php` wires `runAppUpdate` to `runQueued()`.
- Verification:
  - `php tests/run.php AppUpdateTest TaskQueueWorkerTest TaskQueueStoreTest TaskQueueCommandTest DetachedTaskQueueLauncherTest`: 57/57 passed. New: coalescing and requester attribution; deploy claimed before an earlier rebuild and agent reply (rebuild not run); fast-score then rebuild keep FIFO order; busy retry then success; refused deploy fails with `local_changes`.
  - `php scripts/task_queue.php status` lists `type=app_update` tasks.
- Notes:
  - The queue has no payload column, so the requester travels in the sidecar file rather than the task row; this avoided a schema change.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Stage 3 - Deploy page

**Original estimate: 0.12**
**Retrospective: 0.12**

- Changes:
  - `src/ForumRewrite/Http/DeployController.php` and `templates/pages/deploy.php`: `/tools/deploy/` shows commit and subject, branch, tracked local changes (only when present), behind `origin/main` with last-checked time, task-worker heartbeat and the latest attempt; served `Cache-Control: no-store`. Reuses System status's `codebase-facts` table and the queue store's heartbeat and `latestByType()`; does not create the queue database if it is missing.
  - `ToolsPageSupport` registry entry `deploy` (operator group, after Operations); route and known-route allowlist in `Application`; Deploy icon (green arrow over tray) in `theme-nav-icons.css` for launcher and subnavigation, plus the subnavigation sizing list.
  - `tests/ToolsRegistryTest.php` tool count 15 to 16.
- Verification:
  - `php tests/run.php DeployPageTest ToolsRegistryTest WebServerRoutingTest ApiRouteCatalogTest AuthNavigationTest AppNavigationPlacementTest`: all passed. New `DeployPageTest`: opening the page creates no `FETCH_HEAD` and no queue database (proves no fetch); operator session renders the page and Tools lists Deploy; identity-hint-only viewer gets the read-only page with no controls.
  - Browser, local server on this checkout, 390 px wide: Deploy icon present on launcher button and subnavigation in Word97, Chicago and Midnight, absent in Light; no horizontal overflow in any. Screenshots reviewed.
- Notes:
  - Step 3 said non-operators "get the existing denial". Step 2's requirement is that only operators see the controls; the page itself shows the same commit facts System status already shows publicly, so it follows the Operations/Feature Flags pattern: read-only for everyone, controls server-decided for a session-authenticated operator. Controls are a security gate, so this is decided on the server rather than in the client before paint.
  - Form and page script land in Stage 4 with the POST handler.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Stage 4 - Check, Deploy and Deploy now actions

**Original estimate: 0.12**
**Retrospective: 0.15** (a session bug masked by the tests was found in the browser run and fixed)

- Changes:
  - `DeployController::submit()`: `POST /tools/deploy/` with `action` = `status`, `check`, `queue` or `now`. Requires a same-origin request (`Sec-Fetch-Site: same-origin` and/or a matching `Origin`; neither present is refused) and `RouteServices::securityReviewAllowed()` (session-authenticated root-approved operator). JSON for the page script (`X-Requested-With: ForumDeploy`); a plain form post gets the page with a notice. Instant deploys set `ignore_user_abort` and a time limit so a dropped phone connection doesn't stop the pull.
  - `Application`: POST route, and `/tools/deploy` added to the paths that start the viewer session (POSTs otherwise run without one).
  - `templates/pages/deploy.php`: three buttons for operators, a status line (server-rendered when a deploy is pending, so first paint is final), and a stalled-worker note for operators.
  - `public/assets/deploy_control.js`: JSON requests, polls `status` every 5 s while a deploy is queued or running and reloads when it finishes; buttons are disabled only during a request, so Deploy now stays available while a queued deploy waits.
  - `tool-details.css` mapped to the Deploy template (Stage 3 had left the table unstyled) with a wrapping `.deploy-actions` row.
- Verification:
  - `php tests/run.php DeployPageTest AppUpdateTest ToolsRegistryTest TaskQueueWorkerTest TaskQueueStoreTest`: 49/49 passed. New action tests, one request per process: refused for signed-out, identity-hint only, signed-in non-operator, cross-site, mismatched `Origin` and no origin evidence; operator `status` succeeds; unknown action refused; queue coalesces ("already queued"); an instant deploy of a commit that rewrites the Deploy template completes its JSON response with the new commit.
  - The session bug: the first version of the action tests passed while the real server refused the operator. With the session-start fix removed, the corrected tests now fail (2 of 2), and pass with it.
  - Browser, local server, 390 px, signed-in operator session: Check for updates refreshed the checked time; Deploy now showed the refusal (this checkout is on the feature branch); Deploy queued with Deploy now still enabled; one real `task_queue.php run --limit=1` claimed the deploy ahead of two other queued tasks, and the page reloaded with the result; with JavaScript off, the form post returned the page with the notice; no page errors; no overflow.
- Notes:
  - Non-operator approved members are refused by the same guard that checks operator status first; the fixture has no approved non-operator, so "signed-in non-operator" is the tested case.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Stage 5 - Operator documentation and concept inventory

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `docs/runbooks/production_deploy.md`: new "Deploy from the Site" section (the three actions, pull only, refusal reasons and recovery, attempt log, checkout and fetch requirements, first release by ordinary `git pull`, OPcache note); the task-queue section now lists deploys and their priority.
  - `docs/reference/v3_cli.md`: "Update the application checkout" (`./v3 deploy`, options, output, exit codes).
  - Concepts: new [Application update](../../concepts/application_update.md) and [Internal task queue](../../concepts/internal_task_queue.md); [Tools launcher](../../concepts/tools_launcher.md) count 15 to 16; [Theme navigation icons](../../concepts/theme_navigation_icons.md) notes the Deploy glyph.
- Verification:
  - Script check of every relative link and heading anchor in the six changed docs: 0 problems.
  - Documented commands run as written: `./v3 deploy --help`, `--status` (exit 0), invalid `--timeout=0` (exit 2).
  - `git diff --check`: clean.
- Notes: concept entries describe delivered behavior and their source paths resolve.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Stage 6 - Live check on zenmemes.com

**Original estimate: 0.05**
**Retrospective: pending**

- Status: **pending**, waiting for the maintainer to merge, install with one SSH `git pull`, then run the checks from an iPhone.
- Pre-merge verification (full suite on `cdfa00dc`, stages 1-4 code): 1822 run, 1807 passed, 15 failed. All 15 also fail on unmodified `main` (`33879043`): 4 known (ApprovalSeedCommandTest unreadable-artifact, 3 LocalAppSmokeTest), 8 FrmClientTest, ReadModelBuilderTimingTest, ReactionRecordFormatParityTest, TerminalOperatorUiCommandTest. No new failures.
- Planned live checks: Check for updates (proves PHP can fetch from GitHub); Deploy now with a small pending commit; Deploy (queued) and confirm cron runs it; if the worker is stale, confirm the warning and that Deploy now still works.
