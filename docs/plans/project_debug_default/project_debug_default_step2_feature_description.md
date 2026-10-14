> **Feature plan:** [Step 1](./project_debug_default_step1_solution_assessment.md) · [Step 2](./project_debug_default_step2_feature_description.md) · [Step 3](./project_debug_default_step3_development_plan.md) · [Step 4](./project_debug_default_step4_implementation_summary.md)

Debug mode: enabled

# Project debug default

## Problem
New Railspike workflows default to disabled diagnostics, requiring repeated enable requests. Projects need a shared preference that survives across features and sessions while preserving workflow-specific decisions.

## User stories
- As a project maintainer, I want a shared debug default so that new workflows produce instruction diagnostics without repeated requests.
- As a workflow user, I want to override that default so that my workflow retains its intended mode across sessions.

## Core requirements
- Define one optional, discoverable project preference, owned by the consuming project even when Railspike is vendored or a submodule. Defer its exact format/location to Step 3.
- Resolve mode in order: latest explicit workflow request, existing workflow state, project default for a new workflow, then disabled when no preference exists.
- Snapshot the resolved mode in the existing artifact field; project-default changes affect future workflows only. Workflow overrides leave the shared preference unchanged; removing it restores the disabled fallback for future workflows.
- Surface invalid or ambiguous project preferences for clarification when needed to resolve mode; preserve existing recovery for missing/conflicting resumed state without silently resetting it.
- Preserve diagnostic content, logging, state inheritance, and approval rules. Exclude user-global defaults, nested-project inheritance, and tooling.

## Delivery scope
**Documentation-only**, for consuming-project maintainers and Railspike agents. Allowed implementation files: `RAILSPIKE.md`, `README.md`, `docs/workflow/*.md`, and `docs/failure-modes.md`. Document the preference and examples; adding an actual project setting is outside this slice. Application/runtime, tests, scripts, CI, deployment, and generated-artifact changes are not permitted.

Bookkeeping is limited to this feature's plans, summary, debug log, their eventual organization under `docs/plans/project_debug_default/`, and its `docs/plans/README.md` entry. Preserve historical approved content.

## Risks
- **Wrong project:** Reading the installed Railspike directory could select another default. Before Step 3, review standalone/submodule/vendor scenarios; require consuming-project ownership and clarification when ambiguous.
- **State regression:** A changed default could override ongoing work. Before Step 3, review precedence/resumption cases; retain snapshot semantics and existing recovery.
- **Conflicting guidance:** Existing “default off” wording could defeat opt-in. Before Step 3, inventory active references; identify all wording requiring alignment with the canonical contract.

## Shared component inventory
No UI/API surfaces apply. Extend `RAILSPIKE.md` as the canonical contract; align README onboarding, workflow debug-state/handoff references, and failure-guide recovery. Reuse artifact mode fields and the existing debug log; no separate state/report system.

## Simple user flow
1. Maintainer sets the documented project preference.
2. User starts a feature without a mode request; the agent resolves and records mode.
3. Enabled step completion saves diagnostics and links the log.
4. User resumes or overrides the workflow; later preference changes affect only new workflows.

## Completion boundary and success criteria
Deliver consistent instructions enabling maintainers to set, change, and remove the preference. Scenario review must cover enabled/disabled/absent preferences, explicit overrides, ongoing-workflow isolation, invalid/ambiguous settings, unresolved resumed state, and all three installation layouts, with no unresolved contract contradictions. Correct unclear guidance and broken references before handoff; record document-integrity, link, scenario, and allowed-scope evidence in Step 4. These checks establish the documented contract, not guaranteed agent adherence; runtime/deployment checks are inapplicable.
