> **Feature plan:** [Step 1](./tui_operator_workspace_step1_solution_assessment.md) · [Step 2](./tui_operator_workspace_step2_feature_description.md) · [Step 3](./tui_operator_workspace_step3_development_plan.md) · [Step 4](./tui_operator_workspace_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/tui_operator_workspace; [host ledger](../effort_estimation_trial.md), standing P2 enrollment
Extended estimates: enabled
Status: Step 3 explicitly approved; implementation in progress
Cycle estimate: 0.75

## Entry checkpoint
Date: 2026-10-27 UTC
Scope: v1/A clarified; baseline 6e1b92798364dc84e30a411777d915b9d9d45f2a; R1/P2. Step 4 entry projection 0.75 reaffirmed before implementation; prior exposed single-rater Codex snapshots retained. No measured labor or independent rating. Stage identities/scopes/drivers and uncertainties inherit the approved Step 3; original S1–S6 estimates: 0.18/0.12/0.10/0.15/0.18/0.14, non-additive. Retrospectives pending.
Approval: User supplied Approved Step 3; branch feat/tui-operator-workspace; planning commit fc951e144. No changed-scope requests.
Boundary: No dependency/schema changes, deployment or todo.txt edits. Stage commits include their summary; substantive ledger updates belong to closeout.

*— Codex · 2026-10-27 UTC*

## Stage 1 - Stream diagnostics and restore the terminal
**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Shared Bash lifecycle helper streams output, isolates the launched process group, distinguishes completion/failure/interruption and restores terminal settings with return/retry.
- Verification: bash syntax passed; focused terminal command/dashboard PHP tests 4/4; Python standard-library real PTY regression passed early-before-completion output, success, exit 7, Ctrl-C, descendant cleanup and terminal restoration.
- Notes: Updated old buffered-output assertion and supplied return input in the dashboard fixture. An initial fixture run waited for input; terminated that owned test run after correcting the fixture. No dependency delta. Stage scope/estimate unchanged; no remote SSH claim.

*— Codex · 2026-10-27 UTC*
