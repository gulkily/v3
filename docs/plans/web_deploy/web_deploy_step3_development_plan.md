> **Feature plan:** [Step 1](./web_deploy_step1_solution_assessment.md) · [Step 2](./web_deploy_step2_feature_description.md) · [Step 3](./web_deploy_step3_development_plan.md) · [Step 4](./web_deploy_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 2)
Effort study: `railspike-effort` / `v3/web_deploy` ([ledger](../effort_estimation_trial.md#cycle-v3web_deploy)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.50**

## Completion Contract

- Normal entry: Tools, then Deploy, as a session-authenticated root-approved operator; `./v3 deploy` over SSH.
- End-to-end outcome: Deploy (queued) or Deploy now fast-forwards the app checkout to `origin/main`; the page shows the new commit and a done result.
- Required recovery: every refusal or failure leaves `HEAD` and the working tree unchanged and names the reason; the other mode and SSH remain available.
- Deployment/external verification: the feature reaches zenmemes.com by one manual SSH pull (it cannot deploy itself before it exists); then Stage 6 runs both modes from the iPhone.
- Release condition: Stages 1–5 checks pass locally; Stage 6 evidence recorded from zenmemes.com.

## Key Risks

- **High risk:** PHP on the host cannot fetch from GitHub, or exceeds the request time limit. Validate in Stage 6 first, with Check for updates before any deploy; mitigation is queued mode (cron, outside web limits) and SSH.
- **High risk:** guard weaker than intended. Stage 4 tests signed-out, approved non-operator, identity-hint-only and cross-site requests.
- Pulled code replaces files during an instant request. Stage 4 deploys a commit that changes the Deploy page itself in a test remote; the response must still complete.
- Checkout not writable by the PHP user. The routine refuses with git's error; runbook covers it.

## Stage 1

**Estimate: 0.15**

- Goal: one shared app-update routine, usable from CLI, queue and web.
- Dependencies: none.
- Expected changes:
  - New `ForumRewrite\Deploy\AppUpdate` with `inspect(): array` (local only: commit, branch, clean, behind/ahead vs cached `origin/main`, last fetch time), `check(): array` (fetch, then inspect) and `deploy(string $actor, string $mode): array` (lock, fetch, refuse or `merge --ff-only`, record).
  - Single-flight lock file under `state/`; attempt log `state/logs/app_update.log` as `Key: value` blocks with `readLatest(): ?array`.
  - `./v3 deploy` (deploy) and `./v3 deploy --check`.
- Verification approach: tests against a temporary bare remote and clone: success; dirty tree; non-`main` branch; diverged history; lock held; unreachable remote; already up to date. Each refusal asserts `HEAD` and status unchanged.
- Estimate details: driver: git process handling and refusal matrix; uncertainty: low.
- Risks or open questions:
  - Impact: a half-applied update. Early warning: refusal tests. Mitigation: only `--ff-only`, never reset or merge commits.
- Canonical components/API contracts touched: reuses the git-process pattern in `CheckoutManager` and read-only reads in `CodebaseStateController`; `DeploymentSettings` for paths.

## Stage 2

**Estimate: 0.08** (original) · **Revised 2026-11-02: 0.10**

- Goal: queued mode, with deploys claimed ahead of other queued work.
- Dependencies: Stage 1.
- Expected changes:
  - Task type `APP_UPDATE` in `SqliteTaskQueueStore`; worker arm calling `AppUpdate::deploy($actor, 'queued')`; actor carried in task payload; dedup so repeated clicks coalesce; `task_queue.php status` lists it.
  - Priority: `claimNext()` orders queued `APP_UPDATE` tasks before all others, then keeps the existing `requested_at, id` order. No schema change. A task already running is not interrupted.
- Verification approach: enqueue twice, run the worker once, assert one deploy and one coalesced task; queue a rebuild and an agent reply before a deploy, run the worker once, assert the deploy ran first; assert other task types keep their relative FIFO order; failure is reported through existing task status.
- Estimate details: driver: payload, dedup and claim ordering shared by every task type; uncertainty: low.
- Risks or open questions:
  - Impact: reordering affects every queue consumer. Early warning: existing task-queue tests plus the FIFO assertion. Mitigation: priority applies only to `APP_UPDATE`.
- Canonical components/API contracts touched: task queue store and `claimNext()`, `TaskQueueWorker::runClaimed`, CLI allowlist.

*— Revised by Claude · 2026-11-02 UTC · Model: claude-opus-5-5* (maintainer asked to prioritize deploys)

## Stage 3

**Estimate: 0.12**

- Goal: Deploy page visible only to operators, with no network on open.
- Dependencies: Stage 1.
- Expected changes: registry entry `deploy` (operator group) in `ToolsPageSupport`; route and known-route allowlist; controller method and `templates/pages/deploy.php` showing commit, behind count with last-checked time, worker heartbeat (stale warning), latest attempt; nav icon in Word97, Chicago, Midnight.
- Verification approach: operator sees the page; signed-out and non-operator get the existing denial; a test with an unreachable remote proves opening the page does not fetch; screenshots in the three icon themes plus one text theme at phone width.
- Estimate details: driver: page plus three-theme icon check; uncertainty: low.
- Risks or open questions:
  - Impact: page leaks state to non-operators. Mitigation: session operator check, tested.
- Canonical components/API contracts touched: `RouteServices::securityReviewAllowed()`, `OperatorStatusCollector` heartbeat, Tools subnavigation, `theme-nav-icons.css`.

## Stage 4

**Estimate: 0.12**

- Goal: Check for updates, Deploy and Deploy now actions.
- Dependencies: Stages 2 and 3.
- Expected changes: one POST endpoint (`action`: check, queue, now) behind the session operator check and the same-origin JSON guard; page script updates state and polls the latest attempt while queued or running; works without the script by plain form post and reload.
- Verification approach: tests for signed-out, approved non-operator, identity-hint-only and cross-site rejection; instant deploy of a commit that changes the Deploy page completes its response; concurrent now + queued yields one deploy and one "already running"; browser run of all three actions at phone width.
- Estimate details: driver: guard and two-mode state display; uncertainty: medium (time limits on instant path).
- Risks or open questions:
  - Impact: unauthorized deploy. Early warning: rejection tests. Mitigation: guard reused, not re-implemented.
- Canonical components/API contracts touched: Railspike same-origin JSON guard pattern, Stage 1 routine, Stage 2 enqueue.

## Stage 5

**Estimate: 0.08**

- Goal: operator documentation and concept inventory.
- Dependencies: Stage 4.
- Expected changes: runbook section (modes, refusals, writable checkout, first manual pull); `v3_cli.md` entry for `./v3 deploy`; new concept entries "Application update" and "Internal task queue"; update Tools launcher and theme icon entries.
- Verification approach: links resolve; documented commands run as written.
- Estimate details: driver: runbook clarity; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/runbooks/production_deploy.md`, `docs/reference/v3_cli.md`, `docs/concepts/`.

## Stage 6

**Estimate: 0.05**

- Goal: replay the known environment on zenmemes.com from the iPhone.
- Dependencies: Stages 1–5 merged; one manual SSH pull to install the feature.
- Expected changes: none to code; evidence recorded in the Step 4 summary.
- Verification approach, with the maintainer:
  - Check for updates from the iPhone (proves PHP can fetch; if not, record and use queued/SSH).
  - Deploy now with a small pending commit; confirm new commit shown.
  - Deploy (queued); confirm cron runs it within a few minutes.
  - If the worker is stale at the time, confirm the warning and that Deploy now still works.
- Estimate details: driver: depends on maintainer availability; uncertainty: medium (host behavior).
- Risks or open questions:
  - Impact: feature unusable on this host. Mitigation: findings feed a follow-up; SSH path unchanged.
- Canonical components/API contracts touched: none new.

## Concepts touched

- [Deployment configuration](../../concepts/deployment_configuration.md): used unchanged.
- [Tools launcher](../../concepts/tools_launcher.md): changed (Stage 3, entry updated Stage 5).
- [Theme navigation icons](../../concepts/theme_navigation_icons.md): changed (Stage 3, entry updated Stage 5).
- Application update: proposed; created Stage 5.
- Internal task queue: proposed entry for existing concept; created Stage 5.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-11-02T22:05Z | 0.50 | v1.2 / R1 / P2, original baseline `338790430a6a2bdcdb524d017de2a10993032f49` | Claude (claude-opus-5-5), single exposed rater; additionally inspected task-queue, worker, guard and CLI extension points | Reaffirmed; plan adds no scope beyond Step 2 v1.2 |
| Step 3 revision | 2026-11-02T22:10Z | 0.50 | v1.3 / R1 / P2, same baseline | Same; inspected `claimNext()` | Deploy priority in the queue (maintainer request) is a small ordering change; total reaffirmed |

Stage-work originals S1 0.15, S2 0.08 (revised 0.10 for deploy priority), S3 0.12, S4 0.12, S5 0.08, S6 0.05 are independent annotations, not summed into the cycle total. Frozen R1 hashes verified 2026-11-02. Six stages planned, none implemented; await Approved Step 3. Planning remains uncommitted.

*— Claude · 2026-11-02 UTC · Model: claude-opus-5-5*
