> **Feature plan:** [Step 1](./v3_private_config_editor_step1_solution_assessment.md) · [Step 2](./v3_private_config_editor_step2_feature_description.md) · [Step 3](./v3_private_config_editor_step3_development_plan.md) · [Step 4](./v3_private_config_editor_step4_implementation_summary.md)

## Problem

The read-only terminal dashboard exposes private-configuration state, but operators must still manually edit PHP to change an LLM connection safely.

## User Stories

- As an operator, I want to choose an LLM provider preset and enter its connection values so that I can configure a supported provider without reconstructing its defaults.
- As an operator, I want API-key input and change review to remain redacted so that I can update credentials without disclosing them on screen or in logs.
- As an operator, I want cancellation or validation failure to preserve the existing private config so that I can recover with the current CLI/editor workflow.

## Core Requirements

- Add a guided LLM connection editor to `./v3 tui` for provider, API key, base URL, model, and timeout; offer OpenAI, OpenRouter, Anthropic, stub, and custom-provider presets.
- Reuse the shared configuration contract for field metadata, defaults, validation, redaction, value source, and environment locks.
- Mask secret entry, preserve an existing key when no replacement is provided, and show only a redacted diff before an explicit save confirmation.
- Reuse the existing private-config atomic write and preservation behavior; TUI editing must not expose secrets in output, process arguments, or saved review text.
- Keep Fastmod, agent, recording, arbitrary PHP, web feature flags, and destructive workflows out of scope.

## Delivery Scope

- Work type: application change.
- Completion boundary: from `./v3 tui`, an operator opens the LLM editor, selects a preset or custom provider, enters valid editable values, reviews a redacted difference, confirms an atomic save, and returns to the updated dashboard. Cancellation, invalid data, environment-locked fields, or write failure leave the file unchanged and provide the existing CLI/editor recovery path. Release requires focused editor, redaction, validation, and atomic-write coverage.

## Risks

- Secret exposure — impact: credential disclosure; earliest validation: exercise entry, review, errors, command invocation, and test captures with a sentinel secret; mitigation: never render or pass secret values through arguments and redact all output.
- Invalid provider configuration — impact: analysis/reply services stop working; earliest validation: test each preset and custom-provider requirements before save; mitigation: validate before review and retain the existing configuration on failure.
- Environment conflict — impact: a saved value appears ineffective; earliest validation: test environment-origin target fields; mitigation: mark them read-only with a deployment-change recovery path.
- Write failure — impact: lost or corrupted configuration; earliest validation: simulate failed validation/replacement; mitigation: use the existing atomic writer and only invoke it after confirmation.

## Shared Component Inventory

- `PrivateConfigSchema`: canonical metadata and effective-value contract; extend it for editor-specific validation/preset metadata rather than maintaining a TUI copy.
- `./v3 private-config`: canonical private-config read/write and atomic replacement surface; extend/reuse it for the save operation rather than writing from the TUI directly.
- `scripts/terminal_operator_ui.sh`: canonical terminal dashboard; extend its configuration route with the editor flow.
- `/tools/feature-flags/`: existing site-flag management surface; leave unchanged because private LLM configuration is not a site feature flag.

## User Flow

1. Operator opens private configuration in `./v3 tui` and selects LLM connection editing.
2. The editor shows current redacted values and locks environment-origin fields.
3. The operator selects a preset or custom provider and supplies valid editable values, including an optional masked API-key replacement.
4. The editor presents a redacted diff; the operator confirms save or cancels.
5. On success the dashboard shows the updated effective configuration; otherwise it leaves the file unchanged and points to the CLI/editor fallback.

## Success Criteria

- Each supported preset produces a valid editable starting point, while a custom provider requires its needed connection details.
- No secret value appears in terminal output, diffs, command arguments, or test diagnostics.
- A confirmed valid edit preserves unrelated and legacy values and updates the private config atomically.
- Cancelled, invalid, locked, and failed edits leave the original file byte-for-byte unchanged.
- The dashboard and web feature-flags workflow retain their established read-only/management boundaries.
