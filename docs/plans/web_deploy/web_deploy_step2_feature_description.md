> **Feature plan:** [Step 1](./web_deploy_step1_solution_assessment.md) · [Step 2](./web_deploy_step2_feature_description.md) · [Step 3](./web_deploy_step3_development_plan.md) · [Step 4](./web_deploy_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 1)
Effort study: `railspike-effort` / `v3/web_deploy` ([ledger](../effort_estimation_trial.md#cycle-v3web_deploy)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.50**

## Problem

Updating the running code means opening SSH and running `git pull`, which is awkward from a phone. The app has no way to update its own checkout.

## Direction

Step 1 Option A, approved 2026-11-02: a separate **Deploy** page in the Tools operator group.

Revision 2026-11-02 (maintainer): the first draft of this step placed the controls on System status (`/tools/codebase/`). The maintainer objected to System status checking GitHub every time it opens and chose the separate page as originally planned. System status stays unchanged. Opening the Deploy page does not contact GitHub either: it shows how far behind the server's last-fetched `origin/main` is, with when that was checked, and only **Check for updates**, Deploy or Deploy now fetch.

## User stories

- As a root-approved operator on my phone, I want to deploy the latest `main` from the site so that I don't need an SSH session for routine updates.
- As that operator, I want to choose between queuing the deploy for the cron worker and running it right away so that small releases are immediate and a stalled worker doesn't block me.
- As that operator, I want a clear reason when a deploy is refused or fails so that I know whether to retry, wait, or go to SSH.
- As an operator using SSH, I want the same deploy as one command so that both paths behave alike.

## Core requirements

- Only a session-authenticated root-approved operator sees the controls or can trigger a deploy; requests from other sites are rejected.
- A deploy only fast-forwards the app checkout to `origin/main`. It refuses, leaving the checkout untouched, when the tree has local changes, the branch is not `main`, history has diverged, or another deploy is running. Pull only: no docs publish, static build or other follow-up.
- Queued and instant modes run the same routine under one lock; a double click or a queued and an instant deploy at once cannot run twice.
- Every attempt records time, operator, mode, old and new commit, and outcome in a plain `Key: value` log that the page can show.
- The page shows the latest attempt's state (queued, running, done, refused, failed) and warns when the worker heartbeat is stale.
- Opening the page makes no network request; only Check for updates and the deploy actions fetch from GitHub.

## Delivery scope

- Work type: application change.
- Includes: a Deploy page in the Tools operator group with its nav icon in Word97, Chicago and Midnight, queued task type, instant path, shared routine, `./v3 deploy`, attempt log, tests, operator documentation (runbook section, CLI reference), concept inventory entries below, and this cycle's ledger bookkeeping.

## Completion boundary

- Normal entry: Tools, then Deploy, signed in as a root-approved operator.
- End-to-end outcome: after Deploy or Deploy now, the app checkout is at `origin/main` and the Deploy page shows the new commit and a done result.
- Recovery: any refusal or failure leaves the checkout as it was and shows why; the operator can retry, switch modes, or use `./v3 deploy` over SSH.
- Release condition: tests pass for both modes and refusal cases; one real deploy succeeds on zenmemes.com.

## Known user and environment

The maintainer, on an iPhone browser, against zenmemes.com (shared host; app at `~/v3`, PHP running as the account user).

- Failure to handle: PHP cannot reach GitHub, `git fetch` exceeds the request time limit, or the cron worker is stalled.
- Next action unaided: the page shows the reason and the other mode; SSH with `./v3 deploy` stays available.
- Verify or retry: the Deploy page shows the commit after a reload; retry is the same button.
- Evidence: a deploy run from the iPhone on zenmemes.com, both modes if the host allows. Local tests do not stand in for this.

## Risks

- **Host can't fetch from PHP** (network, credentials, time limit). High impact. Validate early in Step 4 by running the routine on the host; mitigation is the queued mode (runs from cron, outside web limits) and SSH fallback.
- **Pulled code changes the running app mid-request.** Medium. The instant path finishes the response from already-loaded code; verify with a deploy that changes the Deploy page itself.
- **Operator check weaker than intended.** High. Use the session-authenticated check, not the identity-hint cookie path; test signed-out, approved non-operator and cross-site requests.
- **Checkout owned by a different user than PHP.** Medium. The routine refuses with the permission error; documented in the runbook.

## Shared component inventory

| Existing surface | Use |
| --- | --- |
| System status page (`CodebaseStateController`, `/tools/codebase/`): app commit, branch, working tree, worker heartbeat | Used unchanged; the Deploy page reuses its local, read-only git reads rather than duplicating them |
| Tools registry (`ToolsPageSupport`), operator tool pages and tools subnavigation | Extend with one operator entry |
| Theme nav icons (`public/assets/theme-nav-icons.css`) | Extend with the Deploy icon for Word97, Chicago, Midnight |
| `OperatorStatusCollector` worker heartbeat | Reuse for the stale-worker warning |
| `RouteServices::securityReviewAllowed()` session operator check | Reuse as the guard |
| Same-origin JSON request guard (Railspike workflow actions) | Reuse pattern for the POST |
| Task queue store and worker (`SqliteTaskQueueStore`, `TaskQueueWorker`, `scripts/task_queue.php`) | Extend with one task type |
| `CheckoutManager` fast-forward ancestry and busy checks | Reuse its approach; the app checkout is a different target, so a small routine shares the checks rather than the class |

## Concepts touched

- [Deployment configuration](../../concepts/deployment_configuration.md): used unchanged.
- [Tools launcher](../../concepts/tools_launcher.md): changed (new operator destination).
- [Theme navigation icons](../../concepts/theme_navigation_icons.md): changed (Deploy icon in three themes).
- Application update: proposed new inventory entry.
- Internal task queue: proposed new inventory entry for the existing concept, describing the added type.

## User flow

1. Operator opens Tools, then Deploy.
2. Page shows the current commit, how far behind the last-fetched `origin/main` it is and when that was checked, worker health, and the last deploy. Check for updates refreshes the comparison.
3. Operator taps Deploy (queued) or Deploy now.
4. Page shows queued or running, then done with the new commit, or the refusal/failure reason.
5. Operator runs any follow-up (`docs publish`, static build) themselves if the release needs it.

## Success criteria

- A deploy from the phone on zenmemes.com moves the app to `origin/main` with no SSH.
- Each refusal case leaves `HEAD` and the working tree unchanged, verified in tests.
- Signed-out, approved non-operator and cross-site requests cannot trigger a deploy, verified in tests.
- Concurrent attempts produce one pull and one "already running" refusal.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Drop the thread-level `Retracted:` header from `get_thread` | Separate, unrelated | Tabled by maintainer | Pending |
| Stop `/api/read_model_status` showing the server's full home path | Separate small privacy fix | Not requested | Pending |
| Don't check GitHub every time a status page opens; use a separate Deploy page | In scope: restores Step 1's separate page, adds the no-fetch-on-open requirement | Requested by maintainer after Step 2 approval; renewed Step 2 approval requested | Applied in this revision |

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-11-02T21:52Z | 0.45 | v1.1 / R1 / P2, original baseline `338790430a6a2bdcdb524d017de2a10993032f49` | Claude (claude-opus-5-5), single exposed rater; additionally inspected `CodebaseStateController` and `OperatorStatusCollector` | Placing controls on System status removes a new page, nav entry and three theme icons; outcome, modes and recovery unchanged |

| Step 2 revision | 2026-11-02T21:58Z | 0.50 | v1.2 / R1 / P2, same baseline | Same | Separate Deploy page restores the page, nav entry and three icons; no-fetch-on-open adds a cached comparison and a Check action |

Intake and Step 1 snapshots (0.50) preserved in Step 1; the 0.45 Step 2 snapshot above is retained. Frozen R1 hashes verified 2026-11-02. No implementation, measured labor or accepted outcome; planning remains uncommitted.
