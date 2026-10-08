> **Feature plan:** [Step 1](./v3_terminal_operator_ui_step1_solution_assessment.md) · [Step 2](./v3_terminal_operator_ui_step2_feature_description.md) · [Step 3](./v3_terminal_operator_ui_step3_development_plan.md) · [Step 4](./v3_terminal_operator_ui_step4_implementation_summary.md)

## Stage 1 - Shared configuration contract

- Changes:
  - Added `PrivateConfigSchema` with configuration definitions, template defaults, environment keys, effective-value sources, legacy fallbacks, redaction, and additional-value handling.
  - Made `PrivateConfig` take its recognized environment variables from that schema.
  - Added focused schema tests and registered them with the test runner.
- Verification:
  - `php -l src/ForumRewrite/Support/PrivateConfigSchema.php`
  - `php -l src/ForumRewrite/Support/PrivateConfig.php`
  - `php -l tests/PrivateConfigSchemaTest.php`
  - `./v3 test PrivateConfigSchemaTest PrivateConfigCommandTest LlmProviderConfigTest` — 14 passed.
- Notes:
  - No database, deployment, or TUI verification is applicable to this internal configuration-contract stage.
