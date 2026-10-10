# Private message unread state Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md) · [Step 4](./private_message_unread_step4_implementation_summary.md)

## Stage 1 - Durable private state

- Changes: added private tracking/baseline/signing-secret metadata and per-username/counterpart seen positions, without altering envelopes. Initialization is atomic and precedes new deliveries; progress is monotonic and insertion anchors are validated. State returns a global conversation count, up to 25 requested row states, and a coherence revision. Grouped the four FDP artifacts and repaired their links/index.
- Verification: `php tests/run.php PrivateMessageReadStateTest PrivateMessageStoreTest PrivateMessageMailboxServiceTest` — 20 passed. Covers ties/backdating, own sends, >25 conversations, scoped boundaries, concurrent first use/acknowledgment, existing-history baseline, old-code insertion during rollback, re-upgrade, and invalid anchors. PHP syntax and whitespace checks passed.
- Notes: planning-only commit `687e94f7`. Schema changes occur only in isolated tests so far; no production migration, merge, or push. Keep metadata during code rollback to avoid resetting baseline/progress. No UI is wired until later stages.

## Stage 2 - Authenticated receipt contracts

- Changes: added bounded no-store unread retrieval and same-origin JSON acknowledgment routes, with authenticated service authorization. Fresh conversation windows carry HMAC-protected received-message receipts; unsigned history cursors cannot authorize mutation. Receipts bind viewer, counterpart, and validated insertion anchor; retries retain their boundary. Existing envelope/pagination contracts remain compatible.
- Verification: focused store/read-state/service/page/routing suite — 25 passed; PHP syntax and whitespace passed. Full encrypted browser journey and new HTTP API checks passed (`/tmp/private-message-browser-lCKA13/`), covering forged/foreign receipts, missing request guard, cross-site writes, methods, replay, and backdated arrival after the opened boundary. Malformed row-state arrays are rejected.
- Notes: metadata stays private; fresh-window receipts are separate from history continuation and contain no secret. UI wiring follows in Stage 3.
