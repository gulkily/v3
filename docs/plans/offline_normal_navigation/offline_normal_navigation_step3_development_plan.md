# Offline Normal Navigation Step 3 Development Plan

## Stage 1 - Safe normal-route offline boundary
- Goal: Allow the cache layer to handle supported normal navigations without reintroducing a page-reload loop.
- Dependencies: Existing public snapshot and prototype cache.
- Expected changes: Replace prototype-only scope with a versioned normal-navigation worker; use network-first online delivery and offline fallback only for supported public navigations; retire the prototype registration safely.
- Verification approach: Browser smoke test: normal online board remains stable after worker activation; offline fallback is attempted only after a network failure.
- Risks or open questions:
  - Worker upgrades must not retain incompatible cached shells.
- Canonical components/API contracts touched: Service-worker registration, static asset routing, public cache lifecycle.

## Stage 2 - Shared snapshot presentation layer
- Goal: Make one local snapshot renderer available to both prototype and normal-route fallbacks.
- Dependencies: Stage 1.
- Expected changes: Extract local recent-thread and thread-detail rendering into reusable browser functions; retain snapshot timestamp and unavailable-state contracts.
- Verification approach: Script checks plus fixture-driven local rendering checks for a list, a thread, and a missing thread.
- Risks or open questions:
  - Presentation should remain recognizably aligned with normal board/thread screens without duplicating online interactions.
- Canonical components/API contracts touched: Public snapshot schema, hidden reader renderer, board/thread presentation semantics.

## Stage 3 - Offline normal board
- Goal: Render the normal public board URL from the local snapshot when offline.
- Dependencies: Stages 1-2.
- Expected changes: Add a board navigation fallback and snapshot-backed recent-thread screen; direct each saved-thread control to its normal thread URL.
- Verification approach: Browser offline smoke test from `/` after an online cache refresh; assert timestamp, bounded list, and no write controls.
- Risks or open questions:
  - Query/view variants need an explicit reconnect state rather than silently changing their meaning.
- Canonical components/API contracts touched: Normal board URL, thread-list presentation, offline navigation policy.

## Stage 4 - Offline normal thread
- Goal: Render snapshot-contained normal thread URLs, including ordered replies, when offline.
- Dependencies: Stages 1-3.
- Expected changes: Add thread navigation fallback and snapshot-backed root/reply view; expose reconnect treatment for actions and missing threads.
- Verification approach: Browser offline smoke test for a saved thread URL, a missing thread URL, and back navigation to the normal board URL.
- Risks or open questions:
  - Fragment handling must preserve readable navigation without implying live post availability.
- Canonical components/API contracts touched: Normal thread URL, root/reply presentation, online-action boundary.

## Stage 5 - Privacy, recovery, and release verification
- Goal: Prove the fallback is bounded, public-only, and recoverable.
- Dependencies: Stages 1-4.
- Expected changes: Add route/cache/privacy regression tests; update the offline runbook with normal-route behavior and cache recovery.
- Verification approach: Full suite; browser online-to-offline-to-online smoke; approved-members-only regression.
- Risks or open questions:
  - Existing browser-held public data remains non-revocable and must remain documented.
- Canonical components/API contracts touched: Approved-members gate, public snapshot limits, Offline Reading Runbook.
