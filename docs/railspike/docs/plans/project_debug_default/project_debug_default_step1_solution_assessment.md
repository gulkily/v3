> **Feature plan:** [Step 1](./project_debug_default_step1_solution_assessment.md) · [Step 2](./project_debug_default_step2_feature_description.md) · [Step 3](./project_debug_default_step3_development_plan.md) · [Step 4](./project_debug_default_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

As a railspike developer, I want to be able to enable debug mode by default for an entire project. Please write Step 1 of railspike, and use debug mode.

## Understood Intent

Allow a project to opt into Railspike's existing instruction diagnostics for new feature workflows without repeating an enable request. Use the existing per-workflow debug mode while assessing this feature.

## Problem Statement

Debug mode currently defaults to disabled for every new workflow, so developers cannot establish a shared project preference that survives across features and sessions.

## Option A — Dedicated project preference

Define one optional, shared project setting that Railspike reads when starting a new workflow, then record the resolved mode in the workflow's existing artifacts.

- **Pros:** Discoverable source of truth; consistent across assistants; keeps project preferences separate from vendored Railspike instructions.
- **Cons:** Adds a configuration surface and requires clear discovery, precedence, and invalid-setting behavior.

## Option B — Project instruction convention

Document a standard instruction that developers add to their project's existing assistant guidance to enable debug mode for all new Railspike workflows.

- **Pros:** Reuses familiar project guidance; avoids a dedicated settings surface.
- **Cons:** Guidance discovery varies by assistant; scattered or conflicting instructions make the effective default harder to establish.

## Recommendation

Choose **Option A** as a small documentation-led vertical slice: set a project default, start a feature without a debug request, record the resolved mode, and receive the existing step diagnostics and log.

- Scope the preference to the consuming project, including when Railspike is installed as a submodule or vendored directory.
- Resolve mode in this order: latest explicit workflow request, existing workflow state, project default for a new workflow, then disabled when no preference exists.
- Snapshot the default into a new workflow; subsequent project-default changes affect future workflows without silently changing ongoing ones. Preserve existing recovery rules for missing or conflicting resumed state.
- Allow a workflow-specific disable request without changing the shared project default; removing the preference restores the disabled default for future workflows.
- Surface an invalid or ambiguous project preference for clarification rather than silently selecting a mode.
- Keep existing diagnostic content, logging, and approval rules. Defer user-global defaults, nested-project inheritance, and tooling; define the setting's exact format and location in later planning.
