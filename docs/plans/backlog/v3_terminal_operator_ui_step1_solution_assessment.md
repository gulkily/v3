> **Feature plan:** [Step 1](./v3_terminal_operator_ui_step1_solution_assessment.md) · [Step 2](./v3_terminal_operator_ui_step2_feature_description.md) · [Step 3](./v3_terminal_operator_ui_step3_development_plan.md) · [Step 4](./v3_terminal_operator_ui_step4_implementation_summary.md)

## Original Query

I saw this in our todo.txt:

terminal operator ui (`./v3 tui`)
----------------------------------
	config-first status/config dashboard and command launcher
	extract shared config schema: defaults, validation, redaction, precedence
	private-config editor: presets, masked secrets, atomic writes, save diff
	later: guided destructive workflows with review, confirmation, output, cancellation
	keep scripts as executor/validator; do not duplicate web feature-flags ui
	use whiptail with dependency check and cli fallback

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Problem Statement

Operators need a safe, discoverable terminal interface for status and private configuration without replacing the script-based CLI contracts or duplicating the web feature-flags UI.

## Option A: Extend help and the existing private-config command

Keep `./v3` non-interactive, improving its help, documentation, and `private-config` editor as needed.

- Pros: smallest change; no interactive dependency or new terminal test surface.
- Cons: leaves configuration precedence, command discovery, and safe multi-step actions distributed across many commands.

## Option B: Config-first terminal operator UI

Add `./v3 tui` as a `whiptail` interface with a dependency check and CLI fallback; it displays redacted effective configuration and status, selects commands, and delegates execution and validation to the existing CLI.

- Pros: directly addresses the highest-friction operator tasks while preserving automation; makes sources, overrides, and locked values visible; supports an incremental private-config editor once shared metadata exists.
- Cons: requires a shared configuration schema and terminal interaction coverage; the first release must deliberately exclude destructive guided workflows.

## Option C: Full guided terminal workflow console

Build forms for the complete command surface, including destructive and recovery workflows, before releasing the TUI.

- Pros: eventually provides one uniform operator destination.
- Cons: large and risky; duplicates workflow assembly before the shared configuration contract is mature; delays the useful status/config slice.

## Recommendation

Choose **Option B**. The viable vertical slice is a read-only `./v3 tui` dashboard with redacted effective configuration, `./v3 status` output, command discovery/launching, and clear CLI fallback when `whiptail` is unavailable. Extract the shared configuration schema before adding the private-config editor; keep site-managed feature flags read-only in the TUI with a pointer to their existing web UI. Defer guided destructive workflows until a later feature, where review, explicit confirmation, streamed output, and cancellation can be designed as a coherent safety model.
