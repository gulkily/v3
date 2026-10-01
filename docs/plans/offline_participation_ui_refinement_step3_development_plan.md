# Offline Participation UI Refinement Step 3 Development Plan

## Stage 1
- Goal: Define a durable signed offline-action and two-timestamp contract.
- Dependencies: Approved Steps 1–2; existing Outbox state and browser identity.
- Expected changes: Extend local items with frozen action time, canonical intent data, detached signature, and integration outcome fields; introduce `createSignedIntent(input): Promise<OutboxItem>`.
- Verification approach: Unit tests cover deterministic intent contents, signature-at-queue behavior, and safe summaries excluding signed payloads.
- Risks or open questions:
  - Device time is attributed, not authoritative.
- Canonical components/API contracts touched: Outbox state/storage; browser signing.

## Stage 2
- Goal: Accept a signed reaction intent exactly once and record integration time.
- Dependencies: Stage 1; existing thread/post reaction authorization.
- Expected changes: Extend the reaction write contract to verify target, author, detached signature, and stable intent ID; return action and integration times with duplicate-safe outcomes.
- Verification approach: API tests cover thread/comment Likes, invalid signatures, duplicate delivery, changed authorization, and integration-time ordering.
- Risks or open questions:
  - Existing reaction records must remain readable.
- Canonical components/API contracts touched: Reaction write API; canonical reaction records; read-model integration.

## Stage 3
- Goal: Establish the online presentation contract for supported offline cards.
- Dependencies: Existing online thread/reply cards and snapshot renderer.
- Expected changes: Define and test matching title, body, metadata, card/action, and Like-control presentation for snapshot thread roots and comments.
- Verification approach: Renderer contract tests compare supported online/offline structural and class output.
- Risks or open questions:
  - Online-only controls must remain visible only where their offline behavior is defined.
- Canonical components/API contracts touched: Online thread/reply cards; offline snapshot presentation.

## Stage 4
- Goal: Queue signed Likes from matching thread-root and comment controls.
- Dependencies: Stages 1–3.
- Expected changes: Route supported offline Like clicks through the signed-intent contract while preserving online control appearance and truthful local feedback.
- Verification approach: Offline interaction tests cover root/comment click, no local score mutation, missing identity, and retained queue state.
- Risks or open questions:
  - Only Like is in scope; flags remain excluded.
- Canonical components/API contracts touched: Offline reader; reaction controls; Outbox capture.

## Stage 5
- Goal: Automatically process deliberately queued work in the foreground.
- Dependencies: Stages 1–2; current Outbox sender.
- Expected changes: Add `processQueuedOutbox(): Promise<DeliveryResult[]>` on online load/reconnect, with a durable single-delivery lock; exclude drafts and Background Sync.
- Verification approach: Tests cover reconnect success, restart/retry, offline no-op, duplicate delivery prevention, and observable failures.
- Risks or open questions:
  - A reconnect must not send a draft or race another tab.
- Canonical components/API contracts touched: Outbox sender/storage; browser connection lifecycle; signed reaction API.

## Stage 6
- Goal: Make Outbox scanning compact without hiding meaningful state.
- Dependencies: Stages 1 and 5; existing Tools → Outbox shell.
- Expected changes: Render one collapsed row per item with summary/state; expose action time, integration time, outcome, and state-appropriate controls on expansion.
- Verification approach: Presentation tests cover keyboard expansion, timestamp labels, accepted/conflict detail, and safe collapsed content.
- Risks or open questions:
  - Local payload must never leak into collapsed summaries.
- Canonical components/API contracts touched: Tools → Outbox template and client presentation; Outbox safe-summary contract.

## Stage 7
- Goal: Document the changed delivery and timestamp behavior and release-test it.
- Dependencies: Stages 1–6.
- Expected changes: Update the runbook, roadmap, and MVP checklist; document foreground-only automatic delivery and two-time semantics.
- Verification approach: Run focused client/API suites and an acceptance flow for root/comment Likes and queued content reconnect.
- Risks or open questions:
  - Background Sync remains a separately approved future slice.
- Canonical components/API contracts touched: Offline Reading Runbook; offline support roadmaps; MVP checklist.
