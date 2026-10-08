# Offline Snapshot Initialization — Step 2 Feature Description

> **Feature plan:** [Step 1](./offline_snapshot_initialization_step1_solution_assessment.md) · [Step 2](./offline_snapshot_initialization_step2_feature_description.md) · [Step 3](./offline_snapshot_initialization_step3_development_plan.md) · [Step 4](./offline_snapshot_initialization_step4_implementation_summary.md)

## Problem

First-time setup makes a read model but does not ensure that the public offline
snapshot exists. A new public instance can therefore return 404 for
`/offline/snapshot.sqlite3` before any later update triggers publication.

## User Stories

- As an operator, I want first-time public provisioning to publish an offline
  snapshot automatically so that launch does not depend on a forgotten command.
- As a reader, I want the offline reader's initial snapshot request to succeed
  so that I can save the advertised offline reading set.
- As an operator, I want a failed initial publication to stop readiness with a
  clear recovery path so that I do not launch with a hidden 404.

## Core Requirements

- A successful initial public read-model setup automatically invokes the
  existing bounded, atomic offline-snapshot publication before readiness.
- A launch-ready public instance has a valid snapshot at the canonical endpoint;
  no later write, scheduled worker, or manual `offline publish` is required to
  create the first one.
- Preserve existing public-only and approved-members-only behavior, atomic
  replacement, and the last valid snapshot on a later publication failure.
- Surface initial-publication failure to the operator and provide an explicit
  retry/recovery path; do not generate a snapshot in a visitor request.
- Keep the established update-driven task-queue refresh path unchanged after
  initialization.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: an operator follows the standard first-time public setup after
  the read model is available.
- End-to-end outcome: provisioning publishes the snapshot and verifies the
  endpoint before the instance is considered launch-ready.
- Recovery: a failed bootstrap is visible, leaves no partial publication, and
  can be rerun through the supported provisioning/publication path.
- Release condition: a clean public instance proves a successful snapshot
  response; approved-members-only mode remains intentionally unpublished.

## Risks

- **Initial database or artifact-path misconfiguration:** publication cannot
  start; validate on a clean configured instance and report the failed stage
  before Step 3.
- **Readiness without an accessible public route:** a file may exist but be
  unreachable through host routing; validate the canonical endpoint before
  marking setup complete.
- **Privacy regression:** bootstrap could expose a snapshot when public offline
  reading is disallowed; validate approved-members-only behavior and retain the
  existing guard.
- **Duplicate work with normal refresh automation:** startup and later worker
  paths could overlap; reuse existing publication locking/atomic semantics and
  validate coexistence before Step 3.

## Shared Component Inventory

- **Initial read-model/provisioning lifecycle — extend:** make initial
  publication and endpoint readiness part of the canonical setup flow.
- **Offline snapshot publisher and `offline publish` — reuse:** remain the sole
  bounded public-snapshot builder and recovery surface.
- **Task queue and snapshot task — reuse unchanged:** continue handling
  post-initialization, update-driven publication.
- **Snapshot locator, front controller, and offline reader/service worker —
  reuse unchanged:** continue serving and requesting the canonical URL.
- **Production deployment and offline-reading runbooks — extend:** describe the
  automatic bootstrap, failure recovery, and launch verification.

## User Flow

1. An operator provisions a public instance and completes its initial read-model setup.
2. The lifecycle automatically publishes the bounded public snapshot.
3. Setup verifies `/offline/snapshot.sqlite3` and reports readiness or failure.
4. A reader loads the public offline reader and receives the initial snapshot.

## Success Criteria

- On a clean eligible public instance, the standard setup flow yields a valid
  snapshot at the canonical endpoint before launch without manual publication.
- The first offline-reader/service-worker fetch receives a non-404 snapshot
  response after successful setup.
- A bootstrap failure is operator-visible, recoverable, and never replaces a
  valid snapshot with a partial artifact.
- Approved-members-only instances do not publish a public snapshot.
