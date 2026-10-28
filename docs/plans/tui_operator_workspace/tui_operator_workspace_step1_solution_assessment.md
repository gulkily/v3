> **Feature plan:** [Step 1](./tui_operator_workspace_step1_solution_assessment.md) · [Step 2](./tui_operator_workspace_step2_feature_description.md) · [Step 3](./tui_operator_workspace_step3_development_plan.md) · [Step 4](./tui_operator_workspace_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/tui_operator_workspace; [host ledger](../effort_estimation_trial.md), standing P2 enrollment
Extended estimates: enabled
Cycle estimate: 0.75
Status: Step 1 Option A approved; Step 2 requirements in review; no implementation approval

## Original Query
How can we hugely improve TUI?

## Understood Intent
Make ./v3 tui substantially more useful for everyday operation, building on the delivered terminal dashboard and LLM editor.

## Problem
The current dialog launcher separates status from actions, withholds output until commands finish, and starts LLM editing from hard-coded presets rather than effective settings.

## Option A — Improve the existing operator workflow
**Estimate: 0.75**

Keep whiptail and deliver a status-first home screen, direct diagnosis/detail actions, a searchable command chooser, current-value LLM editing with source/override visibility and an actual redacted before/after review, plus streaming output for the existing read-only commands.
- Pros: Immediately useful; reuses current executors and installed dependency; avoids introducing a terminal rendering engine.
- Cons: Dialog transitions remain; continuous multi-pane monitoring is outside this slice. Main uncertainty is reliable terminal handoff, interruption and return across SSH/WSL and small windows.

## Option B — Persistent terminal workspace
**Estimate: 1.30**

Provide persistent navigation, refreshable health and queue views, searchable actions, a detail/output pane and current-value LLM editing, with terminal resizing and keyboard focus treated as core behavior.
- Pros: Strongest interaction improvement; status, selection and results remain visible together.
- Cons: Renderer/runtime choice and terminal compatibility add substantial work; split into independently usable cycles before proceeding. Any new dependency needs its own exact inventory and approval; no component selected or evaluated here.

## Option C — Guided operational workflows first
**Estimate: 1.10**

Retain the dialogs but add bounded import and rebuild journeys with destination review, preflight, live progress, truthful interruption/recovery and durable results using existing executors.
- Pros: Replaces difficult command sequences with complete operator tasks.
- Cons: Does less for everyday navigation; process ownership, cancellation and partial-state recovery dominate the work. Split import and rebuild before approval; backup/restore and general lifecycle redesign remain separate.

## Recommendation
Start with A as one releasable cycle: inspect health → open relevant detail → run a read-only diagnostic → see output immediately → return with selection retained; edit the existing LLM settings without resetting unrelated values. Remove confirmations for read-only inspection, preserve essential write review, and keep unknown/stale status explicit. Later choose B for the interaction ceiling or C for operational breadth; their estimates are alternatives, not additive. No automatic repair, feature-flag duplication, general configuration editor or destructive workflow in A. Validate actual pseudo-terminal interaction, narrow/resized windows, cancellation, failure/retry and secret redaction. Dependency delta for A: zero.

## Study checkpoints
Scope: v1/A; baseline 6e1b92798364dc84e30a411777d915b9d9d45f2a; distinct follow-up to delivered terminal UI/private-config editor.
Intake: 2026-10-27T04:57:58Z; 0.75, single exposed Codex rater after source/prior-plan inspection; pristine pre-inspection checkpoint missing, not reconstructed.
Step 1 handoff: 2026-10-27T04:57:58Z; 0.75 reaffirmed for A, including planning, verification, recovery and documentation. R1 frozen source hashes verified against host ledger. Whole-cycle judgments, not hours or measured labor; no independent rating or accepted outcome.
Approval: Await Approved Step 1 before Step 2; plans remain uncommitted.

*— Codex · 2026-10-27 UTC*

Approval record: 2026-10-27 UTC — user explicitly supplied Approved Step 1; Option A proceeds to requirements. Historical handoff retained above.
