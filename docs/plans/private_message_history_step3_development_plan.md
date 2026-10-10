# Private message history Step 3 development plan

> **Feature plan:** [Step 2](./private_message_history_step2_feature_description.md) · [Step 3](./private_message_history_step3_development_plan.md) · [Step 4](./private_message_history_step4_implementation_summary.md)

## Completion Contract

- Outcome: Messages → conversation → Load older → all history in the opening snapshot → latest/reply; recover page/read failures without lost drafts, duplicate messages, or interrupted reading.
- Release: focused tests and isolated HTTP/browser journeys pass; preserve encryption, authorization, existing clients, and static/offline isolation. No migration, production deployment, or external service. Rollback restores Cycle 2 code/assets without changing stored messages or drafts.
- Boundary: build on Cycle 2; no merge/push implied. Decryption-widget redesign, historical-key access, unread state, and live updates remain deferred. Five stages budgeted ≤1 hour each; split oversized stages, rescope beyond eight/day. After approval: new feature branch, planning-only commit, then verification/summary/commit per stage.

## Key Risks

- **High risk: omitted, duplicated, or disclosed messages.** First test timestamp ties, arrivals, and foreign cursors; freeze an insertion boundary and scope every request to viewer/counterpart.
- **High risk: reading jumps.** First delay decryption while scrolling/zooming; coordinate browser anchoring with visible-message preservation and respect subsequent navigation.
- **High risk: stale envelopes or lost drafts.** First retry old messages and send during loading; retain page-specific ciphertext and independent composer state. Resolve risks before dependent stages.

## Stage 1

- Goal: open a stable recent conversation with authorized continuation.
- Dependencies: approval; snapshot/ownership fixtures first.
- Expected changes: 25-message pages ordered by timestamp/insertion, frozen boundary, scoped opaque cursors; pin initial page markup and reader fetch to the same snapshot. No schema change.
- Verification approach: three pages, ties, backdated/interleaved arrivals, empty/exact/full pages, malformed/foreign/stale cursors, no-store, no-cursor compatibility.
- Risks or open questions: arrivals shift initial decryption; validate exact page replay before UI loading.
- Canonical components/API contracts touched: Store `conversationPageFor(string $viewer, string $counterpart, ?string $cursor = null): array`; service `conversationPage(array $viewer, string $counterpart, ?string $cursor = null): array`; page/API controllers and reader cache. Conversation API retains `status/messages`, adds `page_cursor/next_cursor`; existing list-returning methods remain compatible.

## Stage 2

- Goal: reach older messages from the conversation.
- Dependencies: Stage 1 cursor/replay checks pass.
- Expected changes: Load older, serialized requests, chronological prepend, ID deduplication, loading/exhaustion states; reuse canonical shells, groups/dates, timestamps, and supplied-envelope verification.
- Verification approach: three pages, repeated activation, mixed signatures/unavailable keys, cross-page groups, isolated retry beyond the newest 25.
- Risks or open questions: newest-window lookup strands older messages; retain each loaded envelope for retry without persistent plaintext caches.
- Canonical components/API contracts touched: conversation template/script/styles, message partial, `readCard(..., suppliedMessage)`, time formatter; no new renderer/crypto implementation.

## Stage 3

- Goal: preserve reading position through asynchronous layout changes.
- Dependencies: Stage 2; slow-decryption fixtures first.
- Expected changes: retain visible message/offset through prepend and decryption; rebase or cancel corrections after user navigation. Coordinate native anchoring, grouping, and composer fallback; never replay initial scrolling for history.
- Verification approach: long messages, mixed failures, mobile/zoom, navigation during loading, latest-message action, concurrent replies; stationary anchor within 5px after settlement.
- Risks or open questions: competing scroll corrections interrupt readers; test out-of-order completion and prioritize user navigation.
- Canonical components/API contracts touched: conversation scroll/append lifecycle, reader completion promises, shared composer layout; send contract unchanged.

## Stage 4

- Goal: recover failed history loads without losing context.
- Dependencies: Stages 1–3 verified.
- Expected changes: retry the same cursor, retain loaded messages/drafts, explain authorization or unusable-cursor failures. Explicit restart replaces history only after a fresh window succeeds; ignore obsolete responses.
- Verification approach: network/malformed responses, stale cursor, failed restart, eligibility loss, pending send/newer draft, older-message read retry.
- Risks or open questions: recovery replaces useful state; isolate pagination from composer/attempt storage and keep prior content on failure.
- Canonical components/API contracts touched: history controls, conversation API error/restart contract and controller; preserve list, inbox/sent, profile/aggregate composers and current per-message warnings.

## Stage 5

- Goal: verify and hand off the complete history journey.
- Dependencies: Stages 1–4 committed; risks resolved.
- Expected changes: browser/regression fixtures, API reference, implementation summary, master checklist; retain deferred follow-ups.
- Verification approach: normal Messages entry, ≥3 pages, ties/arrivals, exact-once coverage, anchored decryption/retry, send/restart, authorization/no-store, shared composers, static/offline isolation, syntax and document links.
- Risks or open questions: synthetic checks miss real layout/encryption; require controlled-identity browser evidence and report physical-device limitations.
- Canonical components/API contracts touched: existing messaging suites/browser harness, API documentation, FDP artifacts; no production data.
