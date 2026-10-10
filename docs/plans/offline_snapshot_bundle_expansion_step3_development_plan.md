# Offline Snapshot Bundle Expansion — Step 3 Development Plan

> **Feature plan:** [Step 1](./offline_snapshot_bundle_expansion_step1_solution_assessment.md) · [Step 2](./offline_snapshot_bundle_expansion_step2_feature_description.md) · [Step 3](./offline_snapshot_bundle_expansion_step3_development_plan.md) · [Step 4](./offline_snapshot_bundle_expansion_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a visitor reconnects and loads a supported public page after a
  public write or read-model rebuild.
- End-to-end outcome: a compact ordered update is applied by idempotent upsert
  to one saved SQLite database before the enlarged full base replaces it; that
  database contains all approved keys and only content-associated unapproved
  keys.
- Required recovery: failed publication, download, or update application
  retains the prior readable database and exposes retryable operator state.
- Deployment/external verification: publish against a production-shaped
  read-model and confirm an online refresh then supported offline navigation.
- Release condition: focused builder, queue, worker, reader, and navigation
  tests pass, including public-data boundaries and interrupted refreshes.

## Key Risks

- **High risk: data boundary.** Impact: unapproved keys unrelated to saved
  content or non-public records could be exposed. Early validation: fixtures
  cover approved keys, content-associated unapproved keys, and exclusions.
  Mitigation: one canonical selection contract in Stage 1.
- **High risk: rollback.** Impact: a partial client update corrupts or removes
  offline reading. Early validation: interrupt download/application tests.
  Mitigation: validate and atomically replace only a complete database.
- **High risk: latency.** Impact: full-base work delays new content. Early
  validation: queue a large base with a newer compact update. Mitigation:
  prioritize the update and coalesce base work in Stage 3.

## Stage 1 - Define bounded import contracts

- Goal: Establish the enlarged base, ordered compact-update, and public-key
  selection contracts.
- Dependencies: Approved Step 2; existing public snapshot builder.
- Expected changes: Choose and document a browser-validated base capacity and
  recent-thread allowance; extend the snapshot version/metadata and define
  conceptual base, update, and key-set import records. Plan builder contracts
  equivalent to `buildBase(...)` and `buildUpdate(...): array`.
- Verification approach: Builder fixtures prove whole-thread selection, the
  complete approved key set, only content-associated unapproved keys, and all
  public-only exclusions within the chosen bound.
- Risks or open questions:
  - Impact: an oversized default fails browser storage expectations.
  - Early warning / validation: generate and cache an enlarged representative
    fixture before dependent work.
  - Mitigation: make the selected bounded defaults and resulting metadata the
    Stage 1 output.
- Canonical components/API contracts touched: `PublicOfflineSnapshotBuilder`,
  snapshot metadata/manifest, public read-model profiles and post/thread rows.

## Stage 2 - Publish the base and compact update safely

- Goal: Produce independently validated, atomically published base and update
  artifacts from the ready public read model.
- Dependencies: Stage 1 contracts and capacity decision.
- Expected changes: Extend the snapshot publisher/locator and public route
  contract for the enlarged base plus ordered update artifact; retain the last
  valid publications on a build or publish failure.
- Verification approach: Publisher and route tests cover atomic replacement,
  update ordering, artifact validation, public-key contents, and fallback to
  the prior valid base.
- Risks or open questions:
  - Impact: an update can be served against the wrong base generation.
  - Early warning / validation: publish successive base/update fixtures.
  - Mitigation: validate declared generation/order before exposure.
- Canonical components/API contracts touched: `OfflineSnapshotPublisher`,
  `OfflineSnapshotLocator`, `FrontController`, offline manifest route.

## Stage 3 - Prioritize fresh updates and coalesce base work

- Goal: Request compact updates promptly after eligible changes without making
  writes wait, while scheduling full-base replacement as coalesced work.
- Dependencies: Stage 2's publication contract and existing task queue.
- Expected changes: Add update/base task intent and priority to the queue
  lifecycle; extend successful public write/rebuild hooks, status, retry, and
  operator commands without changing approved-members-only behavior.
- Verification approach: Queue and write/rebuild tests prove update priority,
  deduplication, retry, and a newer update completing before queued base work.
- Risks or open questions:
  - Impact: a long-running base can starve a later update.
  - Early warning / validation: enqueue both against a large fixture.
  - Mitigation: resolve queue ordering before browser work begins.
- Canonical components/API contracts touched: `SqliteTaskQueueStore`,
  `TaskQueueWorker`, write/rebuild enqueue boundary, task-queue CLI/status.

## Stage 4 - Apply updates to one saved client database

- Goal: Download and transactionally upsert ordered compact updates, then
  atomically replace the cached SQLite database the reader already opens.
- Dependencies: Stages 1-3; existing service-worker cache and SQLite runtime.
- Expected changes: Extend the service-worker refresh/revision contract with
  update retrieval, validation, idempotent application, and prior-database
  retention; preserve the reader's single-database interface.
- Verification approach: Worker and reader tests cover first install, repeat
  and out-of-order updates, interrupted application, offline fallback, and
  complete approved-key availability.
- Risks or open questions:
  - Impact: browser-side import cost erodes the intended latency benefit.
  - Early warning / validation: measure apply time on the enlarged fixture.
  - Mitigation: apply only the compact update and leave full replacement
    coalesced.
- Canonical components/API contracts touched: `service_worker.js`,
  `offline_reader.js`, cached `/offline/snapshot.sqlite3`, SQLite runtime.

## Stage 5 - Surface state and verify the vertical slice

- Goal: Make capacity, key-set, and update freshness diagnosable and complete
  the end-to-end release evidence.
- Dependencies: Stages 1-4.
- Expected changes: Extend Offline Reading health/reader status and the
  runbook with base/update generation, import state, retry, and the explicit
  no-remote-revocation boundary.
- Verification approach: Run focused builder, publisher, queue, worker,
  reader, health, and navigation tests; perform an online-refresh then offline
  smoke test against the enlarged fixture.
- Risks or open questions:
  - Impact: operators cannot distinguish a stale base from a failed update.
  - Early warning / validation: inspect state after each injected failure.
  - Mitigation: release only with actionable diagnostic states and recovery.
- Canonical components/API contracts touched: `offline_health.js`, Offline
  Reading health page, offline-reading runbook, task-queue status.
