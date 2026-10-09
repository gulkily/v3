> **Feature plan:** [Step 1](./v3_private_config_editor_step1_solution_assessment.md) · [Step 2](./v3_private_config_editor_step2_feature_description.md) · [Step 3](./v3_private_config_editor_step3_development_plan.md) · [Step 4](./v3_private_config_editor_step4_implementation_summary.md)

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for the next step.

## Understood Intent

The next deferred step is a guided private-config editor within `./v3 tui`, following the completed read-only terminal dashboard and shared configuration contract.

## Problem Statement

Operators can inspect redacted effective configuration in `./v3 tui`, but safe private-config changes still require editing a PHP file directly without presets, field validation, or a reviewable difference.

## Option A: Retain the external-editor workflow

Keep `./v3 private-config edit` as the only editing path, with the TUI linking to its existing instructions.

- Pros: no new secret-entry or write-path risk; preserves familiar operator control.
- Cons: does not provide presets, field validation, masked input, or save review; leaves the TUI read-only.

## Option B: Edit every supported setting in one TUI release

Build forms for all private-config groups, including provider, Fastmod, agent, and recording settings, with a common review-and-save flow.

- Pros: one complete in-terminal editing destination.
- Cons: broad validation and dependency surface; difficult to keep the first release small and auditable.

## Option C: Guided LLM connection editor first

Add a focused editor for LLM provider settings, using provider presets, masked API-key input, validation, redacted diff review, and the established atomic private-config write behavior; retain other settings as read-only until later slices.

- Pros: covers the highest-risk secret-bearing configuration with a coherent end-to-end recovery path; reuses the shared schema and existing private-config contract.
- Cons: other editable settings still require the existing CLI/editor workflow initially.

## Recommendation

Choose **Option C**. It is a viable vertical slice: an operator enters the TUI, selects an LLM provider preset or custom provider, safely updates supported connection fields, reviews a redacted change set, saves atomically, and returns to the effective-config dashboard. Environment-origin values remain read-only; cancellation, invalid input, and write failure leave the original file intact. Defer Fastmod, agent, recording, and destructive workflows to separate slices.
