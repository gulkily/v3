# Private key access Step 3 development plan

> **Feature plan:** [Step 1](./private_key_access_step1_solution_assessment.md) · [Step 2](./private_key_access_step2_feature_description.md) · [Step 3](./private_key_access_step3_development_plan.md) · [Step 4](./private_key_access_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_key_access`; [host ledger](./effort_ledger/private_key_access.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.95**

Approved: Step 2 (maintainer, "approved step 2", 2026-11-24). Epic: [private group chats checklist](./private_group_chats_checklist.md), cycle 1. The Quill confirm command is out of scope and logged as [PF-20261124-F01](../feedback/items/PF-20261124-F01.txt).

## Completion Contract

- Normal entry: Messages on any device; the card on Unread and Messages; Tools → User Access.
- End-to-end: a second key approved by another member shows the device line and gets no new DM. Its owner confirms it by QR from the first device. It then reads a new DM, and history sync fills an older one.
- Recovery:
  - No private device: an operator confirms from User Access.
  - Wrongful lockout: `FORUM_PRIVATE_KEY_ACCESS_ENFORCED` off restores plain approval.
  - Not me: retirement's own confirmation lets a wrong tap be cancelled.
- Deployment: zenmemes.com, deployed in this order: flag off, deploy, confirm Ilya's devices from User Access, flag on.
- Release condition: Stage 8 replays the downgraded-device case on zenmemes.com with Ilya's iPhone SE and a second device.

## Key Risks

- **High risk:** locking Ilya out of his own messages on deploy. Early validation: Stage 1 lists who a local import of zenmemes.com would downgrade. Mitigation: the flag-off release order in Stage 8.
- **High risk:** a chained fake passes rule 2. Early validation: a Stage 1 test with two cross-account keys under one name, the second approved by the first. Mitigation: an approver counts only if it has private access itself.
- Finding the username's first key. Impact: rule 1 picks the wrong key, so a squatter could be treated as the account. Early validation: Stage 1 checks how the read model orders approvals against receipts on the import. Mitigation: use receipt `accepted_at` and classification, falling back to the approval grant's post time.
- History sync gaps. Impact: an old transfer still reaches a public-only key. Early validation: Stage 3 lists every approval check in the sync service before changing any. Mitigation: all three services call one check.

## Stage 1 — The private-access check

**Estimate: 0.15**

- Goal: one answer to "does this key have private access?", with no enforcement yet.
- Dependencies: none.
- Expected changes: a new `PrivateKeyAccess` class with `hasAccess(identityId): bool`, `keysFor(usernameToken): list` and `waitingFor(usernameToken): list`. It applies rules 1–3 from profiles (`is_approved`, `approved_by_identity_id`), retirement, `ApprovalPolicy::isOperator` and receipts. Results are cached per request. A `./v3 approval private-access-report` command lists approved keys that are public only.
- Verification approach: unit tests for each rule, the chained fake, a session bootstrap, a root seed and a retired key. Run the report on a local import of zenmemes.com and record the list.
- Estimate details: driver: the approver chain and first-key ordering; uncertainty: legacy grants without receipts.
- Risks or open questions: the first-key and chain risks above.
- Canonical components/API contracts touched: `ProfileRepository`, `ApprovalAcceptance::read`, `KeyRetirement::profile`, `ApprovalPolicy::isOperator`; the `./v3 approval` command group.

## Stage 2 — The confirm record

**Estimate: 0.15**

- Goal: rule 4, a signed record that gives one approved key private access.
- Dependencies: Stage 1.
- Expected changes:
  - A spec, `docs/specs/private_access_confirmation_record_v1.md`. The record lives at `records/private-access-confirmations/openpgp-<fingerprint>.txt` with a detached signature, and its `Key: value` headers follow the retirement record: Version, Action-ID, Created-At, Actor-Identity-ID, Target-Identity-ID, Account, Authority (member or operator).
  - Prepare and submit endpoints, following retirement's: `POST /api/prepare_private_access_confirmation` and `POST /api/confirm_private_access`.
  - `PrivateKeyAccess` reads these records the way `KeyRetirement` reads retirements, so the read-model schema does not change.
- Verification approach: tests that a member confirms only a same-username key, an operator confirms any key, a public-only key cannot confirm, a repeat is a no-op, and the result survives a rebuild.
- Estimate details: driver: signing and acceptance under the write lock; uncertainty: low, since retirement is the template.
- Risks or open questions: the record must be covered by content import and export. Check `ArchiveRecordCatalog` and `SourcePathValidator`.
- Canonical components/API contracts touched: `KeyRetirement`, `KeyRetirementPolicy`, `SourcePathValidator`, `ArchiveRecordCatalog`, `docs/reference/agent_api.md`.

## Stage 3 — Enforcement behind the flag

**Estimate: 0.15**

- Goal: public-only keys get no new messages, no history and no mailbox.
- Dependencies: Stages 1 and 2.
- Expected changes:
  - `ApprovedUserKeyResolver` returns only `PrivateKeyAccess::keysFor`, except for historical verification keys.
  - The history sync key checks (`key`, `nextKey`, `hasKey`, `context`) and the mailbox's `approvedViewer` call `hasAccess`. A refused mailbox request answers with a distinct `public_only` error.
  - A new flag, `FORUM_PRIVATE_KEY_ACCESS_ENFORCED`, in the ACCESS group, on by default and requiring `FORUM_PRIVATE_MESSAGES_ENABLED`.
- Verification approach: tests per service with a public-only key: absent from recipient keys, no transfer offered or accepted, mailbox refused. The same tests with the flag off match `main`. Run the existing messaging tests.
- Estimate details: driver: the sync service's many entry points; uncertainty: senders' signatures from public-only keys must still verify.
- Risks or open questions: the history sync gaps risk above.
- Canonical components/API contracts touched: `ApprovedUserKeyResolver`, `PrivateMessageHistorySyncService`, `PrivateMessageMailboxService`, `FeatureFlagRegistry`.

## Stage 4 — The public-only device line

**Estimate: 0.10**

- Goal: a public-only device sees why Messages is empty and what to do.
- Dependencies: Stage 3.
- Expected changes: a `public-only` state in `private_message_access.php`, with the line "This device can't read messages yet", a short code from the fingerprint, Show QR (the bundled `qrcode-generator.js`) and the operator fallback line. The QR encodes a readable confirm URL, `/account/confirm/<fingerprint>`.
- Verification approach: the page in each state, scripts on and off, every theme, 375 and 1280 px; final on first paint.
- Estimate details: driver: the pre-paint state decision; uncertainty: low.
- Risks or open questions: the short code must match on both devices. One helper in `browser_signing.js` makes it.
- Canonical components/API contracts touched: `private_message_access.php`, `private_message_access.js`, `qrcode-generator.js` as used by `invite_issuance.js`.

## Stage 5 — The Confirm / Not me card

**Estimate: 0.12**

- Goal: the account's private devices see waiting keys and act on them.
- Dependencies: Stages 2 and 4.
- Expected changes:
  - One partial with the code and Confirm and Not me, placed as a module in `inbox.php` (Unread) and `private_message_list.php` (Messages).
  - `/account/confirm/<fingerprint>` shows the same card for one key.
  - Confirm signs through the Stage 2 endpoints. Not me opens the existing retirement flow for that key.
- Verification approach: a two-browser local test: confirm by card and by QR URL, then read a DM on the second browser. Not me reaches retirement's confirmation. Themes and widths as in Stage 4.
- Estimate details: driver: signing from the card; uncertainty: the retirement flow's entry for a key that is not the viewer's.
- Risks or open questions: if retirement refuses a key with a changed username, Not me falls back to "Ask an operator".
- Canonical components/API contracts touched: `inbox.php`, `private_message_list.php`, the retirement UI and API, `browser_signing.js`.

## Stage 6 — Operator list in User Access

**Estimate: 0.08**

- Goal: operators see and confirm waiting keys.
- Dependencies: Stage 5.
- Expected changes: a "Waiting for private access" section beside the pending list in `user_access.php`, from `PrivateKeyAccess::waitingFor`, each with Confirm. It works with the enforcement flag off.
- Verification approach: an operator confirms a downgraded key locally; a non-operator never sees the section.
- Estimate details: driver: one list and one button; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `UserAccessController`, `user_access.php`.

## Stage 7 — Docs and concepts

**Estimate: 0.05**

- Goal: the change can be found and understood.
- Dependencies: Stages 1–6.
- Expected changes:
  - A new concept entry, `docs/concepts/private_key_access.md`.
  - Updates to `private_message_history.md`, `docs/reference/agent_api.md` (beside "Approving keys") and the release gate in `docs/product/v3_feature_priorities.md`.
  - The flag's description, and the concept inventory index.
- Verification approach: `git diff --check`, link check, `./v3 feedback check`.
- Estimate details: driver: several docs; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: concept inventory, agent API reference.

## Stage 8 — Release and replay on zenmemes.com

**Estimate: 0.05**

- Goal: Ilya's own case works in production.
- Dependencies: merge.
- Expected changes: none in code. Record the release in the Step 4 summary and the epic checklist.
- Verification approach:
  1. Turn the flag off, deploy, and run the private-access report.
  2. Ilya confirms his own devices from the iPhone SE, using User Access or the card. Then the flag goes on.
  3. A second device that was downgraded shows the line, is confirmed by QR, and reads a recent DM.
- Estimate details: driver: Ilya's time on two devices; uncertainty: how many keys the report lists.
- Risks or open questions: production access is Ilya's; the agent prepares the steps and records results.
- Canonical components/API contracts touched: none.

Concepts touched: [Private-message history](../concepts/private_message_history.md) (changed), [Approval acceptance](../concepts/approval_acceptance.md) (read), [Key retirement](../concepts/key_retirement.md) (used by Not me; model for the record), [Feature flags](../concepts/feature_flags.md) (one flag). New entry: "Private key access", in Stage 7.

Tests: each stage runs the smoke set plus its own tests; the full suite is not run.

## Study checkpoint

- Step 3 handoff, 2026-11-24 UTC: 0.90 → 0.95 projected total, same scope v2. New understanding: the confirm route and QR landing page (Stage 5) and the private-access report command (Stage 1). Stage work: 0.15, 0.15, 0.15, 0.10, 0.12, 0.08, 0.05, 0.05; not summed. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
