> **Feature plan:** [Step 1](./private_message_sync_idle_step1_solution_assessment.md) · [Step 2](./private_message_sync_idle_step2_feature_description.md) · [Step 3](./private_message_sync_idle_step3_development_plan.md) · [Step 4](./private_message_sync_idle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/private_message_sync_idle — [ledger](../effort_estimation_trial.md#cycle-v3private_message_sync_idle)
Extended estimates: enabled
Status: Step 3 approved; implementation in progress

**Cycle estimate: 0.75**

Step 4 entry: 2026-10-29 UTC; 0.75 reaffirmed, scope v1/A, original baseline 02dafa857, updated base e614fbd9b, R1/P2. Codex single exposed rater; inherited verified references. Planning commit 09a0a660a. Original S1–S6 stage estimates 0.12/0.14/0.16/0.16/0.16/0.06 and scopes/uncertainties are preserved in Step 3; non-additive. Measured labor unavailable. Deployment and production acceptance not authorized by this approval.

*— Codex · 2026-10-29 UTC*

## Stage 1 - History gate

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Add account-level has_history to unread state using an existing bounded mailbox query; unread coordinator shares identity-bound eligibility and wakes sync on status refresh. Sync waits for eligibility instead of starting on every page.
- Verification: 27/27 focused history/read-state/mailbox PHP tests; node tests/browser/private_message_history_gate.cjs passes initial ordering, unused account, first-message activation, identity change and failed-status recovery. git diff --check passes.
- Notes: No schema/dependency/UI changes. Real browser end-to-end acceptance remains Stage 5. Original scope/uncertainty unchanged; no estimate revision. Ledger planning records included now, outside the planning commit.

*— Codex · 2026-10-29 UTC*
