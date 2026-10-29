> **Feature plan:** [Step 1](./railspike_feature_gating_step1_solution_assessment.md) · [Step 2](./railspike_feature_gating_step2_feature_description.md) · [Step 3](./railspike_feature_gating_step3_development_plan.md) · [Step 4](./railspike_feature_gating_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/railspike_feature_gating — [ledger](../effort_estimation_trial.md#cycle-v3railspike_feature_gating)
Extended estimates: enabled
Status: Implementation started; Step 3 approved

**Cycle estimate: 0.60**

Entry: 2026-10-29 UTC; v2/A, R1/P2, original baseline cdb7a4e75a8b0e9065140656ec79dba5cf12e229; Codex single exposed rater. Step 3 projection reaffirmed; verified references carried forward. Planning commit ef47c56ae on feat/railspike-feature-gating. Original stage-work estimates S1–S5: 0.12/0.14/0.15/0.14/0.12, separately scoped in Step 3 and not additive. No measured labor. Production verification and maintainer acceptance pending.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Flag and API enforcement

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Added default-off FORUM_RAILSPIKE_ENABLED in the existing Agent replies group, site/environment precedence and private-config schema without a template default. Workflow GET and both POST phases refuse before storage access when disabled. Updated API catalog/runbook.
- Verification: `php tests/run.php RailspikeFeatureFlagTest FeatureFlagEvaluatorTest PrivateConfigSchemaTest PrivateConfigCommandTest`: 32/32 passed, including disabled routes, enabled anonymous rejection, defaults/overrides and config creation/refresh. `git diff --check` passed.
- Notes: Browser rendering and worker suspension belong to subsequent stages. Zero dependency/schema delta; original scope unchanged. R1/P2, Codex exposed rater, 2026-10-29 UTC; no measured labor.

*— Codex · 2026-10-29 UTC*

## Stage 2 - Worker suspension and recovery

**Original estimate: 0.14**
**Retrospective stage work: 0.14**

- Changes: Worker skips disabled Railspike claims, rechecks fresh application flag state at assessment admission, and requeues admission races without consuming retries. Disabled reconciliation preserves work; admitted results still finish. Existing task table and deferral contract reused.
- Verification: `php tests/run.php RailspikeWorkflowTest TaskQueueWorkerTest TaskQueueCommandTest`: 32/32 passed. Includes repeated disabled runs, claimed-before-disable race, unrelated task progress, in-flight finish and re-enable without duplicate execution. Syntax and diff checks passed.
- Notes: Stage 1 commit ba8d6e8d6. No schema/dependency change. Original scope unchanged; R1/P2, Codex exposed rater, 2026-10-29 UTC; no measured labor.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Eligible-page and first-paint gating

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Only eligible thread/post mounts render when enabled. Public operator fingerprints provide local presentation hints; API authorization remains authoritative. An inline pre-paint check reveals a static collapsed Development control only for a matching saved key; asynchronous private status stays inside it. Disabled pages omit the client script. Static/high-traffic HTML checks reject obsolete flag state and legacy workflow markup.
- Verification: Railspike flag tests 3/3; prior cache/backup regression run 8/8; PHP syntax/diff checks; Node auth-navigation test; real signed Railspike browser journey passed (start/answer/revise/approve, failure/retry, rebuild, direct post, Activity role/JS/viewport matrix and session recovery). Initial browser failure was a test opening the collapsed shell before a session-resumption navigation; corrected the test to await restored status before opening.
- Notes: Stage 2 commit dcc17b8e9. Detailed network timing/theme matrix follows in S5. Zero dependency/schema delta. R1/P2, Codex exposed rater, 2026-10-29 UTC; scope unchanged, no measured labor.

*— Codex · 2026-10-29 UTC*

## Stage 4 - Polling lifecycle and draft preservation

**Original estimate: 0.14**
**Retrospective stage work: 0.18**

- Changes: Replaced fixed interval with one non-overlapping five-second scheduler, including hidden-page pause, page navigation suspension, identity-change stop, terminal authorization/disabled stop and three-failure transient cutoff. Eligible threads poll regardless of workflow state. Session-scoped per-identity/post/revision drafts survive reload and state changes; older drafts remain copyable/discardable. Successful submission clears only the exact submitted draft.
- Verification: `node tests/browser/railspike_polling.mjs` passed real-browser controlled-clock checks for idle/completed polling, coalescing, visibility, revision changes, retained drafts/reload, terminal denial, bounded failures/manual recovery and removed eligibility. Auth-navigation regression and real signed full workflow browser journey passed. `node --check` and diff checks passed.
- Notes: Stage 3 commit 817db341f. Draft recovery required more work than the original stage estimate; whole-cycle projection remains 0.60. No new dependencies/schema. R1/P2, Codex exposed rater, 2026-10-29 UTC; retrospective is scope judgment, not measured labor.

*— Codex · 2026-10-29 UTC*
