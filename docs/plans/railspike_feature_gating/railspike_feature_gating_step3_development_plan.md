> **Feature plan:** [Step 1](./railspike_feature_gating_step1_solution_assessment.md) · [Step 2](./railspike_feature_gating_step2_feature_description.md) · [Step 3](./railspike_feature_gating_step3_development_plan.md) · [Step 4](./railspike_feature_gating_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/railspike_feature_gating — [ledger](../effort_estimation_trial.md#cycle-v3railspike_feature_gating), standing P2 enrollment
Extended estimates: enabled
Status: Step 3 approved 2026-10-29; implementation authorized

**Cycle estimate: 0.60**

## Completion Contract

From Feature Flags and normal thread/direct-post entry, an operator can enable the default-off workflow, start and review an assessment, disable it, and re-enable/reload without losing records or drafts. Poll only on visible eligible #railspike threads, independent of workflow state. Preserve signed authorization, shared static HTML and first-paint stability. Disabled/ordinary pages make no workflow requests; stale pages stop after learning of disablement or lost access. Already-started assessments may finish; queued work resumes safely. Deliver local network/rendered/recovery evidence and production replay instructions; deployment and actual production verification remain separately authorized and pending, not implied by local completion. No dependencies or planned schema changes; private messaging and legacy handoffs excluded.

## Key Risks

- **High risk:** A disabled task returned as successful becomes an interrupted workflow during reconciliation. Validate queue deferral and reconciliation first; preserve queued state, retry budget and unrelated queue progress.
- **High risk:** Late availability/status rendering can flash controls or erase answers. Validate baked pages, local viewer hints and existing draft handling before client changes; use stable collapsed controls and preserve drafts across refresh/failure. Unresolved first-paint behavior blocks dependent work.
- Stale eligibility can poll ordinary threads or strand history. Validate thread labels, post tags, direct links and tag removal; stop when eligibility is lost, retain records and support re-tag/reload recovery.

## Stage 1 — Flag and API enforcement

**Estimate: 0.12**

- Goal/Dependencies: Make the feature explicitly controllable; starts after implementation approval and planning commit.
- Expected changes: Register default-off site flag, canonical group/order, environment/private-config support where applicable, and actionable no-store disabled responses for workflow reads/actions.
- Components: FeatureFlagRegistry/evaluator, existing Feature Flags UI, PrivateConfigSchema if needed, RailspikeWorkflowController and API documentation.
- Verification: Default/override/reset/locked precedence; disabled GET and both signed-action phases; enabled operator-only behavior and registry completeness.
- Risk/validation/mitigation: A prepared action could bypass disablement; check final submission too. Driver: shared flag integration; uncertainty: configuration wiring.

## Stage 2 — Worker suspension and recovery

**Estimate: 0.14**

- Goal/Dependencies: Preserve queued work while disabled; depends on S1 availability contract.
- Expected changes: Evaluate current flag before assessment admission; defer without consuming attempts or claiming success; preserve completion for admitted work and reconciliation after re-enable.
- Components: AssessmentTask, WorkflowStore, TaskQueueWorker, existing requeueClaimed contract and scripts/task_queue.php wiring; no schema change.
- Verification: Disabled queued task, disable at admission, in-flight completion, repeated disabled worker runs, re-enable exactly-once execution and other task progress.
- Risk/validation/mitigation: Deferral loops or false failures; exercise reconciliation before client work and bound each worker run. Driver: lifecycle races; uncertainty: long-lived flag freshness.

## Stage 3 — Eligible-page and first-paint gating

**Estimate: 0.15**

- Goal/Dependencies: Keep ordinary browsing silent; depends on S1, with S2 recovery verified.
- Expected changes: Reuse eligibility for thread/direct-post mounts; gate initial client work on feature/viewer context; preserve static shared markup and stable initial controls, including baked-page regeneration/invalidation.
- Components: WorkflowService eligibility, ThreadAndPostPageController, workflow partial/client, TemplateRenderer/layout and existing availability/static-artifact paths only as required.
- Verification: Flag on/off × guest/member/operator × eligible/ordinary thread and direct-post views; listings remain silent; compare shared HTML and phone/desktop first paint with JavaScript on/off.
- Risk/validation/mitigation: Cached markup or late auth reveals controls; resolve presentation before wiring polling, fail closed on unknown local state and retain explicit sign-in/reload recovery. Driver: static/client integration; uncertainty: existing viewer hints.

## Stage 4 — Polling lifecycle and draft preservation

**Estimate: 0.14**

- Goal/Dependencies: Poll only an open visible eligible thread; depends on S3.
- Expected changes: Single non-overlapping scheduler; pause/resume on visibility, stop on navigation/disablement/authorization loss, bounded transient failures and explicit refresh recovery; preserve typed answers across state updates and interrupted actions.
- Components: railspike_workflow.js and existing signing/auth integration; reuse API paths and signed action contract.
- Verification: Existing Railspike auth/browser suites plus slow responses, repeated visibility events, waiting/completed workflows, tag removal, failed authorization, draft edits and reload recovery.
- Risk/validation/mitigation: Duplicate timers or refreshed panels discard input; delayed-response and state-transition checks block acceptance. Driver: browser lifecycle; uncertainty: existing draft persistence gaps.

## Stage 5 — Acceptance and handoff

**Estimate: 0.12**

- Goal/Dependencies: Demonstrate the approved browsing/recovery outcome; depends on S1–S4.
- Expected changes: Extend existing fixtures and browser coverage, update phone-development runbook/API docs, UI verification checklist, cycle summary/debug log and study ledger. Own shared acceptance/documentation here; earlier stages own focused checks.
- Components: RailspikeWorkflowTest, feature-flag tests, railspike_auth_navigation.mjs, railspike_workflow_browser.mjs and normal static/baked entry.
- Verification: Network traces over multiple intervals; enable/start/result/disable/re-enable with retained work; phone/desktop across affected themes; signed failure/recovery; relevant PHP/Node/browser regressions. Record production replay steps for the reported browsing issue and live status as pending until authorized and performed.
- Risk/validation/mitigation: Local success mistaken for prod success; separate evidence and limitations. Driver: rendered matrix; uncertainty: production access and deploy timing, excluded from local completion claims.

## Execution and study checkpoint

After Approved Step 3, create a feature branch, organize this cycle under docs/plans/railspike_feature_gating/, repair links/index and commit approved planning under Railspike's narrow file gate; create Step 4 summary afterward. Commit each completed stage with its summary update. Keep ledger updates out of the planning commit unless permitted by that gate; include accumulated outcome bookkeeping at closeout. Stop/replan if required scope or schema changes exceed Step 2.

2026-10-29T18:59:40Z: scope v2/A clarified, original baseline cdb7a4e75a8b0e9065140656ec79dba5cf12e229, R1/P2, Codex single exposed rater; verified frozen references carried forward. Cycle projection revised from 0.50 to 0.60 for newly examined queue reconciliation, static gating and draft recovery, not added scope. Preserve Step 1/2 scores. S1–S5 are original stage-work estimates, not additive or measured labor. Five stages planned, none implemented; acceptance pending. Documents remain uncommitted.

*— Codex · 2026-10-29 UTC*
