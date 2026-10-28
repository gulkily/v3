> **Feature plan:** [Step 1](./tui_operator_workspace_step1_solution_assessment.md) · [Step 2](./tui_operator_workspace_step2_feature_description.md) · [Step 3](./tui_operator_workspace_step3_development_plan.md) · [Step 4](./tui_operator_workspace_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/tui_operator_workspace; [host ledger](../effort_estimation_trial.md), standing P2 enrollment
Extended estimates: enabled
Status: Step 1 Option A approved; Step 2 proposed
**Cycle estimate: 0.75**

## Problem
Operators must navigate separate dialogs to discover health, wait for diagnostics to finish before seeing output, and reconstruct existing LLM settings when editing. The TUI should connect current state, inspection and configuration without losing context.

## User stories
- As an operator, I want health and relevant inspection actions together so that I can identify the next useful action immediately.
- As an operator, I want searchable actions and visible command output so that I can diagnose failures and return to my previous selection.
- As an operator, I want to edit current LLM settings and review only changes so that unrelated values and secrets remain intact.

## Core requirements
- Home shows the selected instance/repository context, concise existing health/queue indicators, observation freshness and explicit unavailable/unknown states. Offer refresh and direct relevant detail actions; observation never initiates repairs.
- Search filters the existing read-only diagnostic catalog by familiar names, descriptions and command names; empty results are recoverable. Retain selection/search on return; inspection requires no confirmation. Preserve the web feature-flags handoff.
- Show diagnostic output as emitted, before command completion; distinguish running, success, failure and interruption. Allow safe interruption of the launched diagnostic and restore a usable terminal with return/retry; never infer background-job cancellation from a UI exit.
- Edit the existing LLM fields from resolved current values; show value sources and override locks. Presets are optional deliberate changes. Review an actual redacted before/after diff, preserve blank-secret-as-unchanged behavior and unrelated settings, validate before atomic save, and permit correction after failure or cancellation without a partial save.
- Retain whiptail and existing CLI fallbacks; support keyboard-only navigation, small/resized terminals and readable plain-text output. Secrets must not appear in output, review, command arguments or retained logs. Keep essential failure/recovery information concise and visible.

## Delivery scope and completion boundary
Work type: Application change, limited to the existing TUI, shared status/configuration interfaces needed by it, focused tests, CLI/operator documentation, cycle artifacts/debug log and study ledger. Dependency delta: zero; no new renderer, schema change, general config editor, feature-flag editor, repair/import/rebuild launcher or deployment. Normal entry is ./v3 tui: inspect health → select/search a diagnostic → observe output → return/retry; separately edit current LLM settings → review → save → verify effective values. Release requires both journeys, cancellation/failure recovery and existing CLI behavior to pass terminal acceptance; implementation remains gated by Step 3 approval.

## Environment and recovery
The shared workspace is WSL. Verify actual interactive entry there, including missing whiptail/noninteractive fallback, narrow/resized windows and interrupted diagnostics. Users must be able to return to the TUI or use the displayed CLI command, retry, and verify refreshed status or saved effective values. Exercise SSH-style terminal sessions and record whether a real remote SSH session was available; local fixtures alone do not establish remote acceptance.

## Risks
- Terminal handoff/interruption can leave a broken screen or child process: validate real pseudo-terminal behavior first; require clean return/exit and bounded diagnostic ownership.
- Status may be stale, slow or ambiguous: validate unavailable/slow collectors early; expose freshness and unknown state, keep inspection bounded and reuse current health semantics.
- Configuration sources can change between review and save: validate override/change cases early; recheck at save, retain entered non-secret values for correction and require renewed review on conflicts.

## Shared component inventory
Reuse/extend OperatorStatusCollector and HealthIndicators, ./v3 status, task-queue status and fast-score status for health/diagnostics; keep existing CLI meanings. Reuse PrivateConfigSchema/PrivateConfig and private-config view/update-llm for sources, presets, redaction, validation and persistence; no independent TUI policy lists. Extend the TUI home/chooser/editor/output presentation only. Existing web System State, Fastmod, Feature Flags and LLM Exchanges retain their respective status/content/configuration roles; no web redesign or second feature-flag control surface. No new public API is required.

## Flow and success criteria
1. Enter TUI: see health without opening a separate dialog; refresh or inspect the relevant detail.
2. Find and launch a diagnostic without read-only confirmation; observe an emitted marker before a delayed command finishes, then return with selection/search preserved. Verify failure, interruption and no-match recovery.
3. Open LLM editor: current values and sources match private-config view; unchanged fields survive a preset-free edit. Cancel writes nothing; failed validation/save permits correction; successful save matches the reviewed diff with secrets redacted.
4. Complete both paths with real whiptail in WSL, narrow/resized terminals and focused CLI/config regression checks; document terminal/SSH limitations and recovery. No full-suite or deployment claim without evidence.

## Study checkpoint and approval
Timestamp: 2026-10-27T05:10:33Z. Scope: v1/A clarified; original baseline 6e1b92798364dc84e30a411777d915b9d9d45f2a; R1/P2; single exposed Codex rater. Whole-cycle projection: 0.75 reaffirmed, including required verification/recovery/documentation and ledger upkeep; earlier intake/Step 1 preserved, all three frozen hashes reverified. Not measured labor or independent rating; no accepted outcome. Await Approved Step 2; documentation uncommitted, no application changes.

*— Codex · 2026-10-27 UTC*

Approval record: 2026-10-27 UTC — user explicitly supplied Approved Step 2; unchanged v1/A requirements proceed to Step 3. Historical handoff retained above.
