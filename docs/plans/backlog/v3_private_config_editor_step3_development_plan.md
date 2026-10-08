> **Feature plan:** [Step 1](./v3_private_config_editor_step1_solution_assessment.md) · [Step 2](./v3_private_config_editor_step2_feature_description.md) · [Step 3](./v3_private_config_editor_step3_development_plan.md) · [Step 4](./v3_private_config_editor_step4_implementation_summary.md)

## Completion Contract

- Normal entry: `./v3 tui` → private configuration → LLM connection editor.
- End-to-end outcome: select a preset or custom provider, enter valid fields, review a redacted diff, confirm, atomically save, and return to the updated dashboard.
- Required recovery: cancel, invalid fields, environment locks, or a write failure leave the original file unchanged and identify the existing CLI/editor alternative.
- Deployment/external verification: use an isolated private-config fixture; no deployment or database migration is required.
- Release condition: preset, validation, redaction, diff, lock, cancel, and atomic-write tests pass; other configuration groups and site flags remain unchanged.

## Key Risks

- **High risk: secret exposure.** Impact: credential disclosure. Early validation: sentinel-secret tests for entry, review, diagnostics, and subprocesses. Mitigation: secret input only through the terminal-safe path and redaction at every display boundary.
- **High risk: invalid connection settings.** Impact: broken analysis/reply services. Early validation: preset and custom-provider validation before review. Mitigation: reject invalid values before the save contract runs.
- **High risk: unintended write.** Impact: configuration loss. Early validation: cancel, lock, and replacement-failure fixtures. Mitigation: explicit confirmation plus the existing atomic writer.

## Stage 1

- Goal: Define editable LLM-connection metadata and validation.
- Dependencies: None.
- Expected changes: Extend the shared schema with LLM presets, editable-field rules, provider requirements, environment locks, and planned validation/diff surfaces; no database changes.
- Verification approach: Unit cases cover every preset, custom-provider requirements, locks, and redacted value changes.
- Risks or open questions:
  - Impact: metadata diverges from runtime provider rules.
  - Early warning / validation: compare preset outcomes to current provider defaults.
  - Mitigation: make the schema the only editor metadata source.
- Canonical components/API contracts touched: `PrivateConfigSchema`, `LlmProviderConfig` provider conventions.

## Stage 2

- Goal: Add a safe LLM-update save contract to private-config.
- Dependencies: Stage 1.
- Expected changes: Add a non-argument, standard-input update request for validated LLM values; preserve untouched/legacy values and reuse existing atomic replacement, permissions, and errors.
- Verification approach: Command tests cover valid save, invalid request, unchanged secret, replacement secret, cancel-equivalent no request, and failed replacement fixtures.
- Risks or open questions:
  - Impact: secret reaches shell history, output, or an argument list.
  - Early warning / validation: inspect captured command output and process invocation fixtures.
  - Mitigation: accept the secret only on standard input and return redacted results.
- Canonical components/API contracts touched: `./v3 private-config`, `scripts/write_private_config.php`, `PrivateConfigCommandTest`.

## Stage 3

- Goal: Collect LLM edits safely in the terminal UI.
- Dependencies: Stages 1–2.
- Expected changes: Add the editor route, preset selection, editable prompts, masked API-key input, environment-lock messaging, and validation-error recovery to the existing configuration dashboard.
- Verification approach: Fake-`whiptail` interactions cover presets, custom fields, masked entry routing, locked fields, and cancellation before save.
- Risks or open questions:
  - Impact: the UI collects values that cannot be saved safely.
  - Early warning / validation: test each form outcome against the save contract before review exists.
  - Mitigation: pass only validated data through the standard-input contract.
- Canonical components/API contracts touched: `scripts/terminal_operator_ui.sh`, `PrivateConfigSchema`, terminal UI test harness.

## Stage 4

- Goal: Add review, confirmation, save, and dashboard return.
- Dependencies: Stage 3.
- Expected changes: Render redacted before/after changes, require explicit confirmation, invoke the save contract, and show the resulting effective configuration or recovery guidance.
- Verification approach: End-to-end fixture tests cover confirmed save, cancel, invalid data, lock, write failure, preserved unknown/legacy values, and no secret output.
- Risks or open questions:
  - Impact: the review misrepresents the persisted result.
  - Early warning / validation: compare diff records with the saved fixture and reloaded effective values.
  - Mitigation: generate review data from the same validated update request used for saving.
- Canonical components/API contracts touched: TUI dashboard, private-config save contract, shared schema, private-config fixture tests.

## Stage 5

- Goal: Verify and document the editor boundary.
- Dependencies: Stages 1–4.
- Expected changes: Add focused regression coverage and document the LLM-only scope, environment-lock recovery, and existing CLI/editor fallback.
- Verification approach: Run focused tests, syntax checks, fixture-based terminal flow, and documentation review against the editor catalog.
- Risks or open questions:
  - Impact: documentation implies unsupported configuration groups or secret handling.
  - Early warning / validation: compare docs with the offered editor fields and fallback messages.
  - Mitigation: name deferred groups and never show secret values in examples.
- Canonical components/API contracts touched: `docs/reference/v3_cli.md`, TUI tests, `PrivateConfigCommandTest`.
