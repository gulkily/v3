> **Feature plan:** [Step 1](./railspike_feature_gating_step1_solution_assessment.md) · [Step 2](./railspike_feature_gating_step2_feature_description.md) · [Step 3](./railspike_feature_gating_step3_development_plan.md) · [Step 4](./railspike_feature_gating_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/railspike_feature_gating — [ledger](../effort_estimation_trial.md#cycle-v3railspike_feature_gating), standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.50
Status: Step 1 v2/A approved 2026-10-29; planning only; no implementation approval

## Original Query

“Agree, please write step 1.”

## Understood Intent

Add a feature flag for the browser Railspike development workflow and correct its polling in one cycle, with separate implementation stages. Private-message synchronization traffic is separate work. This follows the user's production request observations and agreement with that combined scope.

## Problem

Ordinary thread/post browsing loads an unflagged operator workflow that checks every five seconds even without active work, and continues scheduling requests after authorization fails.

Evidence: [browser lifecycle](../../../public/assets/railspike_workflow.js), [page loading](../../../src/ForumRewrite/Http/ThreadAndPostPageController.php), [API authorization](../../../src/ForumRewrite/Http/RailspikeWorkflowController.php). Production deployment parity has not been verified.

## Option A — Feature flag with polling on open Railspike threads

**Estimate: 0.50**

Add a site-controlled flag through the existing feature system, and poll only while an authorized operator has a Railspike thread open in a visible tab. Interpret Railspike thread as a thread identified by the existing #railspike tag/label eligibility, including a direct post view in that context. Polling may continue while work awaits input or is complete; it need not depend on queued/running state. Ordinary threads and listings do not make workflow requests. Stop on navigation away, hidden tabs, disablement or lost authorization; retain explicit recovery.

- Benefits: Quiet ordinary browsing; automatic assessment results; one coherent enabled/disabled contract across UI, API and worker execution.
- Costs: An open Railspike thread still generates idle requests; stale eligibility, authorization recovery and disable/re-enable behavior need coordinated verification.
- Driver/uncertainty: Existing-worker disable races and static-page client gating account for most complexity.

## Option B — Feature flag with manual status refresh

**Estimate: 0.45**

Use the same feature gate, remove recurring polling entirely, and fetch status only on deliberate workflow entry, actions or explicit refresh.

- Benefits: Simplest request lifecycle; no idle timer traffic, including while assessments run.
- Costs: Operators must refresh to discover completed assessments; less convenient for the phone workflow.
- Driver/uncertainty: Flag enforcement and recovery remain necessary; removing automatic updates reduces browser-state verification.

## Recommendation

Choose A as one releasable slice: an operator can enable the feature, start an assessment, receive its result, and disable/re-enable it without losing records. Separate flag enforcement and polling correction into reviewable stages, with shared end-to-end acceptance. Propose disabled by default; approve that rollout choice with this assessment. Preserve stored workflows and existing API paths, with an actionable disabled response. Disabling must prevent new assessment execution; define already-running work and safe resumption in Step 2 before implementation.

Verification must cover zero workflow traffic when disabled, zero workflow traffic on ordinary threads, continued polling on an open eligible thread regardless of workflow state, authorization failure, explicit recovery, hidden/visible tabs, normal thread/post entry, and phone/desktop first paint without layout shifts. Use the existing flag controls; no new navigation, dependencies or planned schema changes. Related [Activity cleanup](../railspike_workflow_visibility/railspike_workflow_visibility_step4_implementation_summary.md) remains distinct; broader workflow-reading redesign is excluded.

## Revision and request disposition

Request: “I think polling is OK, but only if there is a railspike thread open?”
Disposition: In-scope Step 1 revision, v2/A; replace active-work-only polling with open-Railspike-thread polling. The default-off flag remains a proposal. No step approval or implementation authorization.

## Study snapshots

Scope: v1/A; baseline cdb7a4e75a8b0e9065140656ec79dba5cf12e229; reference R1; protocol P2.
Intake: 2026-10-29T18:12:07Z; 0.60; Codex, single exposed rater. Earlier source inspection in this conversation is disclosed; a pristine pre-inspection checkpoint is unavailable, not reconstructed.
Step 1 handoff: 2026-10-29 UTC; 0.60 reaffirmed; same scope and rater. All three host-local R1 hashes verified. Option estimates cover complete alternative cycles including planning, verification, documentation and recovery; not additive, measured labor or approval. Maintainer acceptance pending; no accepted outcome increment.

*— Codex · 2026-10-29 UTC*

Revision checkpoint: 2026-10-29T18:14:46Z; scope v2/A; projected total 0.50, replacing v1/A 0.60 for the revised recommendation while preserving that original above. Same baseline/R1/P2 and exposed Codex rater; reference verification carried forward. The user-selected polling boundary reduces workflow-state coordination; flag and recovery obligations remain. Option B remains 0.45. No measured labor or accepted outcome.

*— Revised by Codex · 2026-10-29 UTC*

Approval: User replied “Approved Step 1” after the v2 revision, approving Option A including the default-off flag. Earlier proposal and estimate snapshots above remain historical.
