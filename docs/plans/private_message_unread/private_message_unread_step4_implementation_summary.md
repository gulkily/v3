# Private message unread state Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md) · [Step 4](./private_message_unread_step4_implementation_summary.md)

## Stage 1 - Durable private state

- Changes: added private tracking/baseline/signing-secret metadata and per-username/counterpart seen positions, without altering envelopes. Initialization is atomic and precedes new deliveries; progress is monotonic and insertion anchors are validated. State returns a global conversation count, up to 25 requested row states, and a coherence revision. Grouped the four FDP artifacts and repaired their links/index.
- Verification: `php tests/run.php PrivateMessageReadStateTest PrivateMessageStoreTest PrivateMessageMailboxServiceTest` — 20 passed. Covers ties/backdating, own sends, >25 conversations, scoped boundaries, concurrent first use/acknowledgment, existing-history baseline, old-code insertion during rollback, re-upgrade, and invalid anchors. PHP syntax and whitespace checks passed.
- Notes: planning-only commit `687e94f7`. Schema changes occur only in isolated tests so far; no production migration, merge, or push. Keep metadata during code rollback to avoid resetting baseline/progress. No UI is wired until later stages.
