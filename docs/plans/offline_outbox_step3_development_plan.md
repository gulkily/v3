# Offline Outbox Step 3 Development Plan

## Stage 1 - Define durable Outbox state
- Goal: Establish one action-neutral state vocabulary and transition policy.
- Dependencies: Approved Step 2 MVP boundary.
- Expected changes: Define the Outbox item contract and valid transitions for vote, reply, and thread intents; planned client contract: `OutboxStore.list()`, `save(item)`, `transition(id, state)`.
- Verification approach: Unit tests cover valid/invalid transitions, ordering, safe summaries, and pending counts.
- Risks or open questions:
  - State names must not imply server acceptance before confirmation.
- Canonical components/API contracts touched: Outbox item lifecycle, local intent ID contract, user-visible state vocabulary.

## Stage 2 - Persist local work safely
- Goal: Retain drafts and queued intents across restart without using the public offline cache.
- Dependencies: Stage 1 state contract and browser storage availability.
- Expected changes: Add dedicated browser persistence with quota/error reporting, export/discard support, and clear-data safeguards; no read-model migration.
- Verification approach: Browser-storage tests cover restart, unavailable storage, quota failure, export, discard, and clear-warning paths.
- Risks or open questions:
  - Shared-device retention and identity changes must not expose pending content unexpectedly.
- Canonical components/API contracts touched: Dedicated Outbox storage, browser identity boundary, public service-worker cache exclusion.

## Stage 3 - Expose Tools → Outbox offline
- Goal: Make all pending work visible from Tools without crowding main navigation.
- Dependencies: Stages 1-2 and existing Tools/offline routing.
- Expected changes: Add the Tools entry, Outbox screen, item states/counts/controls, empty state, and direct offline shell support; no public snapshot data expansion.
- Verification approach: Route, offline-fallback, and rendering tests cover empty, pending, outcome, and needs-attention views.
- Risks or open questions:
  - The cached shell must never make locally stored work available as a public artifact.
- Canonical components/API contracts touched: Tools navigation, offline navigation policy, Outbox accessibility/status presentation.

## Stage 4 - Prove queueing with supported votes
- Goal: Add the first queueable action and validate the shared model.
- Dependencies: Stages 1-3 and existing reaction semantics.
- Expected changes: Route the approved reaction(s) into Outbox when offline; add stable intent IDs and an idempotent server outcome contract.
- Verification approach: Test offline capture, duplicate/retry, cancel/undo semantics, accepted/rejected outcomes, and unavailable targets.
- Risks or open questions:
  - Only reactions whose moderation semantics are safe for delayed delivery belong in this MVP.
- Canonical components/API contracts touched: Reaction controls, reaction write API, Outbox action adapter, idempotency contract.

## Stage 5 - Capture reply drafts and queued replies
- Goal: Preserve reply work and expose it as an editable Outbox item.
- Dependencies: Stages 1-4 and existing reply compose flow.
- Expected changes: Add local reply draft/queue actions, thread-context snapshots, edit/export/discard controls, and parent-unavailable explanations.
- Verification approach: Test offline creation, restart, edit, queue, cancel, and hidden/locked/removed-parent states.
- Risks or open questions:
  - Stored context must be sufficient to explain a changed parent without copying private content.
- Canonical components/API contracts touched: Inline reply compose, reply draft lifecycle, Outbox reply adapter.

## Stage 6 - Capture new-thread drafts and queued threads
- Goal: Preserve subject/body work and expose it as an editable Outbox item.
- Dependencies: Stages 1-5 and existing thread compose flow.
- Expected changes: Add local thread draft/queue actions, restore/edit/export/discard controls, and truthful pending-thread presentation.
- Verification approach: Test offline creation, restart, validation feedback, edit, queue, and discard/export paths.
- Risks or open questions:
  - A queued thread must never appear in Board results before acceptance.
- Canonical components/API contracts touched: Thread compose, compose draft lifecycle, Outbox thread adapter.

## Stage 7 - Explicit send and reconciliation
- Goal: Submit queued actions online through existing signed workflows and retain observable outcomes.
- Dependencies: Stages 4-6, online identity readiness, and idempotent server contracts.
- Expected changes: Add explicit send/retry processing for each action type, invoking existing prepare/sign/finalize journeys for replies/threads; retain accepted, rejected, conflict, and needs-attention results.
- Verification approach: End-to-end tests cover reconnect, signing unavailable, duplicate retry, acceptance, rejection, conflict, and cancellation races.
- Risks or open questions:
  - Background Sync remains optional and must not silently submit composed content.
  - Server validation and authorization must be rechecked at send time.
- Canonical components/API contracts touched: Prepare/sign/finalize APIs, reaction write API, Outbox reconciliation and outcome contract.

## Stage 8 - Document and release-test the MVP
- Goal: Make the local-work lifecycle understandable and verify supported browsers.
- Dependencies: Stages 1-7.
- Expected changes: Update Outbox/offline runbook guidance and the MVP checklist; no database changes.
- Verification approach: Run focused client/server tests, browser QA with and without Background Sync, and the acceptance demo for one vote, reply, and thread.
- Risks or open questions:
  - Clear-data warnings and outcome retention must be understandable before release.
- Canonical components/API contracts touched: Offline Reading Runbook, Outbox user guidance, browser QA matrix.
