# Forte Activity parity — Railspike debug log

## 2026-10-16 — Step 2 completed handoff

Debug mode: enabled from the project marker for this new workflow. Step 1 skipped because the proposal extends the existing shared activity system.

### Necessary/useful instructions

- Source: `step2_feature_description.md`, Shared component inventory. Comparing the existing ActivityService, templates, and browser reader revealed missing security row membership and established reusable evidence partials. Retain this requirement.
- Source: `step2_feature_description.md`, Risks and Completion boundary. Client-side security transitions and lazy loading need verification beyond initial rendering. Retain explicit normal-flow and recovery coverage.

### Redundant/unnecessary instructions

No supported findings.

### Uncertain assessments

- Source: `step2_feature_description.md`, Problem and desired outcomes. “In line” could mean layout replacement or functional parity. The draft assumes retaining Forte's layout, and a clarification question is pending. Revise the scope if the user chooses layout replacement; this draft does not authorize implementation.

## 2026-10-16 — Step 3 completed handoff

Debug mode: enabled, inherited from the approved Step 2 artifact. The user approved Step 2 without changing scope, establishing the proposed split-pane parity boundary.

### Necessary/useful instructions

- Source: `step3_development_plan_do.md`, Completion Contract and Key Risks. Inspecting the browser's ID-only detail cache identified view-transition and late-response risks; Stage 1 resolves these before presentation changes. Retain early risk validation and explicit stage dependencies.
- Source: `step3_development_plan_do.md`, normal-flow vertical slices. Stage 1 covers category-to-detail behavior across server/client rendering and recovery, rather than splitting those layers into independently incomplete stages. Retain this requirement.
- Source: `step3_development_plan_after.md`, approval gating. The plan remains uncommitted and implementation has not started, keeping the scope reviewable. Retain the boundary.

### Redundant/unnecessary instructions

- Source: Step 3 Before/Do/After and `RAILSPIKE.md`, repeated debug-state recovery and approval/commit reminders. The same enabled state and uncommitted boundary are restated across short sequential phase files. Consolidate shared reminders into one step-entry checklist while preserving the final approval gate.

### Uncertain assessments

- Source: `step3_development_plan_do.md`, stage sizing. Stage 1's asynchronous view-state coverage may expose more edge cases than static inspection reveals. Reassess its estimate during implementation; split verification-driven work within approved scope if necessary and record the reason.

## 2026-10-16 21:09:23 UTC — Step 4 completed handoff

Debug mode: enabled, inherited from the approved plan and implementation summary. All three stages are committed: `0d20b349`, `6897f248`, `9394254f`, following planning commit `efa30a6c`.

### Necessary/useful instructions

- Source: `step4_implementation_do.md`, shared component/contract rule. Reusing ActivityService, author/time helpers, and evidence partials kept the data model shared; extracting the policy notice made default and Forte wording identical. Retain this rule.
- Source: `step4_implementation_do.md`, Stage Boundary Protocol. Separate stage commits with verification and summary updates expose the security lifecycle fix independently of presentation and final HTTP checks. Retain stage-scoped evidence and commits.
- Source: `step4_implementation_after.md`, Contract and dependency verification. Real HTTP/signing/authentication, deterministic delayed-response tests, and phone screenshots revealed different concerns: pagination fixture sizing, abandoned sort responses, and selected-folder visibility. Retain end-to-end recovery and environment-appropriate checks.

### Redundant/unnecessary instructions

- Source: `RAILSPIKE.md`, Step 4 commit cadence, and `step4_implementation_do.md`, Execution Rules/Stage Boundary Protocol. These sections repeat the same stage-summary/commit gate. Consolidate the normative rule and link it from the phase checklist to reduce repeated reading without weakening the gate.

### Inapplicable instructions

- Source: Step 4 documentation-only rules. This workflow explicitly authorizes application and browser changes, so documentation-only file restrictions and runtime-check exemptions were inapplicable.
- Source: `step4_implementation_after.md`, deployment/dependency verification. Production deployment and external services are outside the approved scope; isolated HTTP and static/rebuild rehearsal verified local behavior instead. No schema migration was required.

### Uncertain assessments

- Source: `step3_development_plan_do.md`, approximately 50 lines or one hour per stage. Stage 1 exceeded 50 lines because the deterministic browser test and bounded loader accompany a single complete navigation/recovery slice. It remained a single scoped stage; line count alone would split closely coupled behavior and verification. Clarify whether test lines are intended to count toward this heuristic.
