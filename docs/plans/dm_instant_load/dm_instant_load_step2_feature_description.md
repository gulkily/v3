# DM instant load Step 2 feature description

> **Feature plan:** [Step 1](./dm_instant_load_step1_solution_assessment.md) · [Step 2](./dm_instant_load_step2_feature_description.md) · [Step 3](./dm_instant_load_step3_development_plan.md) · [Step 4](./dm_instant_load_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_instant_load`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.70**

Approved direction: Step 1 Option B built on Option A (approved 2026-11-11). This approval amends the history-sync rule that plaintext stays in browser memory only: decrypted messages may persist in this browser's storage, encrypted with a browser key that cannot be exported.

## Problem

Every visit to a conversation re-decrypts the visible history before any text appears, so on a phone the page opens on a "…" placeholder and an empty or half-drawn thread.

## User stories

- As a member reopening a conversation, I want my recent messages to appear at once so that DMs feel like a chat app, not a loading screen.
- As a member on a slow phone, I want the newest messages to appear first so that I can read and reply before older history finishes.
- As a member who removes this browser's identity, I want its saved messages removed too so that leaving no key behind also leaves no readable history behind.
- As the operator, I want the cache behind a feature flag so that I can turn it off if it misbehaves.

## Core requirements

- On a repeat visit in the same browser, previously read messages render without loading OpenPGP, and the conversation opens at the newest message without visible jumping.
- Messages not yet cached decrypt as today, newest first, and are added to the cache once read successfully.
- Cached text is stored only in encrypted form, under a key this browser generated and cannot export; the cache is tied to the browser identity's fingerprint and is deleted when that identity is cleared.
- A cached message never shows more trust or freshness than a live read: retracted or now-unavailable messages leave the cache, and verification marks follow the same rules as a live read.
- With the flag off, or when browser storage is unavailable or fails, the page behaves exactly as today.

## Delivery scope

Work type: application change (browser scripts for the conversation page, one feature flag, documentation and concept entry).

## Completion boundary

- Normal entry: Messages → a conversation, on phone and desktop.
- End-to-end: second visit shows cached messages on first paint; new incoming messages decrypt and join the cache.
- Recovery: storage errors, a full or blocked store (private browsing), a changed or restored identity, and flag-off all fall back to live decryption without user action.
- Release condition: flag ships off by default; the maintainer enables it in production after trying it.

## Known user or environment

The maintainer on iPhone Safari (screenshot from 2026-11-10). Entry: zenmemes.com → Messages → a conversation. Failure handled: the page opens on "…" before text. Verification: reopen a read conversation and see text immediately; if not, turning the flag off restores today's behavior. Evidence: a before/after recording or screenshot from that phone; desktop Chromium evidence alone does not satisfy this.

## Risks

- **First-paint timing**: browser storage reads are asynchronous and may land after the first frame. Validate early by timing a cached read on iPhone Safari; mitigate with a brief hold on the thread area before showing it.
- **Stale trust**: a cached message could outlive its retraction or show a check mark from an old verification. Validate by listing every reader state and when it changes; mitigate by caching only fully verified reads and dropping entries the server no longer lists.
- **Safari storage quirks**: Safari can evict site storage and restricts non-exportable keys in some modes. Mitigate by treating any cache failure as a miss.
- **Identity changes**: switching or restoring a key must not show another identity's history. Mitigate by keying the cache to the fingerprint and clearing it with the identity.

## Shared component inventory

- `public/assets/private_message_reader.js`: canonical decrypt path and reader states; extend with a cache lookup before decryption and parse keys once per page (Option A). No second reader.
- `public/assets/private_message_conversation.js`: initial positioning and settle event; reuse as is, cache hits settle sooner.
- `public/assets/outbox_storage.js`: existing IndexedDB open/upgrade pattern; reuse its approach for the new store rather than inventing another.
- `public/assets/browser_signing.js` `clearBrowserIdentity`: the one place identity is removed; extend to delete the cache and its key.
- `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: add one flag beside `PRIVATE_MESSAGES_ENABLED`.
- History sync (`private_message_history_sync.js`) and server storage are unchanged.

## User flow

1. Member opens a conversation they have read before in this browser.
2. Cached messages appear at once, scrolled to the newest.
3. Any new messages decrypt, newest first, and appear below.
4. Next visit, those are cached too.

## Success criteria

- On iPhone Safari, a 50-message conversation previously read in the same browser shows readable text in the first rendered frame after the hold, with no "…" placeholder.
- Uncached first visits are no slower than today, measured on the same conversation.
- Clearing the browser identity leaves no cache store or cache key behind.
- With the flag off, the existing messaging browser checks behave as on main.

Concepts touched: [Private-message history](../../concepts/private_message_history.md) (reading only). Proposed inventory addition: "Local message cache" (encrypted per-browser store of read messages, keyed to the identity fingerprint).

Enhancement requests: DM page layout and composer work is shipping separately (PRs #161, #165) and is not part of this cycle.

## Study snapshots

- Step 2 handoff, 2026-11-11 UTC: 0.70 projected total reaffirmed; scope v1 unchanged except recording the approved storage-rule amendment. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*
