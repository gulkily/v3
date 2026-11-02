> **Feature plan:** [Step 1](./web_deploy_step1_solution_assessment.md) · [Step 2](./web_deploy_step2_feature_description.md) · [Step 3](./web_deploy_step3_development_plan.md) · [Step 4](./web_deploy_step4_implementation_summary.md)

Debug mode: enabled (`.railspike_debug_on` present at the project root; new workflow)
Effort study: `railspike-effort` / `v3/web_deploy` ([ledger](../effort_estimation_trial.md#cycle-v3web_deploy)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.50**

## Original Query

Great. I want to add a new patch that makes it possible to deploy via web interface if I have an approved account. Most of the time I just git pull.

Clarifications given in the same session, 2026-11-02:

- Who can deploy: root-approved operators.
- How the pull runs: "Give a choice, prefer cron, but have the option for instant for small releases and if the cron is not working."
- What a deploy does: pull only.

## Understood Intent

A root-approved operator can update the server's application checkout from a page in the site, without SSH (the maintainer is often on a phone). The normal path queues the pull for the existing cron worker; an instant path runs it in the request when the release is small or cron is down. Follow-up commands (`docs publish`, `build-static`) stay manual.

## Problem

Updating the running code requires an SSH session to run `git pull`, which is awkward from a phone; nothing in the app updates its own checkout today.

## Concepts touched

- [Deployment configuration](../../concepts/deployment_configuration.md): used unchanged (project root vs content repository root).
- [Tools launcher](../../concepts/tools_launcher.md): changed in all options (new operator destination).
- [Theme navigation icons](../../concepts/theme_navigation_icons.md): changed in all options (icon for the new destination in Word97, Chicago, Midnight).
- Application update (proposed, all options): a guarded fast-forward of the app checkout with a single-flight lock and a readable result.
- Internal task queue (proposed entry; exists in code without an inventory entry): changed in A and B (new task type).
- Operator audit (proposed): created in A as a local log; in B as signed records in the content repository.

## Option A: Operator Deploy page with queued and instant modes

**Estimate: 0.50**

A Tools page, visible only to a session-authenticated root-approved operator, shows the app's current commit and how far it is behind `origin`. "Deploy" enqueues an app-update task that the cron worker runs; "Deploy now" runs the same routine inside the request. The routine refuses unless the checkout is clean, on the expected branch and a fast-forward, holds one lock shared by both modes, and records old/new commit, actor and outcome. The page shows queued/running/done/failed, and warns when the cron worker's heartbeat is stale so the operator knows to use the instant path. The same routine is exposed as `./v3 deploy` for SSH use.

- Pros: covers both requested modes with one code path; reuses the task queue, its heartbeat and the existing operator check.
- Pros: refuses rather than merges, so a deploy never leaves the checkout conflicted.
- Cons: the web/PHP user must be able to write the checkout and reach GitHub; true on the current host (paths are under the account's home), but unverified until tried there.
- Cons: the audit is a local log, not a signed record.
- Driver: new task type, page, guard, lock and status reporting across queue and request. Uncertainty: medium, mainly host behavior of `git fetch` from PHP (time limits, network).

## Option B: Option A with a signed deploy request

**Estimate: 0.75**

Same page and routine, but each deploy is a browser-signed record (prepare/finalize, like feature-flag changes) committed to the content repository before the pull runs.

- Pros: tamper-evident audit tied to the operator's key; matches how feature-flag changes are recorded.
- Cons: every deploy adds a commit to forum content history; more moving parts (prepare token, signature verification, record spec).
- Cons: the instant path gets slower and has more failure points exactly when used as a fallback.
- Driver: new record format/spec plus signing flow. Uncertainty: medium.

## Option C: Pull-on-merge webhook

**Estimate: 0.45**

A GitHub webhook to a secret-protected endpoint triggers the same pull whenever `main` changes; no button.

- Pros: zero clicks; no phone needed at all.
- Cons: does not match the request (no account-gated choice, no instant vs queued control); every merge deploys immediately; adds a shared secret to manage.
- Driver: webhook verification and secret configuration. Uncertainty: low-medium.

## Recommendation

Option A. It is a releasable slice: entry is a new operator page under Tools; the outcome is the app checkout fast-forwarded to `origin/main` with the result shown; recovery is a clear refusal with the reason (dirty tree, diverged branch, lock held, fetch failed) and the unchanged checkout, plus the instant path when cron is stale. Verify with a temporary bare remote in tests for both modes and refusal cases, then one real deploy on zenmemes.com. Option B can follow if a signed audit trail is wanted later; C is not recommended.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Drop the thread-level `Retracted:` header from `get_thread` (raised earlier this session) | Separate: unrelated to deploys; maintainer tabled it | Tabled by maintainer | Pending |
| Stop `/api/read_model_status` from showing the server's full home path | Separate: small privacy fix noticed while finding the SSH username | Not requested | Pending |

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-11-02T21:38Z | 0.50 | v1 / R1 / P2, baseline `main` `338790430a6a2bdcdb524d017de2a10993032f49` | Claude (claude-opus-5-5), single exposed rater; surveyed permission checks, the task queue, `CheckoutManager` and Tools registration before rating. No pristine pre-inspection intake. | New task type, operator page, shared guarded routine, two execution modes and status |
| Step 1 handoff | 2026-11-02T21:38Z | 0.50 (Option A) | v1 / R1 / P2 | Same | Recorded together with intake; no change |

Option estimates (A 0.50, B 0.75, C 0.45) are complete-cycle alternatives, not summed. All three frozen R1 hashes verified 2026-11-02. No implementation, measured labor or accepted outcome; planning remains uncommitted.
