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

## Stage 2 - Put health and relevant actions on home
**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Added plain-text status --summary using canonical collector/HealthIndicators; home shows repository, health/queue, observation time, refresh and direct details. Five-second bounded collection retains stale last-known observations on failure.
- Verification: PHP terminal/collector/health checks 18/18; syntax checks and real ./v3 status --summary passed. Real whiptail PTY rendered home observation and queue action. PTY regression plus delayed/failed collection and stale snapshot recovery passed.
- Notes: Stage 1 commit bb6109593. No automatic repair, schema or dependency change. Existing collector tests cover ready/stale/unavailable and observation without state creation. Original estimate unchanged; detailed SSH/resize acceptance remains S6.

*— Codex · 2026-10-27 UTC*

## Stage 3 - Search diagnostics and retain context
**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Search filters the existing diagnostic catalog literally and case-insensitively; no-match recovery, retained search/selection and home selection. Removed confirmation for read-only diagnostics; streaming return/retry remains.
- Verification: Bash syntax passed; terminal PHP checks 4/4; navigation regression passed command-name search, no matches, arbitrary shell-looking input, exact allowlisted invocation and retained filter/selection.
- Notes: Stage 2 commit 5c2cf14d8. No arbitrary command execution or new diagnostic scope. Original estimate unchanged; real keyboard acceptance consolidated in S6.

*— Codex · 2026-10-27 UTC*

## Stage 4 - Edit resolved current LLM values
**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Replaced shell preset-first sequence with a PHP whiptail editor using schema-owned fields, defaults/legacy/file/environment resolution, per-field locks, explicit presets and draft correction. Secret input stays blank and travels through pipes, never command arguments or retained files. Shared file reader added to PrivateConfig.
- Verification: PHP syntax and focused config/schema/terminal checks 16/16. Actual editor/writer fixture passed current custom model/timeout, legacy source display, canceled review without write, preserved unrelated settings/secret, environment lock, invalid-timeout correction and blank-secret cancellation. Output/argument logs contain no fixture secret.
- Notes: Stage 3 commit f3125f4ff. Stage 5 adds coordinated stale-review protection before release; current saves still use the established stdin writer. Original estimate unchanged; dependency delta zero.

*— Codex · 2026-10-27 UTC*
