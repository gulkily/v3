> **Feature plan:** [Step 1](./v3_private_config_editor_step1_solution_assessment.md) · [Step 2](./v3_private_config_editor_step2_feature_description.md) · [Step 3](./v3_private_config_editor_step3_development_plan.md) · [Step 4](./v3_private_config_editor_step4_implementation_summary.md)

## Stage 1 - LLM editor metadata

- Changes:
  - Added shared LLM provider presets, editable-field metadata, validation, environment locks, and redacted-diff support.
  - Added schema coverage for presets, validation failures, redaction, and locks.
- Verification:
  - `php -l src/ForumRewrite/Support/PrivateConfigSchema.php`
  - `php -l tests/PrivateConfigSchemaTest.php`
  - `./v3 test PrivateConfigSchemaTest PrivateConfigCommandTest LlmProviderConfigTest` — 16 passed.
- Notes:
  - No database, deployment, or terminal UI behavior is applicable to this schema stage.

## Stage 2 - Stdin-only LLM save contract

- Changes:
  - Added `private-config update-llm`, which accepts editable LLM values only as a JSON request on standard input.
  - Reused schema validation, environment locks, and the existing atomic writer while preserving unrelated values.
  - Added a command test proving the API key stays out of output and unrelated values survive the update.
- Verification:
  - `php -l scripts/write_private_config.php`
  - `php -l src/ForumRewrite/Support/PrivateConfigSchema.php`
  - `php -l tests/PrivateConfigCommandTest.php`
  - `./v3 test PrivateConfigSchemaTest PrivateConfigCommandTest` — 10 passed.
- Notes:
  - The request is deliberately standard-input-only; no database, deployment, or terminal UI verification applies at this stage.
