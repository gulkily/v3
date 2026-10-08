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

## Stage 2 - Private-config schema migration

- Changes:
  - Replaced private-config's duplicated defaults, legacy resolution, effective-value/source rendering, redaction, and known-key handling with the shared schema.
  - Preserved existing generated-file format, permissions, atomic replacement path, and legacy values as additional file values.
  - Added command coverage for environment override precedence without secret disclosure.
- Verification:
  - `php -l scripts/write_private_config.php`
  - `php -l src/ForumRewrite/Support/PrivateConfigSchema.php`
  - `php -l tests/PrivateConfigCommandTest.php`
  - `./v3 test PrivateConfigSchemaTest PrivateConfigCommandTest LlmProviderConfigTest` — 15 passed.
- Notes:
  - No database, deployment, or TUI verification is applicable to this command-contract migration stage.

## Stage 3 - TUI entry boundary

- Changes:
  - Added `./v3 tui` and a terminal launcher that validates `whiptail` plus interactive stdin/stdout/stderr before opening any UI.
  - Added clear `./v3 status` and `./v3 private-config view` fallbacks for missing dependencies or non-interactive use.
  - Added command tests for help, non-interactive execution, and unavailable `whiptail`.
- Verification:
  - `bash -n scripts/terminal_operator_ui.sh`
  - `bash -n v3`
  - `php -l tests/TerminalOperatorUiCommandTest.php`
  - `./v3 test TerminalOperatorUiCommandTest StatusCommandTest PrivateConfigCommandTest` — 12 passed.
  - Direct pseudo-terminal check rendered the `whiptail` dialog; the harness could not send its close key, so the isolated no-op dialog was terminated.
- Notes:
  - The entry path starts no operational command before its terminal/dependency checks pass. Dashboard interaction and launcher cancellation coverage follow in later stages.

## Stage 4 - Read-only dashboard

- Changes:
  - Replaced the entry placeholder with a `whiptail` dashboard for canonical operator status, redacted effective private configuration, and the web-only feature-flags handoff.
  - Added explicit read-only context for environment overrides and site flags.
  - Added a pseudo-terminal dashboard test using a fake `whiptail` to inspect delegated output without running operational work.
- Verification:
  - `bash -n scripts/terminal_operator_ui.sh`
  - `php -l tests/TerminalOperatorUiDashboardTest.php`
  - `./v3 test TerminalOperatorUiCommandTest TerminalOperatorUiDashboardTest PrivateConfigCommandTest StatusCommandTest` — 13 passed.
- Notes:
  - The dashboard delegates status/configuration rendering to existing commands. Feature-flag editing remains exclusively on `/tools/feature-flags/`; no database, deployment, or write-path verification applies.
