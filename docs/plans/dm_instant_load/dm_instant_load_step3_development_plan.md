# DM instant load Step 3 development plan

> **Feature plan:** [Step 1](./dm_instant_load_step1_solution_assessment.md) · [Step 2](./dm_instant_load_step2_feature_description.md) · [Step 3](./dm_instant_load_step3_development_plan.md) · [Step 4](./dm_instant_load_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_instant_load`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.70**

Concepts touched: [Private-message history](../../concepts/private_message_history.md) (reading path). Add a "Local message cache" entry in Stage 5.

## Completion Contract

- Normal entry: Messages → a conversation, phone and desktop.
- End-to-end: on a second visit in the same browser, read messages show on first paint at the newest message; new messages decrypt newest first and join the cache.
- Recovery: any cache failure, blocked storage, identity change or flag off falls back to today's live read; clearing the identity removes the cache and its key.
- Deployment: no server data changes; deploy as usual. Flag `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED` ships off.
- Release: maintainer turns the flag on in production and confirms on iPhone Safari (Stage 6).

## Key Risks

- **High risk:** a cached message shows trust it no longer has. Early validation: Stage 3 reader-state review. Mitigation: cache only `verified` results, look up only messages the server still lists, and keep cache entries keyed by identity fingerprint.
- **High risk:** async storage reads land after first paint and the thread jumps. Early validation: Stage 4 timing on Chromium with throttling, then iPhone in Stage 6. Mitigation: a short capped hold on the transcript.
- Safari eviction or key restrictions. Validation: Stage 2 failure tests. Mitigation: every failure is a cache miss.

## Stage 1

**Estimate: 0.15**

- Goal: faster live reads with nothing persisted (Option A).
- Dependencies: none.
- Expected changes: parse the browser's private key once per page instead of per message; start loading OpenPGP when a conversation page binds; read newest cards first.
- Verification approach: browser check that a conversation still reads, verifies and retries; timing note before/after on the same 50-message fixture.
- Estimate details: driver: reordering inside the reader without disturbing retry and history recovery; uncertainty: whether newest-first conflicts with history-load anchoring.
- Risks or open questions: reordering could change the initial-settle signal. Validation: existing `checkChatLayout`. Mitigation: settle still waits for all visible cards.
- Canonical components/API contracts touched: `public/assets/private_message_reader.js` (`decryptEnvelope`, card read queue); no new reader.

## Stage 2

**Estimate: 0.15**

- Goal: encrypted cache store and feature flag.
- Dependencies: Stage 1.
- Expected changes: new `public/assets/private_message_cache.js` with `get(fingerprint, messageId)`, `put(fingerprint, messageId, plaintext)`, `clearAll()`; one IndexedDB database holding a non-extractable AES-GCM key and encrypted entries; flag in `FeatureFlagRegistry` exposed on the conversation page.
- Verification approach: browser checks for round trip, wrong fingerprint miss, blocked IndexedDB and failed key generation both returning misses.
- Estimate details: driver: WebCrypto and IndexedDB error paths; uncertainty: Safari's handling of stored non-exportable keys.
- Risks or open questions: storing a `CryptoKey` in IndexedDB may fail on some Safari modes. Mitigation: treat as disabled for the page.
- Canonical components/API contracts touched: `outbox_storage.js` open/upgrade pattern reused; `FeatureFlagRegistry.php` beside `PRIVATE_MESSAGES_ENABLED`. New module justified: no existing encrypted local store.

## Stage 3

**Estimate: 0.15**

- Goal: reader uses the cache.
- Dependencies: Stage 2.
- Expected changes: before the mailbox fetch, look up the card's message id; on a hit, present it as `verified` without OpenPGP or network; on a live `verified` read, store it; never store other states.
- Verification approach: browser check that a second visit renders text with OpenPGP never requested; failed and unavailable messages are never cached; flag off makes no cache calls.
- Estimate details: driver: keeping hit and live paths on one presentation function; uncertainty: interaction with history-sync recovered candidates.
- Risks or open questions: stale check mark (Key Risks). Mitigation as above.
- Canonical components/API contracts touched: `private_message_reader.js` `readCardOnce`, `setReaderResult`, `presentState`.

## Stage 4

**Estimate: 0.10**

- Goal: no jump on first paint; identity removal clears the cache.
- Dependencies: Stage 3.
- Expected changes: hold the transcript until cached lookups finish or a short cap passes; `clearBrowserIdentity` calls `clearAll()`.
- Verification approach: layout check that the first visible frame is at the newest message; clearing identity leaves no cache database.
- Estimate details: driver: timing the hold; uncertainty: Safari's IndexedDB latency.
- Risks or open questions: first-paint jump (Key Risks).
- Canonical components/API contracts touched: `private_message_conversation.js` initial positioning; `browser_signing.js` `clearBrowserIdentity`.

## Stage 5

**Estimate: 0.10**

- Goal: documentation and concept inventory.
- Dependencies: Stages 1–4.
- Expected changes: concept entry "Local message cache"; amend the history-sync storage findings to record the approved exception; flag description.
- Verification approach: `git diff --check`; links resolve; smoke set plus changed tests; final diff review for duplication and scope.
- Estimate details: driver: wording the storage-rule amendment precisely; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `docs/concepts/`, `private_message_history_sync_storage_findings.md`.

## Stage 6

**Estimate: 0.05**

- Goal: replay the reported iPhone Safari condition.
- Dependencies: PR merged and flag on in production.
- Expected changes: none; acceptance evidence only.
- Verification approach: maintainer reopens a read conversation on iPhone Safari and sees text immediately; screenshot or recording recorded in Step 4. Desktop evidence does not count.
- Estimate details: driver: coordinating the maintainer's check; uncertainty: Safari storage behavior.
- Risks or open questions: if it still shows "…", flag off restores today's behavior while debugging.
- Canonical components/API contracts touched: none.

## Study snapshots

- Step 3 handoff, 2026-11-11 UTC: 0.70 reaffirmed after adding verification and recovery stages; not the stage sum (0.70). Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*
