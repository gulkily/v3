# Offline Snapshot Bundle Expansion — Step 2 Feature Description

> **Feature plan:** [Step 1](./offline_snapshot_bundle_expansion_step1_solution_assessment.md) · [Step 2](./offline_snapshot_bundle_expansion_step2_feature_description.md) · [Step 3](./offline_snapshot_bundle_expansion_step3_development_plan.md) · [Step 4](./offline_snapshot_bundle_expansion_step4_implementation_summary.md)

## Problem

The bounded public offline snapshot omits useful recent discussion, and a full
larger replacement delays delivery of the newest public content. Readers need
broader saved coverage without waiting to download a replacement base bundle.

## User Stories

- As an offline reader, I want a larger saved discussion set so that more
  recent public threads remain readable without a connection.
- As a reconnecting reader, I want recent public changes to arrive quickly so
  that I can read new discussion before a larger base refresh completes.
- As a future offline capability, I want every approved public key saved with
  the offline import so that later key-dependent features need no separate
  online key download.
- As an operator, I want public snapshot updates to remain safe and observable
  so that a failed refresh never removes the last readable archive.

## Core Requirements

- Publish an enlarged, explicitly bounded public base snapshot containing
  whole eligible threads, required pinned threads, and the newest eligible
  non-pinned threads; retain the existing public-only exclusion rules and
  include every currently approved profile's public key regardless of thread
  selection, with unapproved keys permitted only when accompanying selected
  content.
- Publish a compact, atomic recent-content update after each eligible ready
  read-model change; it must contain complete changed public threads and carry
  ordering information that makes retrying it safe, plus the complete current
  approved public-key set and any unapproved keys accompanying its content.
- Apply each recent-content update as an idempotent upsert to a client-side
  working copy, then atomically save one validated SQLite database; the reader
  continues to read that single saved database.
- Preserve the prior saved database on publication, download, application, or
  validation failure, and expose enough freshness/capacity state for recovery.
- Coalesce full-base replacement work independently of the low-latency update;
  an offline browser receives neither until it reconnects and refreshes; later
  hidden/ineligible changes are not published or removed from browser caches.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: a visitor loads any supported public page while online after a
  public content change.
- End-to-end outcome: the browser applies the compact update to its saved
  database first; supported offline Board, Tag, and thread views show its
  newer content while retaining the larger saved base.
- Recovery: an invalid, unavailable, or out-of-order update is ignored and
  the previously saved database remains available; operators can inspect and
  retry publication through existing operational surfaces.
- Release condition: focused publication, reader, worker, and offline
  navigation checks prove public-only data, upsert freshness before full-base
  replacement, and fallback after injected failures.

## Risks

- **Update replay or ordering fails — impact:** readers could duplicate or
  regress saved content. **Early validation:** apply repeated and out-of-order
  updates to a fixture. **Mitigation:** require ordered, idempotent upserts
  and atomically save only a validated result before Step 3.
- **Larger storage or transfer fails — impact:** readers may lack the expanded
  archive. **Early validation:** measure generated bundle size and browser
  cache replacement with an enlarged fixture. **Mitigation:** retain explicit
  limits and the last verified saved database.
- **Public data remains on devices after status changes — impact:** previously
  saved content or approved keys may remain readable. **Early validation:**
  confirm hidden/ineligible changes are not published and unapproved keys are
  included only with selected content. **Mitigation:** retain the documented
  existing browser-cache boundary; do not promise remote removal.

## Shared Component Inventory

- **Public snapshot builder and publisher — extend:** remain the canonical
  public-content and atomic-publication boundary; add the enlarged base and
  recent-update artifacts, each with the complete approved public-key set,
  plus only content-associated unapproved keys, rather than copying the full
  read model.
- **Task queue and successful write/rebuild hooks — extend:** retain their
  deduplication, retry, and status contracts for low-latency updates and
  coalesced base replacement.
- **Snapshot route, locator, manifest, and reader shell — extend:** carry the
  ordered update contract through the canonical public reader
  entry, rather than adding a parallel reader.
- **Service worker — extend:** cache and replace the two artifacts safely,
  apply the compact update to one saved database, and retain the prior valid
  database on failure.
- **Offline reader and Offline Reading health page — extend:** render the
  updated database through the existing supported views and report capacity,
  generation, key-set presence, and freshness; no separate user-facing reader
  is needed.

## User Flow

1. A public write or read-model rebuild succeeds.
2. The compact public update, including the complete approved public-key set
   and any content-associated unapproved keys, is atomically published;
   full-base replacement is coalesced in the background.
3. A connected visitor loads a supported public page and applies the update as
   upserts to its saved database before the larger base replacement completes.
4. Offline navigation reads that one saved database; a later base refresh
   replaces it with the expanded complete database.

## Success Criteria

- The default base bundle has higher documented byte and recent-thread limits
  than the current 25 MiB and 50-thread limits without including non-public
  data.
- Every base import and compact update contains the complete current approved
  public-key set, including keys unrelated to selected discussion threads; any
  unapproved key present accompanies selected content.
- In an end-to-end test, a newly changed eligible thread is available offline
  after its compact update is applied and before the coalesced enlarged-base
  replacement is complete.
- Retried or out-of-order updates leave one saved SQLite database with each
  changed public row represented once and at its newest applicable value.
- Failed publication, interrupted download, or failed application leaves the
  prior offline archive readable and reports actionable state.
