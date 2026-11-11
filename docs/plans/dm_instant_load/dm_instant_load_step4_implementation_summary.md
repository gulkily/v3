# DM instant load Step 4 implementation summary

> **Feature plan:** [Step 1](./dm_instant_load_step1_solution_assessment.md) · [Step 2](./dm_instant_load_step2_feature_description.md) · [Step 3](./dm_instant_load_step3_development_plan.md) · [Step 4](./dm_instant_load_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/dm_instant_load`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled. Stage estimates below are copied from the approved Step 3 plan; Claude single exposed rater throughout.

**Cycle estimate: 0.70**

Branch: `feature/dm_instant_load`, from `origin/main` at `f13dd20f`. Planning commit `6e138ddc`.

## Study snapshots

- Step 4 entry, 2026-11-11 UTC: 0.70, Step 3 reaffirmed unchanged. Recorded with the Stage 1 commit; the summary was created after the planning commit but alongside Stage 1 work rather than strictly before it.

## Stage 1 - Faster live reads

**Original estimate: 0.15**

- Changes:
  - `private_message_reader.js`: the browser's private key and each sender key are parsed once per page and reused; a failed parse is dropped so retry parses again.
  - Conversation and mailbox pages start loading OpenPGP when the reader binds, if this browser has a key.
  - Cards start reading newest first. All reads still run together and settle waits for every card.
- Verification:
  - `./v3 test --smoke PrivateMessageReaderTest PrivateMessageReleaseIsolationTest`: 451 passed.
  - `./v3 test PrivateMessageHistoryCryptoTest` (runs the history reader harness): 8 passed.
  - Messaging browser suite through `checkChatLayout`: passed, with two failures that also occur on main bypassed (Users link step; history-sync `state()` assertion).
  - Before/after timing on a 50-message fixture: not measured; no such fixture exists yet. Deferred to Stage 6 on the maintainer's phone.
- Notes:
  - The identity-change guard in `decryptEnvelope` still compares the stored key against the one read at the start, so a cached parse never decrypts for a replaced identity.
- Estimate details: original scope as Step 3; uncertainty: newest-first vs. history anchoring, which `checkChatLayout` covered; revisions: none.

**Retrospective stage-work assessment: 0.10** — smaller than planned because reads already ran concurrently, so reordering was one line.

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*

## Stage 2 - Encrypted cache store and flag

**Original estimate: 0.15**

- Changes:
  - New `public/assets/private_message_cache.js`: `get(messageId)`, `put(messageId, plaintext)`, `clearAll()`, `enabled()`. One IndexedDB database (`forum-private-message-cache-v1`) holds a non-extractable AES-GCM key and per-message ciphertext; entries are keyed and authenticated (AES-GCM additional data) by identity fingerprint plus message id. Every failure returns a miss. A second tab racing to create the key adopts the first tab's key. The open connection closes on `versionchange` so `clearAll` can delete it.
  - Flag `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED` ("Saved message copies", off, site-mutable, Experience group), exposed to templates as `privateMessageCacheEnabled` and on the conversation root as `data-private-message-cache`.
  - Conversation page loads the cache script before the reader. Nothing reads from the cache yet.
- Verification:
  - New `tests/browser/private_message_cache_browser.mjs` (routed https origin, Chromium): round trip, ciphertext-only storage, non-extractable key, other fingerprint misses, flag off makes no reads or writes, `clearAll` removes the database while the page holds it open, blocked IndexedDB and failed key generation are misses. Passed.
  - `./v3 test --smoke FeatureFlagRegistryTest FeatureFlagEvaluatorTest PrivateMessageConversationPageTest`: 444 of 445 before updating the flag lists; `FeatureFlagEvaluatorTest` updated for the new flag in definition and presentation order.
  - `./v3 test FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest`: 26 of 27. `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder` fails identically on main (its toolbar fixture exits 255 in `tools_nav_card.php`); baseline failure, not caused here.
  - Safari: not run here; covered by Stage 6.
- Notes:
  - A test bug found during verification: after `clearAll`, a later `get` reopens (recreates) an empty database. That is acceptable: it holds only a fresh key, and clearing the identity runs on a page where no reads follow.
- Estimate details: original scope as Step 3; uncertainty: Safari's stored non-exportable keys, still open until Stage 6; revisions: none.

**Retrospective stage-work assessment: 0.15**

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*

## Stage 3 - Reader uses the cache

**Original estimate: 0.15**

- Changes:
  - `private_message_reader.js` `readCardOnce`: on conversation pages, before the mailbox fetch, a cache hit is presented as `verified` through the same `setReaderResult`/`presentState` path; no fetch, no OpenPGP. Live `verified` results are saved; no other state is ever saved. History-recovery retries (which pass a supplied message or candidate) skip the cache and read live.
  - New `tests/browser/private_message_cached_conversation.mjs`, run in the messaging suite after `checkChatLayout`.
- Verification:
  - Messaging suite through the new check, flag on (`FORUM_PRIVATE_MESSAGE_CACHE_ENABLED=1`): every verified message saved once; second visit shows identical text and check marks with zero `/api/private_messages/conversation` requests; another fingerprint misses. Passed.
  - Same run, flag off: nothing saved. Passed.
  - Both runs bypassed the two baseline failures that also occur on main (Users link step; history-sync `state()` assertion).
  - `./v3 test --smoke PrivateMessageReaderTest PrivateMessageHistoryCryptoTest`: 458 passed. `testAutomaticallyReadsRenderedCardsWithoutPersistingPlaintext` still holds: the cache never runs outside conversation pages or with the flag off.
- Notes:
  - Trust: a cached message shows the check mark it earned when first read; a later sender-key retirement does not remove it. The server listing still decides which messages appear, so retracted messages never show from the cache. Recorded for the concept entry.
  - Stage 1's background OpenPGP preload still runs on cached visits; it doesn't block rendering.
- Estimate details: original scope as Step 3; uncertainty: history-sync interaction, resolved by skipping the cache for history retries; revisions: none.

**Retrospective stage-work assessment: 0.15**

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*
