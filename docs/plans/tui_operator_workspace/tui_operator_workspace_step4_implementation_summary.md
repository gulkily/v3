> **Feature plan:** [Step 1](./tui_operator_workspace_step1_solution_assessment.md) · [Step 2](./tui_operator_workspace_step2_feature_description.md) · [Step 3](./tui_operator_workspace_step3_development_plan.md) · [Step 4](./tui_operator_workspace_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/tui_operator_workspace; [host ledger](../effort_estimation_trial.md), standing P2 enrollment
Extended estimates: enabled
Status: Six stages locally complete; maintainer acceptance pending
Cycle estimate: 0.85

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

## Stage 5 - Review and save the exact change safely
**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: Shared schema update policy validates and normalizes preview/save, including real redacted differences and explicit secret replacement. Config snapshot binds file bytes and effective LLM sources/values; save rechecks before replacement. Persistent private sidecar lock coordinates writer/edit/refresh operations with a three-second busy response. Conflicts reload sources, retain non-secret unlocked drafts and require review again. No-op leaves bytes unchanged; temporary files are private from creation and failed writes/renames clean up.
- Verification: Config/schema PHP checks 12/12; editor regression passed deliberate presets, replacement-key redaction and concurrent edit/re-review preserving external unrelated changes. Writer regression passed no-op, invalid/unsupported input, file/environment conflicts, bounded lock contention, permissions, legacy stdin compatibility and failed rename cleanup. PHP syntax passed.
- Notes: Stage 4 commit 5ce25b1e5. An uncooperative external writer can still race after the final check; coordinated project writers are serialized. No new dependency/schema or secret-bearing argument/log. Original estimate unchanged; remote/real-terminal integrated acceptance remains S6.

*— Codex · 2026-10-27 UTC*

## Stage 6 discovery - Live resize
Date: 2026-10-27 UTC
Evidence: Real 80x24 → 50x18 resize while home was open left the original newt layout clipped, hiding buttons. Entry-at-size and resize-between-dialog checks had passed; neither covered this failure.
Scope: In-scope correction to approved small/resized-terminal recovery, not a new renderer or dependency.
Stage S6 original estimate: 0.14 retained.
Stage S6 revised whole-stage estimate: 0.20, before dependent correction; driver is shared dialog resize supervision and draft/navigation recovery; uncertainty is newt termination/restoration behavior.
Cycle revised projection: 0.85, independently assessed for the unchanged v1/A outcome; prior 0.75 projections retained. R1/P2, frozen hashes reverified; same exposed single Codex rater, no measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 6 - Replay operator journeys and close out
**Original estimate: 0.14**
**Revised estimate: 0.20**
**Retrospective stage work: 0.22**

- Changes: Adaptive menu sizing, concise labels and explicit undersized-terminal recovery; schema sources precede long values; long reviews scroll. Shared TerminalDialog supervises existing whiptail, recreates dialogs on live resize and restores terminal settings; navigation and committed drafts survive. Updated CLI help/reference and copy audit. No new renderer/dependency.
- Verification: Focused PHP terminal/status/config/schema/collector/health checks 37/37; final terminal checks 4/4. Five Python suites passed: terminal_operator_ui_pty_test.py (live output, success/failure/interruption, descendants, terminal state, bounded/stale collection); terminal_operator_ui_navigation_test.py (literal search, no-match recovery, retained context); terminal_llm_editor_test.py (current sources, locks, presets, blank/replaced secrets, validation, conflicts, failed-save retry, cancellation); private_config_review_test.py (no-op, malformed input, file/source conflicts, busy lock, atomic permissions/rename cleanup and legacy stdin); terminal_operator_ui_acceptance_test.py (real whiptail normal-entry journeys at 80x24/50x18, live home/editor resize, 40x12 recovery, long-value scrolling review, save/reopen/cancel and SSH-style environment).
- Corrections: Real resize initially clipped buttons; shared resize supervision resolved it. Corrected descriptor setup and terminal-test synchronization/Enter handling while verifying that fix. Final keyboard acceptance waits for the replacement dialog; failures were not treated as passes. Stage 5 commit 6326e9ac5.
- Notes: Actual environment is WSL2 (6.6.87.2-microsoft-standard-WSL2). Isolated config/repository fixtures protect live settings. No actual remote SSH target or physical terminal device was available; these remain explicit limits. Browser/theme/icon checks are inapplicable to this terminal-only UI. Full suite and deployment not run.

*— Codex · 2026-10-27 UTC*

## Study closeout and release limits
Status: Six stages locally complete; stage commits retain their scoped summaries. Branch feat/tui-operator-workspace; planning fc951e144; S1 bb6109593, S2 5c2cf14d8, S3 f3125f4ff, S4 5ce25b1e5, S5 6326e9ac5; S6 recorded in the final debug handoff after creation.
Study: railspike-effort / v3/tui_operator_workspace; unchanged v1/A outcome and original baseline; R1/P2, all three frozen hashes reverified. Same single exposed Codex rater; earlier checkpoints preserved. Original entry projection 0.75 and prospective S6 discovery revision 0.85 retained. Separate retrospective whole-cycle scope assessment: 0.85. S1–S6 original stage-work 0.18/0.12/0.10/0.15/0.18/0.14; S6 prospective revision 0.20; separate retrospectives 0.18/0.12/0.10/0.15/0.18/0.22. Non-additive judgments, not measured labor; no independent rating or accepted-count increment. Maintainer comfortable-fit/acceptance pending.
Release boundary: Approved local WSL journeys and recovery verified. Remote SSH, physical-device terminals, deployment, full-suite and maintainer acceptance remain unverified. No merge/push performed. Outside writers that bypass the private sidecar lock can still race the final check; documented operator recovery is to avoid concurrent external edits and retry/review conflicts.
Inventory: Actual dependency delta zero; no manifests, locks for dependencies, vendor code, schema/canonical-record changes, web navigation changes or todo.txt edits. Private-config .lock is runtime coordination, not a dependency lockfile. Human-facing additions use plain text/Key: value; JSON remains the pre-existing stdin machine protocol.

*— Codex · 2026-10-27 UTC*
