> **Feature plan:** [Step 1](./tui_operator_workspace_step1_solution_assessment.md) · [Step 2](./tui_operator_workspace_step2_feature_description.md) · [Step 3](./tui_operator_workspace_step3_development_plan.md) · [Step 4](./tui_operator_workspace_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/tui_operator_workspace; [host ledger](../effort_estimation_trial.md), standing P2 enrollment
Extended estimates: enabled
Status: Step 2 approved; Step 3 proposed; application change
Cycle estimate: 0.75

## Completion Contract
Entry: ./v3 tui in WSL. Outcome: inspect current health, search/run existing diagnostics with live output, return with context retained; edit current LLM values, review actual redacted changes, save and verify effective values. Recovery: unknown/slow status, no matches, command failure/interruption, canceled/invalid/conflicting saves and missing-whiptail/noninteractive fallbacks. Release: both real-terminal journeys and focused regressions pass; record narrow/resized and SSH-style evidence, distinguishing actual remote SSH availability. Deployment is outside scope; unresolved terminal or save-safety risks block release.

## Key Risks
- **High risk:** interrupted children or broken terminal state; validate delayed output, Ctrl-C and resize first; bound ownership, restore terminal state and retain CLI recovery before dependent UI work.
- **High risk:** stale review or exposed secrets; test concurrent configuration changes and redaction before enabling revised saves; recheck under coordinated persistence, require renewed review and preserve unrelated values.
- Slow/ambiguous observations: test unavailable and delayed collectors early; bound collection and show observation freshness/unknown without repair.

## Stage 1 — Stream diagnostics and restore the terminal
**Estimate: 0.18**

- Goal: S1, live existing diagnostics with return/retry.
- Dependencies: None.
- Expected changes: Replace buffered-only execution; retain exit/interruption distinction, scoped child cleanup and terminal restoration.
- Verification approach: Real PTY marker before delayed completion; success, failure, Ctrl-C, descendant cleanup and subsequent usable dialog.
- Risks or open questions: Signal ownership can affect unrelated work; prove isolation first, block dependents if unresolved.
- Canonical components/API contracts touched: scripts/terminal_operator_ui.sh; existing status/task-queue/fast-score commands; TerminalOperatorUiDashboardTest.
- Estimate details: Stage work; driver: process/terminal lifecycle; uncertainty: WSL signal behavior.

## Stage 2 — Put health and relevant actions on home
**Estimate: 0.12**

- Goal: S2, inspect context and health immediately, refresh or open detail.
- Dependencies: S1 lifecycle gate.
- Expected changes: Concise shared health/queue observations with freshness, bounded collection and explicit unavailable states; retain web feature-flags handoff.
- Verification approach: Healthy/stale/unavailable/slow fixtures and real home-to-detail-to-refresh journey; observation performs no repair.
- Risks or open questions: Collection can stall navigation; validate timeout behavior before wiring home and retain last observation as stale.
- Canonical components/API contracts touched: TUI; OperatorStatusCollector, HealthIndicators, status output; existing collector/dashboard tests.
- Estimate details: Stage work; driver: bounded shared observations; uncertainty: collector delays.

## Stage 3 — Search diagnostics and retain context
**Estimate: 0.10**

- Goal: S3, find and inspect without redundant confirmation.
- Dependencies: S1–S2.
- Expected changes: Search existing allowlisted names/descriptions/commands; preserve filter and selection after return/retry; recover empty matches.
- Verification approach: Keyboard search, no matches, cancellation, failed diagnostic and repeated return; CLI command meanings unchanged.
- Risks or open questions: Search must never become shell execution; filter catalog entries only and test arbitrary input.
- Canonical components/API contracts touched: TUI chooser/launcher and TerminalOperatorUiDashboardTest.
- Estimate details: Stage work; driver: navigation state; uncertainty: whiptail selection behavior.

## Stage 4 — Edit resolved current LLM values
**Estimate: 0.15**

- Goal: S4, preset-free edits preserve current settings.
- Dependencies: S1 terminal handling.
- Expected changes: Reuse schema resolution, source/lock information and optional presets; retain non-secret draft on correction, blank secret unchanged; remove duplicated policy.
- Verification approach: Defaults/file/legacy/environment cases, custom values, explicit preset, locked fields and cancel-without-write; no secret in arguments/output/logs.
- Risks or open questions: Redacted values cannot seed secrets; keep secret input blank and transport privately, cleaning temporary state on exit.
- Canonical components/API contracts touched: TUI; PrivateConfig/PrivateConfigSchema and scripts/write_private_config.php; existing schema/config tests.
- Estimate details: Stage work; driver: source-aware editing; uncertainty: legacy fallback interactions.

## Stage 5 — Review and save the exact change safely
**Estimate: 0.18**

- Goal: S5, reviewed diff matches saved effective values with correction/review recovery.
- Dependencies: S4; save-safety gate before enabling new path.
- Expected changes: Shared validated preview and redacted before/after diff; conflict-aware save rechecks sources and reviewed configuration, preserves unrelated values and uses atomic persistence. Retain existing stdin update compatibility.
- Verification approach: No-op, blank/replaced secret, invalid values, override/concurrent-edit conflict, failed write/rename and correction/re-review; creation/refresh-template preserve values.
- Risks or open questions: Atomic rename alone prevents neither stale review nor lost updates; validate coordinated check/write and conflict recovery before release, without claiming protection from uncooperative external writers.
- Canonical components/API contracts touched: private-config view/update-llm, schema validation/redactedLlmDiff, private-config writer, TUI and PrivateConfigCommandTest.
- Estimate details: Stage work; driver: preview/save consistency; uncertainty: concurrent writers.

## Stage 6 — Replay operator journeys and close out
**Estimate: 0.14**

- Goal: S6, demonstrate the approved outcome in the user's environment.
- Dependencies: S1–S5 and resolved risks.
- Expected changes: Focused acceptance coverage, CLI/operator reference, copy audit and implementation/study records; fix in-scope integration defects.
- Verification approach: Real whiptail WSL journeys, small/resized PTYs, SSH-style session, missing dependency/noninteractive recovery; replay buffered-output and preset-reset failures. Run terminal, collector/health and private-config suites; document actual remote SSH and other unavailable checks.
- Risks or open questions: Fake dialogs miss usability failures; real interaction is required, limitations remain explicit.
- Canonical components/API contracts touched: Existing focused tests; docs/reference/v3_cli.md; docs/plans/ui_copy_audit_checklist.md; cycle summary/debug log and host ledger.
- Estimate details: Stage work; driver: integrated terminal acceptance; uncertainty: remote access. Owns shared closeout/documentation overhead; each earlier stage owns its focused checks and summary.

## Execution and study checkpoint
Scope: v1/A clarified, unchanged from approved Step 2; original baseline 6e1b92798364dc84e30a411777d915b9d9d45f2a. Request: Approved Step 2 / accepted, proceed to planning / no added scope. Six atomic stages, zero implemented; re-split any oversized stage before implementation.
Inventory: Existing whiptail/Bash/PHP and project components only; dependency delta zero, no external component approval needed. No schema, canonical-format or web-navigation changes. Human-facing records remain Key: value/plain text; JSON is confined to existing machine protocols. Changes remain within Step 2's TUI/shared-interface/test/operator-documentation boundary; todo.txt excluded.
Execution: Await Approved Step 3. Then establish feature branch, organize plans and make the permitted planning commit; each stage requires checks, summary and commit before the next. Ledger substantive changes remain outside the initial planning-commit allowance.
Checkpoint: 2026-10-27T16:51:19Z; railspike-effort / v3/tui_operator_workspace; Step 3 handoff; R1/P2; Codex single exposed rater after source, prior estimates and frozen-reference inspection. All three frozen hashes verified. Whole-cycle projection 0.75 reaffirmed: existing schema reuse offsets the newly explicit lifecycle/concurrency verification. Earlier intake/Step 1/Step 2 retained; no scope expansion. First S1–S6 stage-work estimates 0.18/0.12/0.10/0.15/0.18/0.14 are separate non-additive judgments using R1, not hours or measured labor; cycle total independently assessed. No accepted outcome, independent rating or reconstructed intake.

*— Codex · 2026-10-27 UTC*
