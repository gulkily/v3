# Private Messaging Step 3 Development Plan

> **Feature plan:** [Step 1](./private_messaging_step1_solution_assessment.md) · [Step 2](./private_messaging_step2_feature_description.md) · [Step 3](./private_messaging_step3_development_plan.md) · [Step 4](./private_messaging_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an authenticated member selects Message on another composite identity's profile.
- End-to-end outcome: all active sender/recipient keys receive the envelope; Sender reads Sent and recipient decrypts Inbox.
- Required recovery: failed send retains the local draft; no plaintext reaches the server.
- Deployment/external verification: back up the private database and inspect every public output for message absence.
- Release condition: identity scoping, multi-key delivery, recovery, focused tests, and full suite pass.

## Key Risks

- **High risk: incomplete active key set.** Early validation: multi-key fixture. Mitigation: establish the authoritative key resolver before mailbox work.
- **High risk: public output leaks message data.** Early validation: output inspection after send. Mitigation: isolate mailbox storage from public data paths.
- **High risk: cross-mailbox access.** Early validation: cross-identity API test. Mitigation: scope every query to the authenticated identity.

## Stage 1
- Goal: Resolve a composite identity's active encryption keys.
- Dependencies: Approved Step 2.
- Expected changes: Add `activeRecipientKeys(identityId)` and multi-key fixtures; block if no authoritative source exists.
- Verification approach: Test active-key inclusion and retired-key exclusion.
- Risks or open questions:
  - Impact: A device cannot read its envelope.
  - Early warning / validation: Fixture-key decryption fails.
  - Mitigation: Use one resolver everywhere.
- Canonical components/API contracts touched: profile/public-key discovery; new `activeRecipientKeys(identityId)` contract.

## Stage 2
- Goal: Persist encrypted envelopes privately.
- Dependencies: Stage 1.
- Expected changes: Add private database config, envelope metadata, and `PrivateMessageStore`; no plaintext.
- Verification approach: Test mailbox rows, ordering, and private-path initialization.
- Risks or open questions:
  - Impact: Public storage retains messages.
  - Early warning / validation: Store test writes public data.
  - Mitigation: No canonical/derived-state dependency.
- Canonical components/API contracts touched: new private-database configuration and `PrivateMessageStore` contract.

## Stage 3
- Goal: Add authenticated mailbox APIs.
- Dependencies: Stage 2.
- Expected changes: Add identity-scoped send, Inbox, and Sent envelope routes.
- Verification approach: Test both parties, third party, missing session, and bad envelope.
- Risks or open questions:
  - Impact: Ciphertext or metadata is exposed.
  - Early warning / validation: Third party retrieves a fixture.
  - Mitigation: Use only session identity.
- Canonical components/API contracts touched: `AuthApiController` session contract; new mailbox controller/service APIs.

## Stage 4
- Goal: Encrypt and sign envelopes in the browser.
- Dependencies: Stages 1 and 3.
- Expected changes: Add one OpenPGP helper for all resolved keys and sender signing.
- Verification approach: Every fixture key decrypts; tampering fails.
- Risks or open questions:
  - Impact: Messages are unreadable or untrusted.
  - Early warning / validation: Any fixture fails.
  - Mitigation: One tested helper.
- Canonical components/API contracts touched: `browser_signing.js`; OpenPGP loader; mailbox envelope payload contract.

## Stage 5
- Goal: Compose and send from a profile.
- Dependencies: Stages 3–4.
- Expected changes: Add Message, composer, local draft, and actionable feedback.
- Verification approach: Browser send; forced failure retains draft and sends no plaintext.
- Risks or open questions:
  - Impact: Draft loss or duplicate send.
  - Early warning / validation: Retry duplicates or clears draft.
  - Mitigation: Stable submission identity; keep draft to success.
- Canonical components/API contracts touched: profile presentation; browser signing feedback conventions; send-mail API.

## Stage 6
- Goal: List Inbox and Sent.
- Dependencies: Stages 3 and 5.
- Expected changes: Add authenticated navigation and identity-scoped mailbox lists.
- Verification approach: Both parties see fixture; third party sees neither.
- Risks or open questions:
  - Impact: Correspondence metadata leaks.
  - Early warning / validation: Cross-identity list returns entry.
  - Mitigation: Store filter and controller ownership check.
- Canonical components/API contracts touched: authenticated navigation; new Inbox/Sent page and list API contracts.

## Stage 7
- Goal: Decrypt selected messages locally.
- Dependencies: Stages 4 and 6.
- Expected changes: Add reader plus unavailable-key, bad-signature, and decryption-failure states.
- Verification approach: Test both copies and each recovery state without server plaintext.
- Risks or open questions:
  - Impact: Invalid content appears valid.
  - Early warning / validation: Bad fixture renders text.
  - Mitigation: Render only verified plaintext.
- Canonical components/API contracts touched: browser envelope helper; new message-reader presentation contract.

## Stage 8
- Goal: Verify release isolation and operations.
- Dependencies: Stages 1–7.
- Expected changes: Add regression coverage and private-store deployment/backup/retention guidance.
- Verification approach: Run suites, multi-key smoke, public-output, and private-permission checks.
- Risks or open questions:
  - Impact: Private state is unrecoverable.
  - Early warning / validation: Guide lacks backup/output checks.
  - Mitigation: Canonical private-store runbook/checklist.
- Canonical components/API contracts touched: production deployment guidance; private mailbox tests; public-artifact build/offline checks.
