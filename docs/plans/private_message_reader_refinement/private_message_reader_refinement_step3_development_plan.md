# Private Message Reader Refinement: Step 3 Development Plan

> **Feature plan:** [Step 1](./private_message_reader_refinement_step1_solution_assessment.md) · [Step 2](./private_message_reader_refinement_step2_feature_description.md) · [Step 3](./private_message_reader_refinement_step3_development_plan.md) · [Step 4](./private_message_reader_refinement_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an authenticated member opens Inbox or Sent.
- End-to-end outcome: one bounded page (25 messages) reads locally and automatically; verified plaintext and a counterpart-adjacent “Signature verified” label appear without manual reader controls or the encrypted-message module.
- Required recovery: unavailable keys, retrieval/decryption failure, and failed verification show no plaintext or verified label but retain concise, visible failure feedback.
- Deployment/external verification: run the focused PHP and browser-reader tests; confirm no persistent browser plaintext cache and no ciphertext in rendered card markup.
- Release condition: both rendered and API mailbox lists are capped at 25; no persistent plaintext cache, manual reader control/status line, or encrypted-message module remains.

## Key Risks

- **High risk:** Unbounded mailbox retrieval could trigger excessive reads. Early validation: seed more than 25 messages through each mailbox surface. Mitigation: enforce the shared 25-message limit in the data retrieval contract before automatic reading.
- **High risk:** Incorrect verification presentation could expose untrusted plaintext. Early validation: test unavailable key, decryption failure, and invalid signature paths. Mitigation: make plaintext and the label success-only.
- Impact: persistent plaintext would weaken private-message confidentiality. Early validation: inspect browser storage in reader tests. Mitigation: allow only in-page memory and add an explicit non-persistence assertion.

## Stage 1

- Goal: Establish a shared, bounded mailbox retrieval contract.
- Dependencies: Approved Step 2 requirements.
- Expected changes: Add a 25-message limit to `PrivateMessageStore` inbox/sent retrieval and to `PrivateMessageMailboxService::inbox()` / `sent()`; cover ordering and bound behavior with store/service tests.
- Verification approach: Seed 26+ ordered envelopes and prove each authorized mailbox receives only its newest 25 while authorization remains unchanged.
- Risks or open questions:
  - Impact: Inbox and Sent could diverge in their bounds.
  - Early warning / validation: Exercise both retrieval paths with the same over-limit fixture.
  - Mitigation: Use one shared limit contract in the store/service layer.
- Canonical components/API contracts touched: `PrivateMessageStore`, `PrivateMessageMailboxService`, mailbox store/service tests.

## Stage 2

- Goal: Apply the bounded retrieval contract to both canonical mailbox representations.
- Dependencies: Stage 1 bounded service contract.
- Expected changes: Have `PrivateMessagePageController` and `PrivateMessageApiController` use the bounded inbox/sent service methods; update page/API coverage for the 25-message response and rendered list.
- Verification approach: Render Inbox/Sent and request their APIs with more than 25 records; assert exactly the authorized bounded set and no ciphertext in page HTML.
- Risks or open questions:
  - Impact: The reader could request an item outside the rendered page.
  - Early warning / validation: Compare rendered message IDs with API message IDs in an over-limit fixture.
  - Mitigation: Keep page and API on the identical ordered, bounded service contract.
- Canonical components/API contracts touched: `PrivateMessagePageController`, `PrivateMessageApiController`, `/messages/*`, `/api/private_messages/*`.

## Stage 3

- Goal: Simplify mailbox-card presentation for automatic reads.
- Dependencies: Stage 2 canonical bounded page list.
- Expected changes: Update `private_messages.php` to remove the manual decrypt-and-verify button, status-line element, and encrypted-message identifier; add a hidden counterpart-adjacent verified-label target while retaining a hidden plaintext target and a concise failure target.
- Verification approach: Page-render test confirms absent UI, correct From/To label targets, and no encrypted envelope in output.
- Risks or open questions:
  - Impact: Removing the status target could eliminate required recovery feedback.
  - Early warning / validation: Assert a distinct failure target remains in markup.
  - Mitigation: Keep only the general reader status line removed; use the dedicated failure presentation target.
- Canonical components/API contracts touched: `private_messages.php`, `PrivateMessagePageControllerTest`.

## Stage 4

- Goal: Automatically read each rendered mailbox card without persistent caching.
- Dependencies: Stages 2–3; existing OpenPGP reader and recipient-key lookup.
- Expected changes: Change `private_message_reader.js` from click-driven invocation to one automatic read per rendered card, bind successful verification to its counterpart label, retain failure-only feedback, and keep plaintext/results in page memory only.
- Verification approach: Extend browser-reader coverage for automatic card handling, verified/unavailable/decryption-failed/bad-signature states, and absence of plaintext/result writes to persistent browser storage.
- Risks or open questions:
  - Impact: Concurrent reads could leave stale labels or plaintext after a failure.
  - Early warning / validation: Simulate mixed success and failure cards in one mailbox.
  - Mitigation: Scope each result update to its card and clear plaintext/label before non-verified results.
- Canonical components/API contracts touched: `private_message_reader.js`, `private_messages.js` recipient-key lookup, `PrivateMessageReaderTest`.

## Stage 5

- Goal: Validate the complete private-message reader slice and release boundary.
- Dependencies: Stages 1–4.
- Expected changes: Align focused PHP/browser tests and release-isolation assertions with the simplified mailbox UI; no product behavior beyond the approved scope.
- Verification approach: Run private-message mailbox, page, reader, store, and release-isolation tests; inspect rendered output and browser-storage test evidence.
- Risks or open questions:
  - Impact: Refined UI could accidentally weaken private-message isolation.
  - Early warning / validation: Focused release-isolation suite failure or ciphertext in output.
  - Mitigation: Keep no-store responses and existing private-message asset/isolation checks intact.
- Canonical components/API contracts touched: private-message focused test suite, `PrivateMessageReleaseIsolationTest`.
