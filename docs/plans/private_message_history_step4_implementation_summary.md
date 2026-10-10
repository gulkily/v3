# Private message history Step 4 implementation summary

> **Feature plan:** [Step 2](./private_message_history_step2_feature_description.md) · [Step 3](./private_message_history_step3_development_plan.md) · [Step 4](./private_message_history_step4_implementation_summary.md)

## Stage 1 - Stable conversation snapshots

- Changes: added viewer/counterpart-scoped 25-message history pages with insertion-bound snapshots, timestamp/insertion ordering, exact page replay, and validated continuation anchors. Existing list-returning methods and no-cursor behavior remain compatible; the conversation API adds page/next cursors and an explicit invalid-cursor restart response. Initial HTML metadata and ciphertext retrieval now use the same snapshot, with page-specific reader caching.
- Verification: `php tests/run.php PrivateMessageStoreTest PrivateMessageMailboxServiceTest PrivateMessagePageControllerTest PrivateMessageReaderTest PrivateMessageApiRoutingTest` — 24 passed. Tests cover 63 tied messages, interleaved/backdated arrivals, empty/exact page bounds, replay, malformed/foreign/stale cursors, controller responses, and reader snapshot/cache isolation. `node tests/browser/private_message_list_browser.mjs` passed existing real-encryption, navigation, recovery, shared-composer and layout journeys; artifacts `/tmp/private-message-browser-vW9Xzv/`. Syntax and whitespace checks passed.
- Notes: no schema migration or production mutation. The first regression run caught and corrected a SQLite parameter-affinity issue before commit. Planning commit: `1995c45f`; this branch builds on unmerged Cycle 2. Per-message error presentation and historical-key access are unchanged.

## Stage 2 - Load older messages

- Changes: added bounded, serialized Load older requests and loading/exhaustion feedback. Older messages prepend chronologically with deduplicated IDs using the same shell, grouping, dates, time formatter, and verification path as initial/inline messages. Each card retains its own ciphertext for isolated retry; malformed or foreign message payloads are rejected before rendering.
- Verification: `php tests/run.php PrivateMessagePageControllerTest PrivateMessageReaderTest PrivateMessageComposerTest` — 10 passed. Expanded `node tests/browser/private_message_list_browser.mjs` passed, traversing over 63 messages, preserving drafts, excluding an interleaved backdated arrival, preventing duplicate requests on repeated activation, and retrying the oldest message without refetching the newest window. Artifacts: `/tmp/private-message-browser-gkwQo2/`. Syntax and whitespace checks passed.
- Notes: explicit scroll anchoring follows in Stage 3; full restart/error recovery follows in Stage 4. Existing unreadable-message warnings were reused without redesign.
