> **Feature plan:** [Step 1](./v3_terminal_operator_ui_step1_solution_assessment.md) · [Step 2](./v3_terminal_operator_ui_step2_feature_description.md) · [Step 3](./v3_terminal_operator_ui_step3_development_plan.md) · [Step 4](./v3_terminal_operator_ui_step4_implementation_summary.md)

## Completion Contract

- Normal entry: `./v3 tui` from an interactive terminal with `whiptail`.
- End-to-end outcome: view redacted effective config and `./v3 status`, then launch an allowed read-only command.
- Required recovery: cancellation, non-TTY input, or missing `whiptail` changes nothing and prints a CLI alternative.
- Deployment/external verification: test installed/missing `whiptail` with a non-production private-config fixture; no deployment or database migration.
- Release condition: focused config, dispatcher, dashboard, fallback, and launcher tests pass without changing `private-config`, `status`, or web feature-flag behavior.

## Key Risks

- **High risk: secret exposure.** Impact: credential disclosure. Early validation: test display, error, and subprocess paths with fixtures. Mitigation: schema-owned redaction and no secret command arguments.
- **High risk: configuration drift.** Impact: misleading status. Early validation: compare default, file, environment, and legacy values. Mitigation: migrate private-config reporting before the TUI uses the contract.
- **High risk: accidental work launch.** Impact: state changes. Early validation: test catalog/cancellation. Mitigation: fixed read-only allowlist only.

## Stage 1

- Goal: Define the canonical read-only configuration contract.
- Dependencies: None.
- Expected changes: Add schema definitions and planned `definitions(): array` / `resolve(array $fileValues): array` surfaces for defaults, validation metadata, redaction, legacy mappings, and precedence; no database changes.
- Verification approach: Cover default, file, environment, legacy, redacted-secret, and unknown-value resolution.
- Risks or open questions:
  - Impact: a missing setting leaves the dashboard incomplete.
  - Early warning / validation: inventory current `private-config view` output.
  - Mitigation: retain unknown values as marked additional values.
- Canonical components/API contracts touched: `PrivateConfig`; `./v3 private-config` configuration semantics.

## Stage 2

- Goal: Move private-config reporting to the contract.
- Dependencies: Stage 1.
- Expected changes: Use the schema for defaults, template metadata, effective source/value rendering, and redaction; preserve existing write format, permissions, and atomic behavior.
- Verification approach: Extend private-config tests for output/source parity, legacy values, environment overrides, and secret safety.
- Risks or open questions:
  - Impact: changed rendering or file generation breaks operators.
  - Early warning / validation: run existing fixtures before and after migration.
  - Mitigation: retain commands and their current write path unchanged.
- Canonical components/API contracts touched: `scripts/write_private_config.php`, `PrivateConfigCommandTest`.

## Stage 3

- Goal: Add the safe TUI entry boundary.
- Dependencies: Stage 2.
- Expected changes: Add `./v3 tui`, interactive-terminal and `whiptail` checks, a CLI fallback, and cancellation-safe process handling.
- Verification approach: Cover help, piped input, missing dependency, and cancellation.
- Risks or open questions:
  - Impact: unsupported terminals strand operators.
  - Early warning / validation: exercise fallback paths directly.
  - Mitigation: print a direct CLI alternative before work starts.
- Canonical components/API contracts touched: `v3` dispatcher; CLI error-handling contract.

## Stage 4

- Goal: Deliver the read-only dashboard.
- Dependencies: Stages 2–3.
- Expected changes: Show shared-schema configuration/source labels and canonical status output; mark environment values read-only and link site flags to the web tool.
- Verification approach: Fixture-driven interactions cover redaction, sources, ready/stale status, and exit.
- Risks or open questions:
  - Impact: formatting obscures unsafe or unavailable state.
  - Early warning / validation: inspect ready, stale, and missing-runtime fixtures.
  - Mitigation: preserve direct-command access as the authority.
- Canonical components/API contracts touched: `./v3 status`, `OperatorStatusCollector`, schema, `/tools/feature-flags/` boundary.

## Stage 5

- Goal: Add the bounded command launcher.
- Dependencies: Stage 4.
- Expected changes: Add a labeled read-only catalog, selected-command review, and dashboard return; omit arguments and destructive commands.
- Verification approach: Confirm each entry delegates correctly, cancellation runs nothing, and excluded commands are absent.
- Risks or open questions:
  - Impact: the catalog drifts or launches stateful work.
  - Early warning / validation: assert a fixed read-only allowlist.
  - Mitigation: delegate validation/execution to the existing commands.
- Canonical components/API contracts touched: `v3`, `./v3 status`, `./v3 task-queue status`, `./v3 fast-score status`.

## Stage 6

- Goal: Verify and document the release boundary.
- Dependencies: Stages 1–5.
- Expected changes: Add focused end-to-end coverage and document entry requirements, fallback commands, and deferred workflows.
- Verification approach: Run focused tests, shell syntax checks, and supported/fallback manual checks.
- Risks or open questions:
  - Impact: documentation implies unsupported editing or recovery.
  - Early warning / validation: compare docs with the command catalog and fallback output.
  - Mitigation: state the deferred editor and destructive workflows explicitly.
- Canonical components/API contracts touched: `docs/reference/v3_cli.md`, TUI tests, `PrivateConfigCommandTest`, `StatusCommandTest`.
