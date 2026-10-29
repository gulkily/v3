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

## Stage 2 - Donor-owned contribution evidence

**Original estimate: 0.14**
**Retrospective stage work: 0.14**

- Changes: Add contribution lookup scoped to source/target/message/digest and retained transfer. Optional sender filtering is ready for combined discovery; legacy sender discovery remains unfiltered for explicit repair/replacement.
- Verification: 15/15 history service tests pass, including unchanged sender discovery across recipient acknowledgments, independent donors, missing-transfer rediscovery and invalid unconfirmed candidate replacement.
- Notes: Contribution evidence means supplied, not readable. Recipient foreground candidate validation/retry and other donors remain available; explicit legacy sender discovery/upload retains same-donor repair. No recipient receipt is consulted by the new lookup. Prior stage commit: 4a552bbeb. No estimate revision.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Bounded combined discovery

**Original estimate: 0.16**
**Retrospective stage work: 0.16**

- Changes: Add same-origin POST work for combined account/sender discovery, up to eight pages per mode, with completed-mode and continuation fields. Return pending bundles directly. Advance only empty/covered discovery pages server-side, never client work or access receipts. Preserve legacy GET modes. Reuse own contribution evidence for other own-account targets too.
- Verification: 17/17 history/API routing tests pass; coverage includes genuine idle, 151-message covered history requiring bounded continuation, new target keys and unchanged unprocessed cursors. Initial fixture incorrectly requested an unbounded existing helper; corrected fixture to enumerate its seeded records directly. Syntax/whitespace checked.
- Notes: POST is deliberate because empty discovery progress mutates scan positions. No tables/schema/dependencies added. Prior stage commit: fc7c501f5. No estimate revision.

*— Codex · 2026-10-29 UTC*

## Stage 4 - Combined browser coordinator

**Original estimate: 0.16**
**Retrospective stage work: 0.16**

- Changes: Consume combined work and completion state, stop immediately on idle, process returned bundles without redundant discovery, retain bounded follow-ups and only acknowledge actual receipts/checkpoints. A 405 from an older server selects legacy discovery; terminal HTTP errors are no longer retried as network failures. Foreground candidate/confirm remains independent of the background eligibility gate.
- Verification: Gate test asserts exactly one idle request; scheduling suite passes shared budgets, cancellation, interrupted sender batches, disabled messaging and overlapping re-enable with legacy fallback; real OpenPGP donor tests pass account and sender modes, lost-ack retry, target-only decryption and no plaintext/private-key/cache writes; reader test passes invalid-donor fallback and verification.
- Notes: Original scope/estimate unchanged. Prior stage commit: 2f11c3a83. Full HTTP/browser acceptance follows in Stage 5.

*— Codex · 2026-10-29 UTC*

## Stage 5 - Normal browsing and recovery acceptance

**Original estimate: 0.16**
**Retrospective stage work: 0.20**

- Changes: Add HTTP/browser request-count acceptance and combined/paired scheduling regressions. Preserve the shared eight-bundle budget and alternating priority when a combined response returns two bundles. Valid older unread responses without the additive field keep legacy recovery available. Eligibility now uses SELECT 1 rather than loading ciphertext. Existing theme matrix selects stored themes on reload because some themes hide the picker.
- Verification: Focused PHP regression 41/41; final eligibility/mailbox/read-state check 14/14 after query optimization. Combined, paired and legacy scheduling tests pass. Full real Chromium history suites pass same-account (/tmp/history-sync-browser-RttLVT) and sender-assisted (/tmp/history-sync-browser-F2cexz): unused-account zero sync, settled-history one POST work, 31 originals across partial donors, newly approved/restored devices, unchanged ciphertext, drafts, mobile and desktop reading, paired backup/restore, sync outage with ordinary sending, signed messaging toggles across themes and environment overrides. Final direct sender run (/tmp/history-sync-browser-qV0Jm7) passes updated POST work origin/invalid-body checks, canonical reading and retained reload.
- Corrections: Browser drivers now wait for unread eligibility before requesting sync; earlier immediate refresh assertions raced the new gate. Theme selection originally timed out on a hidden picker; selecting the stored theme before reload tests messaging consistently without changing product navigation. Budget/fairness and legacy-status compatibility corrections stay within scope.
- Limitations: The broader private_message_list_browser.mjs failed at its exact bob user-link lookup (/tmp/private-message-browser-ner7y1), before its unread UI checks; not established as a baseline defect and not counted as passed. Focused unread PHP checks and actual history reading/toggle flows pass. No full suite, production deployment/replay or physical-device checks claimed.
- Notes: Prior stage commit 406979772. No prospective estimate revision; retrospective reflects fixture repair and wider integration checks, not measured labor.

*— Codex · 2026-10-29 UTC*
