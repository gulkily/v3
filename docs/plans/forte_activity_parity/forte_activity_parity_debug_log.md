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
