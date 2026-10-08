> **Feature plan:** [Step 1](./v3_terminal_operator_ui_step1_solution_assessment.md) · [Step 2](./v3_terminal_operator_ui_step2_feature_description.md) · [Step 3](./v3_terminal_operator_ui_step3_development_plan.md) · [Step 4](./v3_terminal_operator_ui_step4_implementation_summary.md)

## Problem

The growing `./v3` command surface makes routine status checks and safe private-configuration changes hard to discover, while configuration source and secret handling must remain clear and trustworthy.

## User Stories

- As an operator, I want a terminal dashboard for current status and effective private configuration so that I can identify the next safe action without remembering command syntax.
- As an operator, I want to see private-setting sources and redacted effective values so that I can identify configuration constraints before acting.
- As an automation author, I want existing `./v3` commands to remain authoritative and scriptable so that the TUI does not change established automation.

## Core Requirements

- Provide `./v3 tui` as a config-first status/config dashboard and command launcher, using `whiptail` when available and a concise CLI fallback otherwise.
- Define one shared configuration contract for defaults, validation, redaction, precedence, and supported setting metadata; use it for the dashboard and private-config flow.
- Delegate command execution and validation to existing commands; keep non-interactive command behavior intact and do not recreate site feature-flag management.
- Keep the TUI read-only for private configuration; defer the private-config editor, including presets, secret entry, save diffs, and atomic writes, to a follow-up feature.
- Exclude guided destructive workflows from this feature; environment-origin settings are visibly read-only.

## Delivery Scope

- Work type: application change.
- Completion boundary: an operator starts `./v3 tui` from an interactive terminal, views redacted status/configuration with source information, and launches supported existing commands. Cancellation, unavailable `whiptail`, and non-TTY use produce a usable CLI recovery path without state changes. The release condition is automated coverage of the shared configuration behavior, dashboard/launcher paths, and fallback behavior.

## Risks

- Secret exposure — impact: credential disclosure; earliest validation: exercise display, review, errors, and launched commands with test secrets; mitigation: redact by default and prohibit secrets in arguments, output, and saved previews.
- Configuration drift — impact: the UI reports or writes semantics different from runtime; earliest validation: compare effective sources and validation outcomes across supported settings; mitigation: make the shared contract the canonical source for both paths.
- Terminal incompatibility — impact: operators cannot use the interface in deployment environments; earliest validation: run with and without `whiptail` and with non-TTY input; mitigation: dependency detection and documented CLI fallback.
- Accidental state changes — impact: a launcher performs work unexpectedly; earliest validation: exercise each supported action and cancellation path; mitigation: limit this slice to explicitly selected existing commands and leave destructive workflows out of scope.

## Shared Component Inventory

- `./v3 status`: canonical operational-status renderer; reuse as the dashboard's status source rather than reimplementing status collection.
- `./v3 private-config`: canonical private-config read/write surface; reuse its read-only configuration semantics through the shared contract and retain it as the recovery path.
- `/tools/feature-flags/`: canonical web surface for site-managed feature flags; do not extend or duplicate it in the TUI, beyond directing operators to it when relevant.

## User Flow

1. Operator runs `./v3 tui` from an interactive terminal.
2. The interface shows redacted effective configuration, its source, and current operator status.
3. The operator selects a supported existing command to launch or exits without changing configuration.
4. The interface reports the result and the equivalent CLI recovery path when interactive use is unavailable.

## Success Criteria

- An interactive operator can complete the normal status/configuration flow without manually assembling command arguments.
- Supported settings display one consistent effective value, source, validation result, and redaction policy across the dashboard and private-config command.
- The dashboard and command launcher do not change private configuration or duplicate feature-flag management.
- Existing CLI commands and the web feature-flags workflow remain available and unchanged for their established uses.
- Missing `whiptail` or non-interactive input yields a clear, actionable CLI fallback without partial work.
